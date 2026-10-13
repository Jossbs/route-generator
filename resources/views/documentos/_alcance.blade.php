{{--
    Lo que abre la membresía, más allá de la ruta.

    No es la capa de inventario —esa solo la lleva el documento anual, con sus
    cursos— sino las dos cifras que docs/ARQUITECTURA.md §4.2 obliga a declarar con la
    contención dicha, y los nombres de las doce especializaciones. El gancho las
    necesita: un lead que ve catorce programas recomendados sin saber que su acceso
    son cincuenta y cinco no tiene con qué medir lo que se le está ofreciendo.

    Las especializaciones que ya están en su ruta van marcadas. Lo suyo primero, y
    el resto se lee como lo que viene incluido.
--}}
@if ($catalogo)
    @php
        $enLaRuta = array_map(fn (array $b): string => $b['especialidad'], $agrupados);
    @endphp
    <div class="alcance">
        <div class="cab">
            <h4>Lo que abre tu membresía</h4>
            <div class="c">
                <div><b>{{ count($catalogo['especialidades']) }}</b><span>Especializaciones</span></div>
                <div><b>{{ $catalogo['cursos'] }}</b><span>Cursos</span></div>
                <div><b>{{ $catalogo['programas'] }}</b><span>Programas</span></div>
            </div>
        </div>

        @if ($copy->body('membresia.alcance'))
            <p>{{ $copy->body('membresia.alcance') }}</p>
        @endif

        <div class="chips">
            @foreach ($catalogo['especialidades'] as $nombre)
                <span class="{{ in_array($nombre, $enLaRuta, true) ? 'tuya' : '' }}">{{ $nombre }}</span>
            @endforeach
        </div>

        @if ($copy->body('conteo.contencion'))
            <div class="chips-pie">
                {{ $catalogo['cursos'] }} cursos · {{ $catalogo['programas'] }} programas de contenido distintos.
                {{ $copy->body('conteo.contencion') }}
            </div>
        @endif
    </div>
@endif
