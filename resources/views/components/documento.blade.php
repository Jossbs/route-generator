{{--
    El armazón de los dos documentos.

    Dos medios, una sola plantilla: en pantalla angosta los bloques se apilan
    —así lo abre el lead desde WhatsApp, casi siempre en un teléfono— y al
    imprimir salen las hojas de 216 × 279 mm con su encabezado y su folio.

    Todo lo que se pinta sale de `$doc`, que es el snapshot congelado. Nada se
    consulta al catálogo: el documento que el lead recibió en septiembre tiene que
    seguir diciendo lo mismo en enero.
--}}
@props(['titulo', 'imprimible' => false])
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $titulo }}</title>
@include('documentos._fuentes')
@include('documentos._estilos')
</head>
<body>
@if ($imprimible)
    <div class="barra no-print">
        <span class="tit">{{ $titulo }}</span>
        <span class="sep"></span>
        <span class="aviso">Descarga con Imprimir → Guardar como PDF. Tamaño Carta, sin encabezados del navegador.</span>
        <button class="pri" onclick="window.print()">Descargar PDF</button>
    </div>
@endif

<main class="doc">
    {{ $slot }}
</main>
</body>
</html>
