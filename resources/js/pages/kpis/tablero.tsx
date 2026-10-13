import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    FileDown,
    FileSpreadsheet,
    Flame,
    Lock,
    Trophy,
} from 'lucide-react';
import { useState } from 'react';
import { GraficaRitmo } from '@/components/kpis/grafica-ritmo';
import { NavegacionKpis } from '@/components/kpis/navegacion-kpis';
import {
    BarraAvance,
    EtiquetaEstado,
    TarjetaMedida,
} from '@/components/kpis/tarjeta-medida';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCargando } from '@/hooks/use-cargando';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import kpis from '@/routes/kpis';
import type {
    KpiTablero,
    MedidaTablero,
    OpcionSimple,
    Periodo,
} from '@/types/kpis';

type Props = {
    periodo: Periodo;
    anterior: string;
    siguiente: string;
    esActual: boolean;
    hoy: string;
    periodos: OpcionSimple[];
    alcance: string;
    alcanceNombre: string;
    alcances: (OpcionSimple & { group: string })[];
    kpis: KpiTablero[];
    mesCerrado: boolean;
    puedeAdministrar: boolean;
};

/**
 * El tablero de KPIs: el avance de cada medida en el periodo y el alcance
 * elegidos, su ritmo día por día y el ranking abierto (decidido el 2026-09-26).
 *
 * Se lee de arriba abajo: primero cuánto llevas contra la meta y qué te falta,
 * después la gráfica y el ranking de la medida que se toque. Nada en rojo: lo que
 * va atrás se dice como lo que falta por día.
 */
export default function Tablero(props: Props) {
    const { periodo, alcance, alcances, periodos, puedeAdministrar } = props;
    const cargando = useCargando();

    const ir = (cambios: Record<string, string>) =>
        router.get(
            kpis.dashboard().url,
            {
                periodo: periodo.type,
                fecha: periodo.start,
                alcance,
                ...cambios,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const consulta = new URLSearchParams({
        periodo: periodo.type,
        fecha: periodo.start,
        alcance,
    }).toString();

    const grupos = alcances.reduce<Record<string, typeof alcances>>(
        (acc, a) => {
            (acc[a.group] ??= []).push(a);

            return acc;
        },
        {},
    );

    return (
        <AppLayout>
            <Head title={`KPIs · ${periodo.label}`} />

            <NavegacionKpis puedeAdministrar={puedeAdministrar} />

            <Heading
                overline={`KPIs · ${props.alcanceNombre}`}
                title={periodo.label}
                className="mb-5 first-letter:uppercase"
            />

            <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap items-center gap-2">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={periodo.type}
                        onValueChange={(valor) =>
                            valor &&
                            ir({
                                periodo: valor,
                                fecha: props.esActual
                                    ? props.hoy
                                    : periodo.start,
                            })
                        }
                        aria-label="Periodo"
                    >
                        {periodos.map((p) => (
                            <ToggleGroupItem
                                key={p.value}
                                value={p.value}
                                className="px-3"
                            >
                                {p.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>

                    <div className="flex items-center">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => ir({ fecha: props.anterior })}
                            aria-label="Periodo anterior"
                        >
                            <ChevronLeft />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => ir({ fecha: props.siguiente })}
                            aria-label="Periodo siguiente"
                        >
                            <ChevronRight />
                        </Button>
                        {!props.esActual && (
                            <Button
                                variant="link"
                                size="sm"
                                className="px-2"
                                onClick={() => ir({ fecha: props.hoy })}
                            >
                                Ir a hoy
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={alcance}
                        onValueChange={(valor) => ir({ alcance: valor })}
                    >
                        <SelectTrigger
                            className="w-full sm:w-60"
                            aria-label="De quién son los números"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(grupos).map(([grupo, opciones]) => (
                                <SelectGroup key={grupo}>
                                    {grupo !== 'general' && (
                                        <SelectLabel>{grupo}</SelectLabel>
                                    )}
                                    {opciones.map((o) => (
                                        <SelectItem
                                            key={o.value}
                                            value={o.value}
                                        >
                                            {o.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={`${kpis.report.csv().url}?${consulta}`}
                            download
                        >
                            <FileSpreadsheet /> Excel
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={`${kpis.report.pdf().url}?${consulta}`}
                            download
                        >
                            <FileDown /> PDF
                        </a>
                    </Button>
                </div>
            </div>

            {props.mesCerrado && (
                <p className="bg-muted text-muted-foreground mb-6 flex items-center gap-2 rounded-lg px-4 py-3 text-[14px]">
                    <Lock className="size-4 shrink-0" />
                    Este mes ya está cerrado. Los números incluyen los ajustes
                    que se hicieron después del cierre.
                </p>
            )}

            <div
                className={cn(
                    'flex flex-col gap-10 transition-opacity',
                    cargando && 'opacity-60',
                )}
            >
                {props.kpis.length === 0 ? (
                    <SinKpis puedeAdministrar={puedeAdministrar} />
                ) : (
                    props.kpis.map((kpi) => (
                        <SeccionKpi
                            key={kpi.id}
                            kpi={kpi}
                            periodo={periodo}
                            alcance={alcance}
                        />
                    ))
                )}
            </div>
        </AppLayout>
    );
}

function SeccionKpi({
    kpi,
    periodo,
    alcance,
}: {
    kpi: KpiTablero;
    periodo: Periodo;
    alcance: string;
}) {
    const [seleccionada, setSeleccionada] = useState(kpi.measures[0]?.id);
    const medida =
        kpi.measures.find((m) => m.id === seleccionada) ?? kpi.measures[0];

    if (!medida) {
        return null;
    }

    return (
        <section aria-labelledby={`kpi-${kpi.id}`}>
            <div className="mb-3">
                <h3 id={`kpi-${kpi.id}`} className="text-xl font-semibold">
                    {kpi.name}
                </h3>
                {kpi.description && (
                    <p className="text-muted-foreground text-[14px]">
                        {kpi.description}
                    </p>
                )}
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {kpi.measures.map((m) => (
                    <TarjetaMedida
                        key={m.id}
                        medida={m}
                        seleccionada={
                            kpi.measures.length > 1 && m.id === medida.id
                        }
                        onSeleccionar={() => setSeleccionada(m.id)}
                    />
                ))}
            </div>

            <div className="bg-card border-border mt-3 grid grid-cols-1 gap-6 rounded-xl border p-4 shadow-(--shadow-card) sm:p-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                {periodo.type !== 'dia' && medida.series.length > 0 ? (
                    <div className="min-w-0">
                        <h4 className="mb-3 text-[15px] font-semibold">
                            {medida.name} día por día
                        </h4>
                        <GraficaRitmo
                            serie={medida.series}
                            unidad={medida.unit}
                            acumulable={medida.summable}
                            titulo={`${kpi.name} · ${medida.name}`}
                        />
                    </div>
                ) : (
                    <p className="text-muted-foreground self-center text-[14px]">
                        La gráfica por día aparece en la semana, el mes y el
                        trimestre.
                    </p>
                )}

                <Ranking medida={medida} alcance={alcance} />
            </div>
        </section>
    );
}

/**
 * El ranking abierto: todos ven a todos. Junto al lugar va lo que motiva sin
 * comparar —la racha de días y el mejor mes propio— para que quien va abajo en
 * la tabla también vea que avanza.
 */
function Ranking({
    medida,
    alcance,
}: {
    medida: MedidaTablero;
    alcance: string;
}) {
    const persona = alcance.startsWith('persona:')
        ? Number(alcance.split(':')[1])
        : null;

    if (medida.ranking.length === 0) {
        return (
            <div className="text-muted-foreground text-[14px]">
                <h4 className="text-foreground mb-2 text-[15px] font-semibold">
                    Ranking
                </h4>
                Aún no hay asesores dados de alta en este equipo ni ventas en el
                periodo.
            </div>
        );
    }

    return (
        <div className="min-w-0">
            <h4 className="mb-3 text-[15px] font-semibold">
                Ranking · {medida.name}
            </h4>
            <ol className="flex flex-col">
                {medida.ranking.map((fila) => (
                    <li
                        key={fila.id}
                        className={cn(
                            'border-border flex flex-col gap-1.5 border-t py-2.5 first:border-t-0',
                            persona === fila.id &&
                                'bg-accent -mx-2 rounded-lg px-2',
                        )}
                    >
                        <div className="flex items-baseline gap-3">
                            <span className="text-muted-foreground w-6 shrink-0 text-right text-[14px] tabular-nums">
                                {fila.position}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-[15px] font-medium">
                                    {fila.name}
                                </span>
                                {fila.team && (
                                    <span className="text-muted-foreground block truncate text-[12px]">
                                        {fila.team}
                                    </span>
                                )}
                            </span>
                            <span className="text-right">
                                <span className="block text-[15px] font-semibold tabular-nums">
                                    {fila.value_label}
                                </span>
                                {fila.target !== null && (
                                    <span className="text-muted-foreground block text-[12px] tabular-nums">
                                        de {fila.target_label}
                                    </span>
                                )}
                            </span>
                        </div>

                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 pl-9">
                            {fila.target !== null && (
                                <>
                                    <BarraAvance
                                        porcentaje={fila.percent}
                                        estado={fila.status}
                                        className="h-1.5 w-24"
                                    />
                                    <span className="text-muted-foreground text-[12px] tabular-nums">
                                        {Math.round(fila.percent ?? 0)} %
                                    </span>
                                    <EtiquetaEstado
                                        estado={fila.status}
                                        className="text-[12px]"
                                    />
                                </>
                            )}
                            {fila.streak >= 2 && (
                                <span
                                    className="text-muted-foreground inline-flex items-center gap-1 text-[12px]"
                                    title="Días seguidos con resultado"
                                >
                                    <Flame
                                        className="text-kpi-cerca size-3.5"
                                        aria-hidden
                                    />
                                    {fila.streak} días seguidos
                                </span>
                            )}
                            {fila.is_personal_best && (
                                <span className="text-kpi-bien-texto inline-flex items-center gap-1 text-[12px] font-medium">
                                    <Trophy className="size-3.5" aria-hidden />
                                    Su mejor mes
                                </span>
                            )}
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}

function SinKpis({ puedeAdministrar }: { puedeAdministrar: boolean }) {
    return (
        <div className="border-border rounded-xl border border-dashed p-8 text-center">
            <p className="mb-1 font-semibold">Todavía no hay KPIs activos.</p>
            <p className="text-muted-foreground mb-4 text-[14px]">
                Un KPI se arma con lo que se registra: ventas, cobros, leads
                atendidos.
            </p>
            {puedeAdministrar && (
                <Button asChild>
                    <Link href={kpis.settings().url}>Configurar KPIs</Link>
                </Button>
            )}
        </div>
    );
}
