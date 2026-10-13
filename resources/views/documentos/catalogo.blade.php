{{--
    El catálogo de cursos, decidido el 2026-09-25.

    Lo descarga el asesor desde la barra y se lo manda a cualquier lead: la lista
    completa con sus horas, sin recomendar nada. Tres partes:

     1. **Portada.** En la línea del hero del sitio: especialidades, cursos y
        horas —64 cursos con sus 55 programas (MODULE_DOUBLE_COUNT)— y lo que
        trae la membresía.
     2. **El catálogo.** El mismo inventario del anual, sin la marca «en tu plan»:
        cada especialización bajo su propio título (NO_HEADING_MERGE) y cada nivel
        absorbido colgando de su paquete, con «incluido» en vez de horas.
     3. **Siguiente paso.** La credencial y el WhatsApp de quien lo descargó. Sin
        la nota de Core Tools: el dueño la quitó el 2026-09-25.

    Sin precios (docs/ARQUITECTURA.md §4.5) y sin «lecciones»: cuantifica horas.
--}}
@php
    $folio = 1;
    $pad = fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
@endphp

<x-documento :titulo="$titulo">

    {{-- La portada en la línea del hero de academy.dpsys.com.mx. Solo lo que un
         lead necesita para dimensionar la membresía: especialidades, cursos,
         horas y qué trae. Los 64 cursos llevan al lado sus 55 programas, para
         que la cifra cuadre si el lead suma (MODULE_DOUBLE_COUNT). --}}
    <section class="hoja portada">
        <div class="fondo"></div>
        <img class="pt-agua" src="{{ asset('img/dpsys/logo-blanco.svg') }}" alt="" aria-hidden="true">

        <div class="inner ct-inner">
            <div class="ct-top">
                <img src="{{ asset('img/dpsys/logo-blanco.svg') }}" alt="Desarrollo Profesional SyS">
                <span>Membresía anual</span>
            </div>

            <span class="ct-eyebrow">Desarrollo Profesional SyS</span>
            <h1 class="ct-h1">Catálogo<span>de cursos</span></h1>

            @if ($copy->body('catalogo.portada'))
                <p class="ct-lead">{{ $copy->body('catalogo.portada') }}</p>
            @endif

            <div class="ct-cifras">
                <div>
                    <b>{{ $catalogo['especialidades'] }}</b>
                    <span>Especialidades</span>
                </div>
                <div>
                    <b>{{ $catalogo['cursos'] }}</b>
                    <span>Cursos</span>
                    <small>{{ $catalogo['programas'] }} programas distintos</small>
                </div>
                <div>
                    <b>{{ $catalogo['horas'] }}<i>h</i></b>
                    <span>De contenido</span>
                </div>
            </div>

            {{-- Cada especialidad con su propio nombre, nunca revueltas. --}}
            <span class="ct-k">Las especialidades</span>
            <ul class="ct-esp">
                @foreach ($especialidades as $especialidad)
                    <li>
                        <span class="n">{{ $especialidad['especialidad'] }}</span>
                        <span class="d">{{ $especialidad['total_cursos'] }}&nbsp;{{ $especialidad['total_cursos'] === 1 ? 'curso' : 'cursos' }} · {{ $especialidad['horas'] }}&nbsp;h</span>
                    </li>
                @endforeach
            </ul>

            @if ($membresia !== [])
                <div class="ct-membresia">
                    @foreach ($membresia as $tarjeta)
                        <div>
                            <b>{{ $tarjeta['titulo'] }}</b>
                            @if ($tarjeta['texto'] !== '')
                                <span>{{ $tarjeta['texto'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="ct-pie">
                <span>{{ $fecha }}</span>
                <span class="folio">01<i>/{{ str_pad((string) $paginas, 2, '0', STR_PAD_LEFT) }}</i></span>
            </div>
        </div>
    </section>

    @foreach ($hojas as $indice => $grupos)
        @php $folio++; @endphp
        <section class="hoja anual">
            @include('documentos._encabezado', [
                'seccion' => $indice === 0 ? 'El catálogo' : 'El catálogo ('.($indice + 1).')',
            ])

            <div class="cuerpo">
                @if ($indice === 0)
                    <div class="h-sec"><span class="kicker">{{ $catalogo['cursos'] }} cursos · {{ $catalogo['horas'] }} horas</span><h2>El catálogo</h2></div>

                    @if ($copy->body('catalogo.intro'))
                        <p class="entradilla">{{ $copy->body('catalogo.intro') }}</p>
                    @endif
                @endif

                <div class="inv">
                    @foreach ($grupos as $grupo)
                        <div class="inv-grupo">
                            <h5>{{ $grupo['especialidad'] }}<span class="dur bajo">{{ $grupo['programas'] }}&nbsp;{{ $grupo['programas'] === 1 ? 'programa' : 'programas' }} · {{ $grupo['horas'] }}&nbsp;h</span></h5>
                            <ul>
                                @foreach ($grupo['cursos'] as $curso)
                                    <li class="{{ $curso['horas_absorbidas'] ? 'sub' : '' }}">
                                        <span class="p">{{ $curso['horas_absorbidas'] ? '↳' : '○' }}</span>
                                        <span class="n">{{ $curso['nombre'] }}</span>
                                        <span class="h">{{ $curso['horas_absorbidas'] ? 'incluido' : ($curso['horas'] === null ? '— h' : $curso['horas'].' h') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                {{-- Las dos cifras del catálogo con su contención (docs/ARQUITECTURA.md §4.2). --}}
                @if ($loop->last)
                    <p class="inv-pie">
                        @if ($copy->body('catalogo.leyenda'))
                            {{ $copy->body('catalogo.leyenda') }}
                        @endif
                        {{ $catalogo['cursos'] }} cursos · {{ $catalogo['programas'] }} programas de contenido distintos.
                        @if ($copy->body('conteo.contencion'))
                            {{ $copy->body('conteo.contencion') }}
                        @endif
                    </p>
                @endif
            </div>

            @include('documentos._pie', ['folio' => $pad($folio)])
        </section>
    @endforeach

    @php $folio++; @endphp
    <section class="hoja anual">
        @include('documentos._encabezado', ['seccion' => 'Siguiente paso'])

        <div class="cuerpo cierre">
            <div class="h-sec"><span class="kicker">Cómo se estudia</span><h2>Lo que incluye cada curso</h2></div>

            @if ($copy->body('saber.modalidad'))
                <p class="entradilla">{{ $copy->body('saber.modalidad') }}</p>
            @endif

            @if ($copy->body('credencial.dc3'))
                <div class="tarjeta oscura">
                    <div class="cab-sello">
                        @include('documentos._sello')
                        <div>
                            <span class="kicker">Tu credencial</span>
                            <h4>Constancia DC-3 oficial ante la STPS</h4>
                            <p>{{ $copy->body('credencial.dc3') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Sin WhatsApp en el perfil del asesor, el botón no sale. --}}
            @if ($copy->body('catalogo.siguiente-paso') || $enlace)
                <div class="cta">
                    <span class="k">Siguiente paso</span>

                    @if ($copy->body('catalogo.siguiente-paso'))
                        <p class="txt">{{ $copy->body('catalogo.siguiente-paso') }}</p>
                    @endif

                    @if ($enlace && $copy->body('catalogo.boton-whatsapp'))
                        <div class="cta-botones">
                            <a class="pri" href="{{ $enlace }}">{{ $copy->body('catalogo.boton-whatsapp') }}</a>
                        </div>

                        {{-- Impreso, un enlace no se toca. El número va escrito. --}}
                        <p class="cta-tel">WhatsApp {{ $asesor['whatsapp'] }}</p>
                    @endif
                </div>
            @endif

            {{-- La firma es de la empresa, no del asesor (decidido el 2026-09-25):
                 el catálogo es igual para todos y lo que queda es el contacto
                 que publica el sitio. --}}
            <div class="firma">
                <img class="logo" src="{{ asset('img/dpsys/logo-vino.svg') }}" alt="">
                <div class="txt">
                    <div class="quien">Desarrollo Profesional SyS</div>
                    @if ($copy->body('catalogo.contacto'))
                        <div class="rol">{{ $copy->body('catalogo.contacto') }}</div>
                    @endif
                </div>
            </div>
        </div>

        @include('documentos._pie', ['folio' => $pad($folio)])
    </section>

</x-documento>
