{{--
    La ruta enfocada: el gancho.

    Se abre desde WhatsApp, casi siempre en un teléfono. Lleva una sola capa —el
    itinerario recomendado— y se arma entera desde el snapshot.

    Portada, recomendación, prueba social y cierre con acceso a la membresía.
    El itinerario continúa en hojas adicionales cuando hace falta.

    Estructura de referencia:

     1. **Portada.** El nombre de la ruta por la que preguntó el lead, lo que
        contó devuelto con sus palabras y las tres cifras de su ruta. Las cifras
        se dicen aquí y en ningún otro lado.
     2. **Tu ruta.** El itinerario, con el primer curso marcado. Si no cabe, sigue
        en otra hoja con su encabezado, nunca en una hoja huérfana.
     3. **Quiénes somos.** Lo que publica academy.dpsys.com.mx: cifras, empresas,
        fotos de capacitación presencial y reseñas de Google.
     4. **Siguiente paso.** Lo que el lead debe saber —modalidad, DC-3, lo que abre
        la membresía— y la acción, que devuelve la conversación al asesor.

    No imprime precios. El valor se cuantifica en programas, horas y constancias
    DC-3; el precio lo dice el asesor en la conversación (docs/ARQUITECTURA.md §4.5).
--}}
@php
    $ruta = $doc['ruta'];
    $totales = $ruta['totales'];
    $folio = 1;
    $pad = fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
@endphp

<x-documento :titulo="$titulo" :imprimible="$imprimible" :inicial="true">
<div class="ruta-inicial-doc">

    <section class="hoja portada">
        <div class="fondo"></div>
        <img class="pt-agua" src="{{ asset('img/dpsys/logo-blanco.svg') }}" alt="" aria-hidden="true">
        <div class="inner">
            <div>
                <img class="pt-logo" src="{{ asset('img/dpsys/logo-blanco.svg') }}" alt="Desarrollo Profesional SyS">
                <div class="pt-tag">Capacitación para tu futuro</div>
            </div>

            <div style="margin-top:auto">
                <span class="pt-eyebrow">Ruta principal personalizada</span>
                <p class="pt-para">Preparada para</p>
                <p class="pt-nombre">{{ $doc['lead']['nombre'] }}</p>

                <div class="pt-regla"></div>

                <h1 class="pt-h1">{{ $tituloPortada }}
                    @if ($bajada)
                        <span class="fina">{{ $bajada }}</span>
                    @endif
                </h1>

                @include('documentos._porque')

                {{-- Las tres cifras de la ruta. Antes se repetían en el cierre y en
                     el pie de cada hoja; ahora se dicen una vez, aquí. --}}
                <div class="pt-cifras">
                    <div><b>{{ $totales['programas'] }}</b><span>{{ $totales['programas'] === 1 ? 'Programa' : 'Programas' }}</span></div>
                    <div><b>{{ $totales['horas_netas'] }}<i>h</i></b><span>De contenido</span></div>
                    <div><b>{{ $totales['cursos'] }}</b><span>{{ $totales['cursos'] === 1 ? 'Constancia DC-3' : 'Constancias DC-3' }}</span></div>
                </div>

                <div class="pt-pie">
                    <span class="folio">01<i>/{{ $pad($paginas) }}</i></span>
                    <span>{{ $fecha }}</span>
                    <span class="der">Preparada por DPSYS</span>
                </div>
            </div>
        </div>
    </section>

    @foreach ($hojasRuta as $indice => $bloques)
        @php $folio++; @endphp
        <section class="hoja inicial-ruta">
            @include('documentos._encabezado', [
                'seccion' => $indice === 0 ? 'Tu ruta' : 'Tu ruta ('.($indice + 1).')',
            ])

            <div class="cuerpo">
                @if ($indice === 0)
                    <div class="h-sec"><span class="kicker">Paso a paso</span><h2>Lo que te recomiendo</h2></div>

                    {{-- La ruta consultada y el propósito del refuerzo, sin exigir grado o cargo. --}}
                    @if ($objetivo)
                        <div class="objetivo">
                            <span class="k">Tu objetivo</span>
                            <p>{{ $objetivo }}</p>
                        </div>
                    @elseif ($copy->body('intro-especializacion.generico'))
                        <p class="entradilla">{{ $copy->body('intro-especializacion.generico') }}</p>
                    @endif
                @endif

                <div class="ruta-lista">
                    @php $seccionAnterior = null; @endphp
                    @foreach ($bloques as $bloque)
                        @php $tipoBloque = $bloque['rol'] === 'complementaria' ? 'refuerzo' : 'principal'; @endphp
                        @if ($seccionAnterior !== $tipoBloque)
                            <div class="ruta-grupo">
                                <h4>{{ $tipoBloque === 'principal' ? 'Especialización principal' : 'Cursos adicionales de refuerzo' }}</h4>
                                @if ($tipoBloque === 'refuerzo')
                                    <p class="refuerzo-contexto">
                                        @if ($contextoRefuerzos)
                                            Tomamos en cuenta lo que nos compartiste: «{{ $contextoRefuerzos }}».
                                        @else
                                            Estos cursos complementan tu ruta principal con habilidades que puedes seguir desarrollando.
                                        @endif
                                    </p>
                                @endif
                            </div>
                        @endif
                        @php $seccionAnterior = $tipoBloque; $bloque['rol_etiqueta'] = null; @endphp
                        @include('documentos._bloque', ['bloque' => $bloque, 'numeracion' => 'orden'])
                    @endforeach

                </div>

                {{-- Cuánto le toma, al pie del itinerario que describe. --}}
                @if ($loop->last && $ritmo)
                    <p class="ritmo">{{ $ritmo }}</p>
                @endif
            </div>

            @include('documentos._pie', ['folio' => $pad($folio)])
        </section>
    @endforeach

    @if ($confianza)
        @php $folio++; @endphp
        @include('documentos._confianza', ['folio' => $pad($folio)])
    @endif

    @php $folio++; @endphp
    <section class="hoja inicial-cierre">
        @include('documentos._encabezado', ['seccion' => 'Siguiente paso'])

        <div class="cuerpo">
            <div class="h-sec"><span class="kicker">Lo que debes saber</span><h2>Tu siguiente paso</h2></div>

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

            @include('documentos._alcance')

            {{-- Dos intenciones, dos enlaces, cada uno con su mensaje precargado.
                 Ninguno imprime precio (docs/ARQUITECTURA.md §4.5). --}}
            @if ($copy->body('siguiente-paso.ruta-enfocada') || $enlaces)
                <div class="cta">
                    <span class="k">Siguiente paso</span>

                    @if ($copy->body('siguiente-paso.ruta-enfocada'))
                        <p class="txt">{{ $copy->body('siguiente-paso.ruta-enfocada') }}</p>
                    @endif

                    @if ($enlaces)
                        <div class="cta-botones">
                            @if (($enlaces['membresia'] ?? null) && $copy->body('cta.boton-membresia'))
                                <a class="pri" href="{{ $enlaces['membresia'] }}">{{ $copy->body('cta.boton-membresia') }}</a>
                            @endif
                            @if (($enlaces['conversacion'] ?? null) && $copy->body('cta.boton-whatsapp'))
                                <a href="{{ $enlaces['conversacion'] }}">{{ $copy->body('cta.boton-whatsapp') }}</a>
                            @endif
                        </div>

                        {{-- Impreso, un enlace no se toca. El número va escrito. --}}
                        <p class="cta-tel">WhatsApp {{ $doc['asesor']['whatsapp'] }}</p>
                    @endif
                </div>
            @endif

            <div class="firma">
                <img class="firma-logo" src="{{ asset('img/dpsys/logo-vino.svg') }}" alt="Desarrollo profesional SyS">
                <div class="txt">
                    <div class="quien">Desarrollo profesional SyS</div>
                </div>
            </div>
        </div>

        @include('documentos._pie', ['folio' => $pad($folio)])
    </section>

</div>
@include('documentos._paginacion-inicial')
</x-documento>
