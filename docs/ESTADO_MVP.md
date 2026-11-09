# Estado del MVP · Revisión del 10 de octubre de 2026

El alcance se actualizó el 8 de noviembre de 2026. Las cifras de verificación corresponden a la revisión del 10 de octubre.

## Alcance revisado

Se revisaron las decisiones de `docs/ARQUITECTURA.md`, el modelo y las migraciones, los seeders, las rutas HTTP, controladores, servicios de composición y validación, documentos Blade, cola, interfaz React, centro de confianza y automatización de verificaciones. La revisión conserva el stack y las reglas de negocio existentes.

El producto tiene cuatro áreas operativas: captura/propuesta/emisión de rutas, curación de embudos, bandeja con correcciones y entrega de documentos. CRM y la identidad de producción siguen siendo integraciones previstas, no implementaciones completas.

## Correcciones técnicas

- PDFs: se verifican respuesta y guardado antes de marcar el documento como listo. Las respuestas vacías o ajenas a PDF provocan fallo explícito. El mapa anual se pagina según su contenido: los meses densos continúan con identificación y cada hoja conserva encabezado y folio. La portada y la meta cuentan únicamente los cursos y horas del itinerario; el inventario completo sigue disponible por separado. La vigencia incluye las fechas exactas.
- Cola: reintentos, límites de tiempo, serialización por fila, verificación del snapshot y protección por hash/revisión frente a trabajos antiguos o duplicados.
- Entrega: una ruta rechazada no expone vista imprimible ni descarga; el enlace de respaldo conserva el documento validado si falla Gotenberg. Los reintentos vuelven a activar la descarga automática.
- Identidad: la cuenta de desarrollo solo funciona en `local`/`testing` y no sustituye a otro usuario si falta la cuenta configurada.
- Interfaz: copia al portapapeles con manejo de navegadores sin soporte, fechas locales en captura, espera del PDF acorde al render y cookies de tema normalizadas.
- Calidad: corrección de 87 errores iniciales de análisis estático; formato y lint consistentes. Los reportes de negocio se excluyen del formateador para preservar sus entregables.
- Operación: CI con PostgreSQL y dependencias reproducibles, fuentes locales y setup que conserva la clave de aplicación existente.

Se añadieron pruebas de regresión en identidad, entrega de PDFs y totales del itinerario anual. También se corrigieron verificaciones del diseño anterior: ahora comprueban las tarjetas mensuales, las marcas del inventario y la portada de cada tipo de documento.

## Evidencia de verificación

- Suite completa Pest: **574 pruebas aprobadas, 2,488 aserciones**, ejecutadas con cuatro procesos y bases PostgreSQL aisladas (280.34 segundos).
- PHPStan/Larastan: **0 errores**, sin baseline ni nuevas exclusiones. Se usó ejecución secuencial con `phpstan.restarted=1` y `PAO_DISABLE=1` por el binario PHP estático de este entorno.
- TypeScript, Pint, formato/lint del frontend y `npm run build`: aprobados. Las fuentes quedan incluidas en los assets locales.
- Chromium: nueve pantallas revisadas a 1440 × 1000 y 390 × 844, sin errores de JavaScript ni desbordamientos horizontales.
- Flujo real con PostgreSQL, cola y Gotenberg: emisión/descarga inicial, emisión/descarga anual y corrección del documento inicial. Toda escritura de esta prueba se hizo en una base temporal, separada de los datos de desarrollo.
- PDF inicial: cuatro páginas Carta. Anual de ejemplo a 5 h/semana: ocho páginas Carta, **21 cursos / 257 horas**, vigencia exacta y sin notas internas de pendientes.
- Geometría de impresión revisada a 1, 3, 15 y 40 h/semana: sin contenido recortado ni superposición con el pie; todas las hojas interiores conservan encabezado y folio.
- Guardado de `APP_KEY` existente comprobado en directorio temporal. `composer validate` y `git diff --check` aprobados.

Los PDF y capturas de revisión quedan en `/tmp/route-generator-browser/` durante esta sesión. Los servicios de auditoría y sus bases temporales se retiraron al terminar; los servicios de desarrollo existentes se conservaron.

## Verificación del 8 de noviembre de 2026

Tras retirar el módulo de métricas y actualizar la edición de rutas y los documentos:

- `composer ci:check`: **537 pruebas aprobadas y 2,562 aserciones**, con PostgreSQL real (440.51 segundos). Formato/lint del frontend, TypeScript, Pint y PHPStan también aprobados; el análisis estático termina con cero errores.
- `composer validate --no-check-publish`, `npm run build` y `git diff --check`: aprobados.
- Gotenberg real: tres rutas iniciales —Calidad, Excel y Lean, incluida una captura con contexto largo— de cinco páginas y una ruta anual de diez páginas, todas Carta. El número de páginas coincide con el calculado por la aplicación; no hay hojas interiores huérfanas. Las imágenes y las fuentes están incrustadas.
- Se corrigió el cálculo de espacio de los títulos de grupo y el contexto de los refuerzos, que enviaba únicamente el pie a una página adicional. Una regresión verifica que los tres refuerzos continúen juntos, con encabezado y folio.
- Las pruebas de actualización conservan identificadores del CRM, usuarios, membresías e importes históricos. Los borradores conflictivos no sobrescriben documentos existentes.

Las muestras ficticias de PDF y sus capturas quedan en `/tmp/route-generator-review/` durante esta sesión. Sus bases temporales se eliminaron al terminar; no se ejecutaron estas migraciones sobre los datos de desarrollo.

## Preparación de despliegue del 8 de noviembre de 2026

- Imágenes PHP 8.5/Nginx y Compose de producción separado, con PostgreSQL y Gotenberg privados, worker supervisado y almacenamiento compartido.
- `composer ci:check`: **545 pruebas aprobadas, 2,577 aserciones**; TypeScript, formato/lint, Pint y PHPStan aprobados.
- Prueba Docker aislada: migraciones, catálogo sin cuentas de desarrollo, acceso interno cerrado sin anfitrión, diagnóstico, PDF mediante cola real, respaldo y restauración de la ruta y su archivo. Los recursos temporales se eliminaron al terminar.
- Operación documentada en [DESPLIEGUE.md](DESPLIEGUE.md): instalación, HTTPS, actualización con mantenimiento, respaldo y recuperación. `deploy:check` devuelve un fallo si falta la identidad del anfitrión u otro requisito de producción.

La infraestructura está preparada. El dominio, los secretos persistentes y la integración real de `CurrentAdvisor` deben configurarse antes de habilitar acceso; no se añadió un login propio ni una cuenta genérica de producción.

## Pendientes que requieren datos o decisiones del negocio

Estos pendientes ya figuran en `docs/ARQUITECTURA.md` y no se resolvieron inventando información:

1. Aprobación de Contenido sobre las 64 frases de resultado. Los borradores no deben presentarse como contenido aprobado.
2. El catálogo Excel v2 y los ocho arquetipos reales; actualmente existe el arquetipo general.
3. La discrepancia de lecciones de AutoCAD Completo.
4. La identidad del sistema anfitrión antes de habilitar acceso en producción. La costura existe y la cuenta local queda deshabilitada fuera de desarrollo/pruebas.
5. La integración del CRM y las credenciales/canales externos que el negocio decida activar.
6. Confirmación de Operación del proceso de entrega de credenciales de la academia. Las notas internas de pendientes se retiraron del PDF del miembro.

No se añadieron login propio, administración de catálogo, precios en PDF ni nuevas promesas de certificación. Desde el 8 de noviembre la captura ya no pide el importe; los importes históricos continúan en el expediente de membresía y fuera del documento.
