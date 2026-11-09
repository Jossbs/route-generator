# Despliegue en VPS con Docker

El artefacto incluye PHP 8.5 FPM, extensiones PostgreSQL/Intl/PCNTL, dependencias de Composer sin herramientas de desarrollo y assets compilados con Node 24. Nginx sirve únicamente `public/`. App y worker comparten el volumen privado de documentos; PostgreSQL y Gotenberg no publican puertos. HTTPS termina en el proxy del servidor, delante del puerto local de Nginx.

Se siguen las indicaciones oficiales de [despliegue de Laravel 13](https://laravel.com/docs/13.x/deployment) y [orden de arranque de Docker Compose](https://docs.docker.com/compose/how-tos/startup-order/). Usa Docker Engine y Compose con soporte de `up --wait`.

## Requisito de integración

El módulo requiere el acceso del sistema anfitrión. Antes de habilitarlo, implementa `App\Services\Identity\CurrentAdvisor` y configura `CURRENT_ADVISOR_CLASS` con el nombre completo de esa clase, o registra su binding desde el proveedor del anfitrión. `resolve()` debe devolver el `App\Models\User` del asesor autenticado o `null`. La sesión, token o cabecera usada para resolverlo debe ser verificada por el anfitrión.

No hay login propio ni se habilita una cuenta genérica en producción. Mientras falte esta integración, `deploy:check` falla y los endpoints internos responden 503. `/up` comprueba el arranque de Laravel; no sustituye esa verificación ni una prueba de acceso con un asesor real.

## Primera instalación

Usa un checkout del commit aprobado y conserva su SHA como identificador del release.

```bash
cp deploy/.env.example .env.production
chmod 600 .env.production
```

Edita `.env.production`: dominio HTTPS en `APP_URL`, una contraseña fuerte y exclusiva de PostgreSQL, `CURRENT_ADVISOR_CLASS` y los dominios autorizados del centro de confianza en `TRUST_HUB_ORIGINS`. Si el sistema anfitrión administra la base, sustituye el servicio PostgreSQL por su conexión y retira esa dependencia del Compose.

Genera una clave **solo para una instalación nueva** y guárdala en `APP_KEY`:

```bash
openssl rand -base64 32
```

Anteponer `base64:` al valor. Conserva la misma clave en todos los releases y respaldos; no ejecutes `key:generate` en actualizaciones. La configuración y las claves permanecen fuera de Git y de las imágenes.

Para los siguientes comandos, desde la raíz del checkout:

```bash
export DEPLOY_ENV_FILE="$PWD/.env.production"
export RELEASE_TAG="$(git rev-parse --short=12 HEAD)"
export VCS_REF="$(git rev-parse HEAD)"
dc() { docker compose --env-file "$DEPLOY_ENV_FILE" -f compose.production.yaml "$@"; }

dc config --quiet
dc build --pull app web
dc up -d --wait postgres gotenberg
dc run --rm app php artisan migrate --force
dc run --rm app php artisan db:seed --class=ProductionCatalogSeeder --force
dc up -d --wait app worker web
dc exec -T app php artisan deploy:check
```

`ProductionCatalogSeeder` instala el catálogo inicial sin crear asesores ni embudos de ejemplo. No ejecutes `DatabaseSeeder`, `AdvisorSeeder` ni `FunnelSeeder` para publicar. Crea los asesores mediante la integración del anfitrión y cura los embudos desde la aplicación. No vuelvas a sembrar el catálogo en cada actualización: usa las migraciones que correspondan al release.

Cada contenedor prepara las cachés de configuración, rutas y vistas con sus variables de ejecución. La imagen no contiene `.env`, `public/hot`, `node_modules`, documentos privados ni datos locales. `RELEASE_TAG` debe identificar una imagen inmutable para poder volver a localizarla. Para distribuir imágenes a otro servidor, usa `APP_IMAGE` y `WEB_IMAGE` con los nombres de tu registro, construye una vez y ejecuta `dc push`; en el destino usa `dc pull` y `dc up -d`, sin reconstruir ese mismo tag.

## HTTPS y proxy

El Compose publica por defecto `127.0.0.1:8080`; usa el proxy HTTPS existente del VPS. Ejemplo del bloque de reenvío de Nginx en el servidor, dentro del virtual host con tu certificado y dominio:

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Port $server_port;
    proxy_set_header X-Forwarded-For $remote_addr;
}
```

Configura `TRUSTED_PROXIES` con la IP/CIDR desde la que el contenedor recibe ese proxy. En un VPS suele ser la puerta de enlace de la red Docker. No uses `*` como sustituto de comprobar esa dirección. Después de cambiar variables, recrea app y worker (`dc up -d --force-recreate app worker web`). Las cookies de sesión son seguras y cifradas; prueba la aplicación por su URL HTTPS.

El centro de confianza de `trust-hub/` sigue siendo un sitio estático independiente. Publica esos archivos con su configuración de URL del backend y declara su origen en `TRUST_HUB_ORIGINS`.

## Verificar antes de dar acceso

```bash
dc exec -T app php artisan deploy:check
curl --fail https://tu-dominio/up
dc ps
dc logs --tail=100 app worker web
```

El diagnóstico comprueba entorno, clave, HTTPS configurado, identidad, cookies, conexión PostgreSQL, migraciones, catálogo, tiempos de cola/PDF, servicio Gotenberg, assets y permisos. No imprime claves ni credenciales. Devuelve código 1 si falta un requisito.

Entra con un asesor real, genera una ruta y descarga el PDF terminado por el worker. Comprueba el nombre del asesor, el enlace público y la descarga autenticada. `SESSION_DOMAIN`, si el anfitrión requiere compartir sesión, debe coincidir con el contrato de esa integración. No basta con que `/up` responda 200.

## Actualizar una instalación

La migración `2026_11_08_100000_remove_sales_metrics_module` elimina las tablas de métricas de ventas. Sus datos solo se recuperan restaurando el respaldo; los usuarios, leads, membresías y rutas se conservan. No se ejecuta ninguna migración automáticamente al arrancar un contenedor.

Construye las imágenes del nuevo commit conservando las anteriores y el `.env.production` persistente. Luego, con los contenedores de la versión actual todavía arrancados:

```bash
dc exec -T app php artisan down --retry=60
dc stop worker
sh deploy/backup.sh /ruta/privada/respaldos/antes-del-release

# RELEASE_TAG debe apuntar ahora a las imágenes ya construidas del nuevo commit.
dc run --rm app php artisan migrate --force
dc up -d --force-recreate app web
dc exec -T app php artisan deploy:check
dc exec -T app php artisan up
dc up -d --force-recreate worker
```

No continúes si falla el respaldo, la migración o el diagnóstico. Corrige la causa manteniendo el mantenimiento y el worker detenido. Para probar un PDF al final vuelve a realizar la comprobación funcional. El proceso de actualización tiene una ventana de mantenimiento; no es un despliegue sin interrupciones.

Los workers reciben SIGTERM y tienen 100 segundos para cerrar su trabajo de hasta 90 segundos. Gotenberg tiene un timeout de 60 y la cola `retry_after=120`. La recreación del contenedor carga el nuevo código y configuración; no hace falta iniciar un worker manual dentro del contenedor.

## Respaldar y restaurar

`deploy/backup.sh DIRECTORIO_NUEVO` conserva un dump PostgreSQL, `storage/app` y la revisión e imagen del contenedor en ejecución. Ejecútalo en mantenimiento y con el worker detenido para que el dump y los PDF correspondan al mismo momento. Los archivos se crean con permisos privados; los `.partial` identifican un intento incompleto. Guarda aparte la configuración y `APP_KEY` en tu almacén de secretos. Copia los respaldos fuera del VPS.

Para probar la recuperación, crea otro proyecto Compose y otro archivo de entorno con puertos y volúmenes nuevos. Conserva la clave original y usa la imagen del release respaldado. En ese entorno vacío:

```bash
dc up -d --wait postgres gotenberg
dc exec -T postgres sh -c 'pg_restore --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --no-owner --exit-on-error' < /respaldo/database.dump
dc run --rm --no-deps --entrypoint tar app -xz -C /var/www/html/storage < /respaldo/storage.tar.gz
dc up -d --wait app worker web
dc exec -T app php artisan deploy:check
```

No restaures encima de una base con datos que deban conservarse. Para revertir únicamente código, selecciona las imágenes anteriores solo si el esquema sigue siendo compatible; la migración de retiro de métricas requiere restaurar el respaldo anterior para recuperar ese módulo.

## Prueba automática del paquete

```bash
sh tests/Deployment/containers.sh
```

Construye las dos imágenes y levanta un proyecto Docker temporal con clave, contraseña, puerto y volúmenes propios. Verifica arranque, migraciones, catálogo sin cuentas de desarrollo, bloqueo del acceso interno sin anfitrión, protección de `.env`, diagnóstico, worker y PDF real. Restaura el dump en otra base vacía y extrae el respaldo de archivos para comprobar que recupera la ruta y su PDF. Elimina exclusivamente sus contenedores y volúmenes al terminar. La suite de la aplicación se comprueba en GitHub Actions. Ejecuta además esta prueba de contenedores antes de promover un release.
