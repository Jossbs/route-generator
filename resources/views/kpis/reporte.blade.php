{{--
    El reporte de KPIs de un periodo, en PDF.

    Es un documento interno: lo lee dirección o el equipo en una junta, no un lead.
    Por eso usa la marca con sobriedad —el logo, el vino de los encabezados, Poppins
    para leer— y ningún recurso de persuasión.

    Cada medida lleva su avance contra la meta y el ranking con la meta de cada
    quien. Los estados se escriben en palabras («en ritmo», «por alcanzar»): el
    papel no tiene tooltips y el color solo no alcanza.
--}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>KPIs · {{ $periodo->label() }}</title>
@include('documentos._fuentes')
<style>
    @page { size: Letter; margin: 14mm 14mm 16mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Poppins', sans-serif; color: #22202b; font-size: 10pt; line-height: 1.45; }
    header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #880e4f; padding-bottom: 10px; margin-bottom: 18px; }
    header img { height: 34px; }
    h1 { font-family: 'Antonio', sans-serif; font-weight: 600; font-size: 22pt; margin: 0; color: #2a0820; letter-spacing: .01em; }
    .meta { text-align: right; color: #6b6577; font-size: 9pt; }
    .kpi { margin-bottom: 18px; break-inside: avoid; }
    .kpi h2 { font-family: 'Antonio', sans-serif; font-weight: 600; font-size: 14pt; color: #880e4f; margin: 0 0 6px; }
    .medida { border: 1px solid #e4e0ea; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; break-inside: avoid; }
    .fila { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
    .nombre { font-weight: 600; }
    .valor { font-size: 15pt; font-weight: 600; }
    .de { color: #6b6577; }
    .barra { height: 6px; background: #f1edf4; border-radius: 3px; margin: 6px 0 4px; overflow: hidden; }
    .barra span { display: block; height: 100%; background: #880e4f; }
    .mensaje { color: #6b6577; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9pt; }
    th { text-align: left; color: #6b6577; font-weight: 500; border-bottom: 1px solid #e4e0ea; padding: 3px 4px; }
    td { padding: 3px 4px; border-bottom: 1px solid #f1edf4; }
    td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; }
    .vacio { color: #6b6577; font-style: italic; }
    footer { margin-top: 20px; color: #6b6577; font-size: 8pt; }
</style>
</head>
<body>
    <header>
        <div>
            <h1>KPIs de ventas</h1>
            <div>{{ ucfirst($periodo->label()) }} · {{ $alcance }}</div>
        </div>
        <div class="meta">
            <img src="{{ asset('img/dpsys/logo-vino.svg') }}" alt="DP SyS"><br>
            Generado el {{ $generado }}
        </div>
    </header>

    @forelse ($kpis as $kpi)
        <section class="kpi">
            <h2>{{ $kpi['name'] }}</h2>

            @foreach ($kpi['measures'] as $medida)
                @php($p = $medida['progress'])
                <div class="medida">
                    <div class="fila">
                        <span class="nombre">{{ $medida['name'] }}</span>
                        <span>
                            <span class="valor">{{ $p['value_label'] }}</span>
                            @if ($p['target'] !== null)
                                <span class="de">de {{ $p['target_label'] }} · {{ (int) round($p['percent'] ?? 0) }} %</span>
                            @endif
                        </span>
                    </div>
                    @if ($p['target'] !== null)
                        <div class="barra"><span style="width: {{ min(100, max(0, $p['percent'] ?? 0)) }}%"></span></div>
                    @endif
                    <div class="mensaje">{{ $p['message'] }}</div>

                    @if ($medida['ranking'] !== [])
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Asesor</th>
                                    <th>Equipo</th>
                                    <th class="n">Valor</th>
                                    <th class="n">Meta</th>
                                    <th class="n">Avance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($medida['ranking'] as $fila)
                                    <tr>
                                        <td>{{ $fila['position'] }}</td>
                                        <td>{{ $fila['name'] }}</td>
                                        <td>{{ $fila['team'] ?? '—' }}</td>
                                        <td class="n">{{ $fila['value_label'] }}</td>
                                        <td class="n">{{ $fila['target_label'] }}</td>
                                        <td class="n">{{ $fila['percent'] === null ? '—' : (int) round($fila['percent']).' %' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        </section>
    @empty
        <p class="vacio">Todavía no hay KPIs configurados.</p>
    @endforelse

    <footer>
        Los números cuentan solo eventos confirmados y sin anular. La meta de la semana y del día sale de la del mes, repartida por día natural.
    </footer>
</body>
</html>
