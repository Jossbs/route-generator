# Centro de confianza · DP SyS

Página estática para leads y clientes que dudan antes de pagar. El asesor la manda por WhatsApp.
Vive fuera de la aplicación: estos archivos se publican tal cual en cualquier hosting estático.
Lo único que la liga a route-generator es el receptor de eventos (`POST /hub/eventos`).

## Enlace que manda el asesor

    https://<dominio-del-hub>/?a=<clave-del-asesor>&l=<token-de-la-ruta-inicial>

- `a`: clave del asesor en `asesores.json`. Pone su WhatsApp y su nombre en los botones. Si falta
  o no existe, se usa el WhatsApp general (`<meta name="hub-whatsapp">`).
- `l`: `access_token` de la ruta inicial del lead, el mismo que va en `/r/{token}`. Con él, cada
  evento queda ligado a esa ruta, a su lead y a su asesor. Sin él, la visita se mide anónima.
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`: opcionales; se guardan con la apertura.

## Qué se mide (`trust_events`)

| evento | `target` |
|---|---|
| `abierta` | — (guarda referrer y utm en `meta`) |
| `seccion_vista` | `inicio`, `quienes`, `constancia`, `pago`, `garantia`, `resenas`, `preguntas`, `contacto` |
| `pregunta_abierta` | `previos`, `en-linea`, `constancia-tiempo`, `renovacion`, `fraude` |
| `clic` | el `data-track` del elemento: `whatsapp:barra`, `whatsapp:cierre`, `whatsapp:flotante`, `aval:stps`, `verificador`, `politica-reembolso`… |

`visit_id` agrupa los eventos de una misma visita (dura lo que la pestaña). La IP se guarda como hash.

## Publicar

1. Cambiar `<meta name="hub-endpoint">` en `index.html` a la URL pública de route-generator.
2. En el `.env` de route-generator, `TRUST_HUB_ORIGINS=https://<dominio-del-hub>`.
3. Llenar `asesores.json`: `{ "clave": { "nombre": "Nombre Apellido", "whatsapp": "52…" } }`.
4. Subir la carpeta completa.

## Desarrollo

    php -S localhost:8080 -t trust-hub     # el sitio
    composer dev                           # el receptor, en :8000

## Pendientes de contenido

Todo el texto sale de academy.dpsys.com.mx (consultado el 2026-09-24). No se inventó nada, y
estos puntos quedan por confirmar:

- **Factura:** el sitio no publica cómo pedir un CFDI. La página solo dice «Pregúntale a tu asesor».
- **Razón social, RFC y domicilio con calle:** no están publicados, y en directorios de terceros
  aparecen tres domicilios distintos. Por eso solo se pone «Reynosa, Tamaulipas».
- **Cifras:** +80,000 ingenieros y +50 empresas son las del sitio público.
- **Avales:** van los seis del sitio, con sus mismos rótulos y documentos. Ver la nota de la sesión
  del 2026-09-24 sobre lo que dice cada documento.
