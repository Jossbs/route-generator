#!/bin/sh
# Ejecutar en mantenimiento y con el worker detenido para una copia consistente.
set -eu
umask 077

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
env_file=${DEPLOY_ENV_FILE:-$project_dir/.env.production}
backup_dir=${1:?Indica un directorio vacío para el respaldo}

if [ -e "$backup_dir" ]; then
    echo 'El directorio de respaldo ya existe; elige uno nuevo.' >&2
    exit 1
fi

dc() {
    docker compose --env-file "$env_file" -f "$project_dir/compose.production.yaml" "$@"
}

mkdir -p "$backup_dir"
dc exec -T postgres sh -c 'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --format=custom' > "$backup_dir/database.dump.partial"
dc run --rm --no-deps --entrypoint tar app -cz -C /var/www/html/storage app > "$backup_dir/storage.tar.gz.partial"
app_container=$(dc ps -q app)
docker inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "$app_container" > "$backup_dir/revision.txt"
docker inspect --format '{{.Config.Image}} {{.Image}}' "$app_container" > "$backup_dir/image.txt"
mv "$backup_dir/database.dump.partial" "$backup_dir/database.dump"
mv "$backup_dir/storage.tar.gz.partial" "$backup_dir/storage.tar.gz"
echo "Respaldo completo en $backup_dir. Conserva también APP_KEY y la configuración en tu almacén de secretos."
