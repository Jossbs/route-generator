{{--
    La ruta enfocada: el gancho.

    Se abre desde WhatsApp, casi siempre en un teléfono. Lleva una sola capa —el
    itinerario recomendado— y se arma entera desde el snapshot.

    Cuatro hojas, cada una con un solo trabajo (rediseño del 2026-09-24):

     1. **Portada.** El nombre del embudo que el lead vio en el anuncio, lo que
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

<x-documento :titulo="$titulo" :imprimible="$imprimible">

    <section class="hoja portada">
        <div class="fondo"></div>
        <img class="pt-agua" src="{{ asset('img/dpsys/logo-blanco.svg') }}" alt="" aria-hidden="true">
        <div class="inner">
            <div>
                <img class="pt-logo" src="{{ asset('img/dpsys/logo-blanco.svg') }}" alt="Desarrollo Profesional SyS">
                <div class="pt-tag">Capacitación para tu futuro</div>
            </div>

            <div style="margin-top:auto">
                <span class="pt-eyebrow">Tu ruta formativa</span>
                <p class="pt-para">Preparada para</p>
                <p class="pt-nombre">{{ $doc['lead']['nombre'] }}</p>
                @if (! empty($doc['lead']['ocupacion']))
                    <p class="pt-ocup">{{ $doc['lead']['ocupacion'] }}</p>
                @endif

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
                    @if ($doc['asesor'])
                        <span class="der">Preparada por {{ $doc['asesor']['nombre'] }}</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @foreach ($hojasRuta as $indice => $bloques)
        @php $folio++; @endphp
        <section class="hoja">
            @include('documentos._encabezado', [
                'seccion' => $indice === 0 ? 'Tu ruta' : 'Tu ruta ('.($indice + 1).')',
            ])

            <div class="cuerpo">
                @if ($indice === 0)
                    <div class="h-sec"><span class="kicker">Paso a paso</span><h2>Tu ruta</h2></div>

                    {{-- Su puesto y la especialización alrededor de la que se armó,
                         dichos antes de la primera lista. Sin ocupación capturada
                         entra la entradilla genérica: nada se inventa (docs/ARQUITECTURA.md §6). --}}
                    @if ($objetivo)
                        <div class="objetivo">
                            <span class="k">Tu objetivo</span>
                            <p>{{ $objetivo }}</p>
                        </div>
                    @elseif ($copy->body('intro-especializacion.generico'))
                        <p class="entradilla">{{ $copy->body('intro-especializacion.generico') }}</p>
                    @endif
                @endif

                @foreach ($bloques as $bloque)
                    @include('documentos._bloque', ['bloque' => $bloque, 'numeracion' => 'orden'])
                @endforeach

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
        <section class="hoja">
            @include('documentos._encabezado', ['seccion' => 'Quiénes somos'])

            <div class="cuerpo">
                <div class="h-sec"><span class="kicker">Quiénes somos</span><h2>Aprendes con SyS</h2></div>

                @if ($confianza['entradilla'])
                    <p class="entradilla">{{ $confianza['entradilla'] }}</p>
                @endif

                @if ($confianza['cifras'] !== [])
                    <div class="cifras inst">
                        @foreach ($confianza['cifras'] as $cifra)
                            <div><b>{{ $cifra['valor'] }}</b><span>{{ $cifra['etiqueta'] }}</span></div>
                        @endforeach
                    </div>
                @endif

                @if ($confianza['empresas'] !== [])
                    <h4 class="sub-sec">Empresas que confían en SyS</h4>
                    <div class="logos">
                        @foreach ($confianza['empresas'] as $empresa)
                            <span>
                                @if ($empresa['logo'])
                                    <img src="{{ $empresa['logo'] }}" alt="{{ $empresa['nombre'] }}">
                                @else
                                    {{ $empresa['nombre'] }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif

                @if ($confianza['fotos'] !== [])
                    <div class="fotos">
                        @foreach ($confianza['fotos'] as $foto)
                            <img src="{{ $foto['src'] }}" alt="Capacitación presencial en {{ $foto['nombre'] }}">
                        @endforeach
                    </div>
                    @if ($confianza['pieFotos'])
                        <p class="pie-fotos">{{ $confianza['pieFotos'] }}</p>
                    @endif
                @endif

                @if ($confianza['resenas'] !== [])
                    <h4 class="sub-sec">Lo que dicen quienes ya estudiaron con nosotros</h4>
                    <div class="resenas">
                        @foreach ($confianza['resenas'] as $resena)
                            <figure>
                                <blockquote>«{{ $resena['texto'] }}»</blockquote>
                                <figcaption>{{ $resena['autor'] }} · Reseña de Google</figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif
            </div>

            @include('documentos._pie', ['folio' => $pad($folio)])
        </section>
    @endif

    @php $folio++; @endphp
    <section class="hoja">
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

            {{-- Core Tools publica 4 de las 5 herramientas AIAG: decirlo es lo que
                 impide que el documento prometa un «Core Tools completo». La nota
                 enumera además lo que lleva **esta** ruta. --}}
            @if ($coreTools && $copy->body('nota.core-tools'))
                @php
                    $restoCoreTools = match (true) {
                        $coreTools['fuera'] === 0 => '',
                        $coreTools['fuera'] === 1 => '; el otro queda dentro de tu membresía',
                        default => '; los otros '.$coreTools['fuera'].' quedan dentro de tu membresía',
                    };
                @endphp
                <p class="nota">
                    {{ $copy->body('nota.core-tools') }}
                    En tu ruta van <b>{{ $coreTools['en_ruta'] }}</b>{{ $restoCoreTools }}.
                </p>
            @endif

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

            @if ($doc['asesor'])
                <div class="firma">
                    <div class="avatar">{{ $iniciales }}</div>
                    <div class="txt">
                        <div class="quien">{{ $doc['asesor']['nombre'] }}</div>
                        <div class="rol">Desarrollo Profesional SyS</div>
                    </div>
                </div>
            @endif
        </div>

        @include('documentos._pie', ['folio' => $pad($folio)])
    </section>

</x-documento>
