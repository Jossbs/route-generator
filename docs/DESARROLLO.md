# Iteraciones del módulo

El repositorio conserva el código, migraciones, seeders, pruebas, configuración, recursos usados por la aplicación y documentación técnica. El README principal explica la instalación y la operación; `ARQUITECTURA.md` registra las decisiones de dominio y `ESTADO_MVP.md` conserva la revisión técnica del MVP.

## Preparar un cambio

Partir de `main` actualizado y crear una rama por propósito:

```bash
git switch main
git pull --ff-only origin main
git switch -c feat/descripcion-del-cambio
```

Usar `fix/` para correcciones y `chore/` para mantenimiento. Mantener los cambios de esquema en migraciones y las actualizaciones del catálogo en sus seeders. Actualizar la documentación técnica cuando cambien contratos, configuración o decisiones de dominio.

## Verificar

Con las dependencias instaladas y la base exclusiva `route_generator_test` preparada según el README:

```bash
composer validate --no-check-publish
composer ci:check
npm run build
git diff --check
```

`composer ci:check` verifica formato y lint del frontend, TypeScript, Pint, PHPStan y las pruebas con PostgreSQL. PHPStan tiene un límite explícito de 512 MB para no depender del límite por defecto del PHP local. Las pruebas reconstruyen su esquema: usar únicamente una base dedicada.

El build genera `public/build/` y los tipos de Wayfinder; esos archivos permanecen ignorados y se regeneran en cada entorno. Los lockfiles de Composer y npm se versionan cuando cambian las dependencias; para reproducir una instalación, usar `composer install` y `npm ci`.

## Revisar y subir

Seleccionar archivos concretos con `git add`, revisar `git diff --cached` y crear commits por propósito (`feat:`, `fix:`, `chore:` o `docs:`). La descripción del cambio debe explicar el comportamiento resultante y las verificaciones ejecutadas.

Subir únicamente la rama de trabajo:

```bash
git push -u origin feat/descripcion-del-cambio
```

Abrir un pull request hacia `main` y comprobar el workflow `tests`, que vuelve a compilar y ejecutar los controles con PHP 8.5, Node 24 y PostgreSQL.

Los secretos, bases locales, muestras de leads, reportes, documentos académicos y entregables promocionales se conservan fuera de los commits. `.gitignore` excluye las ubicaciones locales conocidas; revisar también cualquier archivo nuevo antes de añadirlo.
