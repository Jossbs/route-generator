{{--
    La ruta final: el PDF que recibe quien ya cerró su membresía.

    Esta vista sigue la PDF de referencia Tu_ano_de_formacion_Rosbel_Salinas_DPSys.pdf, en hojas
    Carta: portada, mapa de los doce meses, membresía y «Empieza hoy». La
    información sigue saliendo del snapshot; aquí solo se decide cómo leerla en
    el papel.
--}}
@php
    $totales = $totalesItinerario;
    $membresia = $doc['membresia'] ?? [];
    $pad = fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $nombre = $membresia['full_name'] ?? $doc['lead']['nombre'];
    $ocupacion = $membresia['occupation'] ?? $doc['lead']['ocupacion'] ?? null;
    $vigenciaCorta = $vigenciaCorta ?? null;
    $hojasInventario = $hojasInventario ?? [];

    $nivel = [
        'basico' => 'Básico',
        'intermedio' => 'Intermedio',
        'avanzado' => 'Avanzado',
    ];

    $subtituloCurso = static function (array $curso) use ($nivel): string {
        if (($curso['continua'] ?? false) === true) {
            return ($curso['parte'] ?? 1) === ($curso['partes'] ?? 1)
                ? 'Continúa · lo terminas este mes'
                : 'Continúa · parte '.($curso['parte'] ?? 1).' de '.($curso['partes'] ?? 1);
        }

        $especialidad = trim((string) ($curso['especialidad'] ?? ''));
        $partes = (int) ($curso['partes'] ?? 1);

        if ($partes > 1) {
            return $especialidad.' · parte '.($curso['parte'] ?? 1).' de '.$partes;
        }

        $nivelCurso = $nivel[$curso['nivel'] ?? ''] ?? null;

        return trim($especialidad.($nivelCurso ? ' · '.$nivelCurso : ''));
    };

    $horasCurso = static function (array $curso): string {
        if (($curso['horas_absorbidas'] ?? false) === true) {
            return 'incluido';
        }

        return $curso['horas'] === null ? '— h' : $curso['horas'].' h';
    };
@endphp

<x-documento :reading-endpoint="$readingEndpoint ?? null" :titulo="$titulo" :imprimible="$imprimible">
    <div class="ruta-final-doc">
        {{-- ========================= hoja 1 · portada ========================= --}}
        <section class="hoja ruta-final-page rf-portada" data-reading-section="portada">
            <div class="rf-portada-cuerpo">
                @include('documentos._marca-anual')

                <div class="rf-para">Preparada para</div>
                <div class="rf-nom{{ mb_strlen($nombre) > 35 ? ' largo' : '' }}">{{ $nombre }}</div>
                @if ($ocupacion)
                    <div class="rf-puesto">{{ $ocupacion }}</div>
                @endif

                <span class="rf-activa">● Membresía activa</span>
                <h1>Tu ruta de<br><em>12 meses</em></h1>
                @if ($bajada)
                    <p class="rf-bajada">{{ $bajada }}</p>
                @endif

                <div class="rf-ficha">
                    <div><span>Plan</span><b>{{ $membresia['plan'] ?? 'Membresía anual' }}</b></div>
                    <div><span>Vigencia</span><b>{{ $vigenciaCorta ? $vigenciaCorta['inicio'].' – '.$vigenciaCorta['fin'] : '—' }}</b></div>
                    <div><span>Tu ritmo</span><b>{{ $horasSemana }} h por semana</b></div>
                    <div><span>En tu ruta</span><b>{{ $totales['cursos'] }} cursos · {{ $totales['horas_netas'] }} h</b></div>
                </div>

                @if ($primerCurso)
                    <div class="rf-arranque">
                        <i>1</i>
                        <div><span>Empiezas por</span><b>Mes 1 · {{ $primerCurso }}</b></div>
                    </div>
                @endif

                <div class="rf-firma-portada">
                    <span>Preparada por {{ $doc['asesor']['nombre'] ?? 'Desarrollo Profesional SyS' }} · {{ $fechaCorta }}</span>
                    <span>01 / {{ $pad($paginas) }}</span>
                </div>
            </div>
            <aside class="rf-vistazo">
                <div class="rf-eyebrow">Tu año de un vistazo</div>
                <h2>12 meses · 4 etapas</h2>
                <div class="rf-panales">
                    @foreach ($etapasRuta as $etapa)
                        <div class="rf-panal-fila">
                            <div class="rf-panal-etapa"><b>Etapa {{ $etapa['numero'] }}</b><span>Meses {{ $etapa['desde'] }}–{{ $etapa['hasta'] }}</span></div>
                            <div class="rf-panal-meses">
                                @foreach ($etapa['meses'] as $mes)
                                    <div class="rf-hex{{ $mes['numero'] === 1 ? ' primero' : '' }}"><div><b>{{ $mes['numero'] }}</b><span>{{ $mes['etiqueta_corta'] }}</span></div></div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="rf-resumen-etapas">
                    @foreach ($etapasRuta as $etapa)
                        <div><b>{{ $etapa['numero'] }}</b><span>{{ $etapa['nombre'] }}</span><strong>{{ $etapa['horas'] }} h<small>{{ $etapa['cursos'] }} cursos</small></strong></div>
                    @endforeach
                </div>
                <img class="rf-marca-agua" src="{{ asset('img/dpsys/logo-blanco.svg') }}" alt="">
                <div class="rf-vistazo-pie">◆ &nbsp; Mes 1 · empieza aquí</div>
            </aside>
        </section>

        {{-- Mapa mensual: cada hoja conserva su encabezado y su folio. --}}
        @foreach ($hojasMeses as $indiceHoja => $etapasHoja)
        @php
            $mesesHoja = collect($etapasHoja)->flatMap(fn (array $etapa): array => $etapa['meses']);
            $ultimoMapa = $indiceHoja === count($hojasMeses) - 1;
        @endphp
        <section class="hoja ruta-final-page rf-interior rf-mapa-pagina" data-reading-section="mapa">
            @include('documentos._encabezado-anual', ['seccion' => 'Meses '.$mesesHoja->first()['numero'].'–'.$mesesHoja->last()['numero'], 'activos' => $mesesHoja->pluck('numero')->all()])
            <div class="rf-eyebrow">{{ $indiceHoja === 0 ? 'Tu mapa del año' : 'Tu mapa del año · continuación' }}</div>
            <div class="rf-h2">Tu ruta, mes a mes</div>
            @if ($indiceHoja === 0)
            @if ($objetivo)
                <p class="rf-objetivo">{{ $objetivo }}</p>
            @endif

            <div class="rf-leer">
                <span><b>1</b>Sigue la línea de arriba abajo</span>
                <span><b>2</b>Cada mes dice qué cursos tomar</span>
                <span><b>3</b>Cada curso te da su DC-3</span>
            </div>

            @endif
            @foreach ($etapasHoja as $etapa)
                <div class="rf-etapa">
                    <span class="n">Etapa {{ $etapa['numero'] }}</span>
                    <b>{{ $etapa['nombre'] }}</b>
                    <span class="m">Meses {{ $etapa['desde'] }}–{{ $etapa['hasta'] }}</span>
                </div>

                <div class="rf-camino">
                    @foreach ($etapa['meses'] as $mes)
                        @php
                            $numeroMes = (int) $mes['numero'];
                            $esUltimo = $numeroMes === (int) $etapa['hasta'];
                            $libre = ($mes['cursos'] ?? []) === [];
                        @endphp
                        <div class="rf-mes{{ $numeroMes === 1 ? ' inicio' : '' }}{{ $esUltimo ? ' ultimo' : '' }}{{ $libre ? ' libre' : '' }}">
                            <div class="rf-nodo">{{ $numeroMes }}</div>
                            <div class="rf-mes-tarjeta">
                                <div class="rf-mes-cab">
                                    <b>Mes {{ $numeroMes }}{{ ($mes['continuacion'] ?? false) ? ' · continúa' : '' }}</b>
                                    <span class="cal">{{ $mes['etiqueta'] }}</span>
                                    @if ($numeroMes === 1 && ! ($mes['continuacion'] ?? false))
                                        <span class="rf-tag-aqui">Empieza aquí</span>
                                    @endif
                                    @if ($mes['horas'] > 0)
                                        <span class="carga">{{ $mes['horas'] }} h · ~{{ $mes['semanas'] }} h/sem</span>
                                    @else
                                        <span class="carga">Libre</span>
                                    @endif
                                </div>

                                @forelse ($mes['cursos'] as $curso)
                                    <div class="rf-curso{{ ($curso['continua'] ?? false) ? ' sigue' : '' }}">
                                        <div class="t">
                                            <b>{{ $curso['nombre'] }}</b>
                                            <span>{{ $subtituloCurso($curso) }}</span>
                                        </div>
                                        <span class="h">{{ ($curso['continua'] ?? false) ? 'sigue' : $horasCurso($curso) }}</span>
                                    </div>
                                @empty
                                    <div class="rf-libre-txt">Mes libre: pon al día lo pendiente y entrega tus proyectos finales para recibir tus constancias.</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            @if ($ultimoMapa)
            <div class="rf-meta">
                <div class="rf-meta-titulo">
                    <i>META</i>
                    <b>Al terminar tu año</b>
                </div>
                <div class="rf-meta-cifras">
                    <div><strong>{{ $totales['cursos'] }}</strong><span>cursos</span></div>
                    <div><strong>{{ $totales['horas_netas'] }}</strong><span>horas</span></div>
                    <div><strong>{{ $totales['cursos'] }}</strong><span>constancias DC-3<br>ante la STPS</span></div>
                </div>
            </div>
            @if ($copy->body('cierre.ruta-anual'))
                <p class="rf-flex-nota">{{ $copy->body('cierre.ruta-anual') }}</p>
            @endif

            @endif
            <div class="pie"><span>Desarrollo Profesional SyS · academy.dpsys.com.mx</span><span>{{ $pad($indiceHoja + 2) }} / {{ $pad($paginas) }}{{ ! $ultimoMapa ? ' · sigue en la hoja '.($indiceHoja + 3) : '' }}</span></div>
        </section>
        @endforeach

        {{-- Catálogo de la membresía: hojas calculadas según su contenido. --}}
        @foreach ($hojasInventario as $indice => $grupos)
            <section class="hoja ruta-final-page rf-interior rf-inventario-pagina" data-reading-section="membresia">
                @include('documentos._encabezado-anual', ['seccion' => 'Tu membresía'.($indice > 0 ? ' ('.($indice + 1).')' : '')])
                @if ($indice === 0)
                    <div class="rf-eyebrow">Todo lo que tienes abierto</div>
                    <div class="rf-h2">Tu membresía</div>
                    @if ($copy->body('intro-inventario.ruta-anual'))
                        <p class="rf-intro">{{ $copy->body('intro-inventario.ruta-anual') }}</p>
                    @endif
                @endif

                @if ($indice === 0)
                    <div class="rf-leyenda rf-leyenda-arriba"><span><i class="p"></i>En tu ruta</span><span><i></i>Disponible</span><span>↳ Ya incluido en su curso Completo</span></div>
                @endif
                <div class="rf-inv">
                    @foreach ($grupos as $grupo)
                        <div class="rf-inv-grupo">
                            <h3><span>{{ $grupo['especialidad'] }}</span><small>{{ $grupo['en_plan'] }} de {{ $grupo['programas'] }} en tu ruta</small></h3>
                            <ul>
                                @foreach ($grupo['cursos'] as $curso)
                                    @php
                                        $absorbido = (bool) ($curso['horas_absorbidas'] ?? false);
                                        $enPlan = (bool) ($curso['en_plan'] ?? false);
                                    @endphp
                                    <li class="{{ $enPlan ? 'plan' : '' }}{{ $absorbido ? ' sub' : '' }}">
                                        @if ($absorbido)
                                            <span class="rf-inv-arrow">↳</span>
                                        @else
                                            <span class="rf-inv-dot{{ $enPlan ? ' planned' : '' }}"></span>
                                        @endif
                                        <span class="rf-inv-name">{{ $curso['nombre'] }}</span>
                                        <span class="x">{{ $absorbido ? 'incluido' : ($curso['horas'] === null ? '— h' : $curso['horas'].' h') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="rf-leyenda">
                    <span><i class="p"></i>En tu ruta</span>
                    <span><i></i>Disponible</span>
                    <span>↳ Ya incluido en su curso Completo</span>
                </div>

                <div class="pie"><span>Desarrollo Profesional SyS · academy.dpsys.com.mx</span><span>{{ $pad(2 + count($hojasMeses) + $indice) }} / {{ $pad($paginas) }}</span></div>
            </section>
        @endforeach

        {{-- Cierre · empieza hoy --}}
        <section class="hoja ruta-final-page rf-interior rf-cierre" data-reading-section="empieza-hoy">
            @include('documentos._encabezado-anual', ['seccion' => 'Para empezar'])
            <div class="rf-eyebrow">Tu primera semana</div>
            <div class="rf-h2">Empieza hoy</div>
            @if ($vigencia)
                <p class="rf-intro">Tu acceso va del {{ $vigencia['inicio'] }} al {{ $vigencia['fin'] }}.</p>
            @endif

            @php
                $pasos = array_values(array_filter([
                    $copy->body('uso.paso-1'),
                    $primerCurso ? $copy->body('uso.paso-2', ['primer_curso' => $primerCurso]) : null,
                    $copy->body('uso.paso-3'),
                ]));
            @endphp
            @if ($pasos !== [])
                <div class="rf-pasos">
                    @foreach ($pasos as $paso)
                        <div>
                            <b>{{ $loop->iteration }}</b>
                            <span>{{ $paso }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($copy->body('credencial.dc3'))
                <div class="rf-dc3"><div class="sello">DC-3<small>STPS</small></div><div><h3>Tu credencial</h3><p>{{ $copy->body('credencial.dc3') }}</p></div></div>
            @endif

            @if (! empty($doc['asesor']['whatsapp']) && $copy->body('asesor.contacto-anual'))
                <div class="rf-asesor">
                    <h4>Tu asesor</h4>
                    <p>{{ $copy->body('asesor.contacto-anual') }}</p>
                    <span class="tel">{{ $doc['asesor']['nombre'] }} · WhatsApp {{ $doc['asesor']['whatsapp'] }}</span>
                </div>
            @endif

            <div class="rf-cierre-marca">@include('documentos._marca-anual')<span>academy.dpsys.com.mx</span></div>
            <div class="pie"><span>Desarrollo Profesional SyS · academy.dpsys.com.mx</span><span>{{ $pad($paginas) }} / {{ $pad($paginas) }}</span></div>
        </section>
    </div>
</x-documento>
