#!/bin/sh
set -eu
umask 077

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
task_dir=$(mktemp -d)
export COMPOSE_PROJECT_NAME="route-generator-smoke-$$"
export DEPLOY_ENV_FILE="$task_dir/production.env"
export APP_IMAGE=route-generator-app
export WEB_IMAGE=route-generator-web
export RELEASE_TAG=deploy-review
export VCS_REF=$(git -C "$project_dir" rev-parse HEAD)
export HTTP_PORT=0

cp "$project_dir/deploy/.env.example" "$DEPLOY_ENV_FILE"
{
    printf '\nCOMPOSE_PROJECT_NAME=%s\n' "$COMPOSE_PROJECT_NAME"
    printf 'APP_KEY=base64:%s\n' "$(openssl rand -base64 32)"
    printf 'DB_PASSWORD=%s\n' "$(openssl rand -hex 24)"
} >> "$DEPLOY_ENV_FILE"

dc() {
    docker compose --env-file "$DEPLOY_ENV_FILE" -f "$project_dir/compose.production.yaml" "$@"
}

cleanup() {
    result=$?
    trap - EXIT INT TERM
    if [ "$result" -ne 0 ]; then dc logs --tail=40 app worker web >&2 || true; fi
    dc down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf "$task_dir"
    exit "$result"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

dc config --quiet
dc build app web
dc up -d postgres gotenberg
dc run --rm app php artisan migrate --force
dc run --rm app php artisan db:seed --class=ProductionCatalogSeeder --force
dc up -d --wait app worker web

address=$(dc port web 8080)
curl --fail --silent "http://$address/up" >/dev/null
status=$(curl --silent --output /dev/null --write-out '%{http_code}' "http://$address/")
[ "$status" = 503 ] || { echo "El acceso interno sin anfitrión respondió $status, se esperaba 503." >&2; exit 1; }
status=$(curl --silent --output /dev/null --write-out '%{http_code}' "http://$address/.env")
[ "$status" = 403 ] || [ "$status" = 404 ] || exit 1

if dc exec -T app php artisan deploy:check > "$task_dir/check.log"; then
    echo 'No se detectó la integración de identidad pendiente.' >&2
    exit 1
fi
grep -E 'Identidad.*ERROR' "$task_dir/check.log" >/dev/null
[ "$(grep -c ERROR "$task_dir/check.log")" = 1 ] || { cat "$task_dir/check.log"; exit 1; }

dc exec -T app php < "$project_dir/tests/Deployment/issue-smoke-route.php"
dc exec -T app php artisan down
dc stop worker
sh "$project_dir/deploy/backup.sh" "$task_dir/backup"
dc exec -T postgres pg_restore --list < "$task_dir/backup/database.dump" >/dev/null
tar -tzf "$task_dir/backup/storage.tar.gz" | grep '\.pdf$' >/dev/null

dc exec -T postgres sh -c 'createdb --username="$POSTGRES_USER" restore_check'
dc exec -T postgres sh -c 'pg_restore --username="$POSTGRES_USER" --dbname=restore_check --no-owner --exit-on-error' < "$task_dir/backup/database.dump"
restored=$(dc exec -T postgres sh -c 'psql --username="$POSTGRES_USER" --dbname=restore_check -At -c "SELECT count(*) FROM learning_routes"')
[ "$restored" = 1 ]
mkdir "$task_dir/restored"
tar -xzf "$task_dir/backup/storage.tar.gz" -C "$task_dir/restored"
set -- "$task_dir"/restored/app/private/documentos/*/*.pdf
[ -f "$1" ]
head -c 5 "$1" | grep '^%PDF-' >/dev/null

echo 'Producción aislada: arranque, migraciones, catálogo, acceso cerrado, worker, PDF, respaldo y restauración verificados.'
