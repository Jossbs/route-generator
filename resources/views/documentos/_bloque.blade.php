{{--
    Una especialización, bajo su propio título y con su propia cuenta.

    Dos especializaciones pueden compartir una hoja o un mes, jamás un encabezado:
    es `NO_HEADING_MERGE`, y por eso el bloque es la unidad indivisible con la que
    trabaja la paginación.

    `$numeracion` decide qué lleva la columna de la izquierda: el consecutivo de la
    ruta en el gancho, el mes sugerido en el itinerario anual.

    La duración va en la misma píldora que las de los cursos y las del inventario.
    Antes cada sitio la escribía a su manera —versalitas con interletraje aquí,
    minúsculas allá— y en el PDF las tres se leían como tres formatos distintos del
    mismo dato.
--}}
@php
    $numeracion ??= 'orden';
@endphp
<div class="bloque" data-ruta-tipo="{{ $bloque['rol'] === 'complementaria' ? 'refuerzo' : 'principal' }}">
    <div class="bl-enc">
        <span class="bl-barra"></span>

        <div class="bl-tit">
            <h3>{{ $bloque['especialidad'] }}</h3>
            @if ($bloque['rol_etiqueta'])
                <span class="bl-rol {{ $bloque['rol'] === 'ancla' ? 'ancla' : 'compl' }}">{{ $bloque['rol_etiqueta'] }}</span>
            @endif
        </div>

        <span class="dur">{{ $bloque['programas'] }}&nbsp;{{ $bloque['programas'] === 1 ? 'programa' : 'programas' }} · {{ $bloque['horas'] }}&nbsp;h</span>
    </div>

    <div class="cursos">
        @foreach ($bloque['cursos'] as $curso)
            @include('documentos._curso', [
                'curso' => $curso,
                // El punto de partida de la ruta. Sin él todos los cursos pesaban
                // igual y el lead no sabía por dónde entrar.
                'primero' => $numeracion === 'orden' && (int) ($curso['orden'] ?? 0) === 1,
                'marca' => $numeracion === 'mes'
                    ? ($curso['mes_sugerido'] ? 'M'.$curso['mes_sugerido'] : '—')
                    : str_pad((string) $curso['orden'], 2, '0', STR_PAD_LEFT),
            ])
        @endforeach
    </div>
</div>
