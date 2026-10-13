{{--
    La ruta final: el PDF que recibe quien ya cerró su membresía.

    Esta vista sigue la plantilla de referencia ruta-final-sys.html, en hojas
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
            return 'Continúa · lo terminas este mes';
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

<x-documento :titulo="$titulo" :imprimible="$imprimible">
    <div class="ruta-final-doc">
        {{-- ========================= hoja 1 · portada ========================= --}}
        <section class="hoja ruta-final-page rf-portada">
            <div class="rf-barra"></div>
            <div class="rf-portada-cuerpo">
                <div class="rf-top">
                    <div class="rf-marca">
                        <svg class="rf-logo-sys" width="58" height="64" viewBox="0 0 100 110" aria-label="Logo SyS">
                            <polygon class="rf-logo-hx" points="50,6 91,29.5 91,80.5 50,104 9,80.5 9,29.5"/>
                            <polyline class="rf-logo-in" points="23,46 23,37 50,21.5 77,37 77,46"/>
                            <polyline class="rf-logo-in" points="23,64 23,73 50,88.5 77,73 77,64"/>
                            <text x="50" y="66" text-anchor="middle" font-size="34" transform="rotate(-8 50 58)">SyS</text>
                        </svg>
                        <span>Desarrollo<br>Profesional SyS</span>
                    </div>
                    <span class="rf-activa">Membresía activa</span>
                </div>

                <div class="rf-para">Preparada para</div>
                <div class="rf-nom">{{ $nombre }}</div>
                @if ($ocupacion)
                    <div class="rf-puesto">{{ $ocupacion }}</div>
                @endif

                <h1>Tu ruta de<br>12 meses</h1>
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
                    <span>01/{{ $pad($paginas) }}</span>
                </div>
            </div>
        </section>

        {{-- ====================== hoja 2 · meses 1–6 ======================= --}}
        @foreach ($hojasMeses as $indiceHoja => $etapasHoja)
        @php
            $mesesHoja = collect($etapasHoja)->flatMap(fn (array $etapa): array => $etapa['meses']);
            $ultimoMapa = $indiceHoja === count($hojasMeses) - 1;
        @endphp
        <section class="hoja ruta-final-page rf-interior rf-mapa-pagina">
            <div class="enc"><span>DP SyS · Tu ruta de 12 meses</span><span>Meses {{ $mesesHoja->first()['numero'] }}–{{ $mesesHoja->last()['numero'] }}</span></div>
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
                            <div>
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
                <i>META</i>
                <div>
                    <b>Al terminar tu año</b>
                    <span>{{ $totales['cursos'] }} cursos · {{ $totales['horas_netas'] }} horas · {{ $totales['cursos'] }} constancias DC-3 ante la STPS</span>
                </div>
            </div>
            @if ($copy->body('cierre.ruta-anual'))
                <p class="rf-flex-nota">{{ $copy->body('cierre.ruta-anual') }}</p>
            @endif

            @endif
            <div class="pie"><span>Desarrollo Profesional SyS</span><span>{{ $pad($indiceHoja + 2) }}/{{ $pad($paginas) }}{{ ! $ultimoMapa ? ' · sigue en la hoja '.($indiceHoja + 3) : '' }}</span></div>
        </section>
        @endforeach

        {{-- ======================== hoja 4 · inventario ====================== --}}
        @foreach ($hojasInventario as $indice => $grupos)
            <section class="hoja ruta-final-page rf-interior rf-inventario-pagina">
                <div class="enc"><span>DP SyS · Tu ruta de 12 meses</span><span>Tu membresía{{ $indice > 0 ? ' ('.($indice + 1).')' : '' }}</span></div>
                @if ($indice === 0)
                    <div class="rf-eyebrow">Todo lo que tienes abierto</div>
                    <div class="rf-h2">Tu membresía</div>
                    @if ($copy->body('intro-inventario.ruta-anual'))
                        <p class="rf-intro">{{ $copy->body('intro-inventario.ruta-anual') }}</p>
                    @endif
                @endif

                <div class="rf-inv">
                    @foreach ($grupos as $grupo)
                        <div class="rf-inv-grupo">
                            <h3>{{ $grupo['especialidad'] }}</h3>
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

                @if ($loop->last)
                    <div class="rf-leyenda">
                        <span><i class="p"></i>En tu ruta</span>
                        <span><i></i>Disponible</span>
                        <span>↳ Ya incluido en su curso Completo</span>
                    </div>
                @endif

                <div class="pie"><span>Desarrollo Profesional SyS</span><span>{{ $pad(2 + count($hojasMeses) + $indice) }}/{{ $pad($paginas) }}</span></div>
            </section>
        @endforeach

        {{-- ====================== hoja 5 · empieza hoy ======================= --}}
        <section class="hoja ruta-final-page rf-interior rf-cierre">
            <div class="enc"><span>DP SyS · Tu ruta de 12 meses</span><span>Para empezar</span></div>
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
                <div class="rf-dc3"><div class="sello">DC-3</div><p>{{ $copy->body('credencial.dc3') }}</p></div>
            @endif

            @if (! empty($doc['asesor']['whatsapp']) && $copy->body('asesor.contacto-anual'))
                <div class="rf-asesor">
                    <h4>Tu asesor</h4>
                    <p>{{ $copy->body('asesor.contacto-anual') }}</p>
                    <span class="tel">{{ $doc['asesor']['nombre'] }} · WhatsApp {{ $doc['asesor']['whatsapp'] }}</span>
                </div>
            @endif

            @if (! empty($doc['asesor']))
                <div class="rf-firma-asesor">
                    <i>{{ $iniciales }}</i>
                    <div><b>{{ $doc['asesor']['nombre'] }}</b><span>Desarrollo Profesional SyS</span></div>
                </div>
            @endif

            <div class="pie"><span>Desarrollo Profesional SyS</span><span>{{ $pad($paginas) }}/{{ $pad($paginas) }}</span></div>
        </section>
    </div>
</x-documento>
