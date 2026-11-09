# DP SyS · Generador de rutas formativas

Herramienta interna para asesores: captura un lead, propone una ruta, valida el catálogo y entrega un PDF personalizado. La ruta inicial acompaña la conversación de venta; la ruta anual se entrega al cerrar la membresía. Incluye embudos curados, bandeja por lead, guión de audio y mensaje de WhatsApp.

## Arquitectura

Laravel 13, Inertia 3, React 19, Tailwind 4 y PostgreSQL. Gotenberg 8 convierte HTML autocontenido en PDF; la cola usa PostgreSQL mediante el driver `database`. Las fuentes se sirven desde `public/fonts`, sin descargar recursos durante el build o el render.

- `app/Services/RouteComposition`: composición y reparto por horas de estudio.
- `app/Services/RouteValidation`: intérpretes de las reglas almacenadas en PostgreSQL.
- `app/Services/RouteGeneration`: propuesta, emisión, snapshot y correcciones.
- `app/Services/Documents`: copy, presentación, guiones, archivos y conversión.
- `app/Services/Funnels`: curación y validación de embudos.
- `trust-hub`: sitio estático independiente; reporta eventos a `/hub/eventos`.

Las decisiones de dominio y de producto están en [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md). El [estado del MVP](docs/ESTADO_MVP.md) describe la revisión técnica y los pendientes de contenido e integración.

La [guía de desarrollo](docs/DESARROLLO.md) describe cómo preparar cambios, verificarlos y subirlos en futuras iteraciones.

El [cotejo del catálogo del 9 de noviembre](docs/CATALOGO_2026_11_09.md) confirma los 64 cursos publicados por SYS, incluido PPAP, y documenta la actualización de 41 duraciones. La migración del catálogo aplica esos datos en instalaciones existentes sin modificar los documentos emitidos.

La [guía de despliegue](docs/DESPLIEGUE.md) incluye imágenes de producción, Compose separado, HTTPS, integración de identidad, diagnóstico `deploy:check`, respaldos y restauración. El paquete se verifica con `sh tests/Deployment/containers.sh` en un entorno aislado.

## Desarrollo local

Requisitos: PHP 8.5 con `pdo_pgsql`, Composer 2, Node 24, npm y Docker Compose. PHP 8.5 permite ejecutar las herramientas de desarrollo fijadas en `composer.lock`.

```bash
docker compose up -d
composer setup
php artisan db:seed
php artisan db:seed --class=FunnelSeeder
composer dev
```

`composer setup` crea `.env` si falta, instala dependencias, conserva una `APP_KEY` existente, ejecuta migraciones y compila el frontend. Revisa los valores de `.env` antes de usar una base distinta a la local. `FunnelSeeder` crea únicamente un ejemplo de desarrollo; también puedes crear tus embudos desde `/embudos`.

La aplicación abre en `http://localhost:8000`. PostgreSQL escucha en `5433` y Gotenberg en `3001`. `composer dev` arranca servidor, Vite y worker. Si se arrancan por separado, el PDF requiere este proceso:

```bash
php artisan queue:work --tries=3 --timeout=90
```

Para cargar las ofertas registradas en septiembre de 2026 existe `FunnelOfferSeeder`, separado del catálogo para evitar sobrescribir campañas al sembrar. Revisa `database/seeders/data/funnel_offers_2026_09.php` antes de ejecutarlo.

Configura `DEVELOPMENT_ADVISOR_EMAIL` y, si quieres el botón de contacto en el PDF, `DEVELOPMENT_ADVISOR_WHATSAPP`. La cuenta sembrada solo resuelve identidad en `local` y `testing`.

## Verificación

La suite usa PostgreSQL real y una base exclusiva. Créala una vez:

```bash
docker compose exec postgres psql -U route_generator -d postgres \
  -c 'CREATE DATABASE route_generator_test OWNER route_generator;'
composer ci:check
npm run build
```

`composer ci:check` ejecuta formato y lint del frontend, TypeScript, Pint, PHPStan y Pest. La suite simula Gotenberg; la revisión manual del PDF requiere el contenedor activo. CI prepara su propia base de pruebas.

Para dos suites simultáneas, crea otra base y usa `DB_DATABASE=route_generator_test_2 php artisan test`. Nunca apuntes las pruebas a una base con datos que quieras conservar: `RefreshDatabase` reconstruye el esquema.

## Operación del MVP

Una emisión aprobada queda `validated`, se encola y pasa a `rendered` al guardar el archivo. Hay tres intentos, con esperas de 5 y 15 segundos. Si todos fallan, la ficha permite reintentar o abrir la vista imprimible. El enlace público de una ruta inicial validada sigue disponible cuando falla la conversión del PDF.

Los trabajos llevan hash y revisión; un trabajo antiguo no modifica una corrección posterior. El bloqueo de la fila serializa renders, correcciones y borrados. Una ruta rechazada no se imprime ni se descarga. Los archivos viven en un disco privado y el borrado conserva la auditoría mediante soft delete.

La migración `2026_11_08_100000_remove_sales_metrics_module` elimina las tablas del antiguo módulo de métricas de ventas y su permiso de usuario. En instalaciones existentes, respalda la base antes de ejecutar `php artisan migrate`: los datos de ese módulo se eliminan y solo se recuperan restaurando el respaldo. Los usuarios, leads, membresías y rutas se conservan.

El timeout de Gotenberg debe ser menor que el del trabajo (60 y 90 segundos por defecto). `DB_QUEUE_RETRY_AFTER` debe superar el timeout del trabajo (120 segundos por defecto). Tras desplegar cambios, reinicia los workers con `php artisan queue:restart`.

## Integración y publicación

El módulo no incorpora login propio: el sistema anfitrión debe proporcionar la identidad mediante `App\Services\Identity\CurrentAdvisor`, configurable en `CURRENT_ADVISOR_CLASS`. Los endpoints internos conservan `auth` y las políticas. Con `APP_ENV=production`, la identidad sembrada devuelve `null`; sin integración, los endpoints internos responden 503 y `php artisan deploy:check` falla. Esto evita exponer una cuenta administrativa de desarrollo.

Para producción: configura `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con HTTPS, credenciales de PostgreSQL y una `APP_KEY` persistente; instala dependencias, ejecuta migraciones, compila assets y supervisa el worker. El document root es `public/`. PostgreSQL y Gotenberg deben quedar accesibles solo para la aplicación; los puertos del Compose incluido están pensados para desarrollo local.

El centro de confianza necesita `TRUST_HUB_ORIGINS` con sus dominios autorizados.

Respalda tanto PostgreSQL como `storage/app/private`; conserva la clave de aplicación. Las migraciones y los seeders del catálogo constituyen el mecanismo de actualización, no la edición manual de datos.
