import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, History } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { NavegacionKpis } from '@/components/kpis/navegacion-kpis';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatear } from '@/lib/kpis';
import { cn } from '@/lib/utils';
import kpis from '@/routes/kpis';
import type { Periodo, Unidad } from '@/types/kpis';

type Fila = {
    scope: 'general' | 'equipo' | 'persona';
    id: number | null;
    key: string;
    name: string;
    group: string;
    team?: string | null;
    months: (number | null)[];
    quarter: number | null;
    months_sum: number | null;
    months_sum_label: string | null;
};

type Props = {
    trimestre: Periodo;
    anterior: string;
    siguiente: string;
    meses: { start: string; label: string }[];
    medidas: {
        value: string;
        label: string;
        unit: Unidad;
        summable: boolean;
    }[];
    medida: string | null;
    filas: Fila[];
    historial: {
        id: number;
        who: string;
        period: string;
        value: string;
        reason: string | null;
        by: string | null;
        at: string | null;
        replaced: boolean;
    }[];
};

/** Una celda editable: `persona:12|mes|2026-07-01`. */
type Celdas = Record<string, string>;

const llave = (fila: Fila, tipo: 'mes' | 'trimestre', inicio: string) =>
    `${fila.key}|${tipo}|${inicio}`;
const texto = (v: number | null) => (v === null ? '' : String(v));

/**
 * Las metas de un trimestre para una medida, en una tabla: todo ventas, cada
 * equipo y cada asesor, por mes, y el trimestre.
 *
 * Los meses no tienen que ser parejos (10, 20, 30). El trimestre se deja vacío
 * para que valga la suma de sus meses, que aparece en gris; si se captura, manda.
 * La semana y el día se reparten solos por día natural.
 *
 * Guardar solo manda lo que cambió, con un motivo: cada cambio es una versión.
 */
export default function Metas(props: Props) {
    const { trimestre, meses, filas, medida } = props;
    const unidad =
        props.medidas.find((m) => m.value === medida)?.unit ?? 'piezas';
    const sumable =
        props.medidas.find((m) => m.value === medida)?.summable ?? true;

    const iniciales = useMemo(() => {
        const celdas: Celdas = {};

        for (const fila of filas) {
            meses.forEach(
                (m, i) =>
                    (celdas[llave(fila, 'mes', m.start)] = texto(
                        fila.months[i],
                    )),
            );
            celdas[llave(fila, 'trimestre', trimestre.start)] = texto(
                fila.quarter,
            );
        }

        return celdas;
    }, [filas, meses, trimestre.start]);

    const [celdas, setCeldas] = useState<Celdas>(iniciales);
    const [motivo, setMotivo] = useState('');
    const [guardando, setGuardando] = useState(false);

    useEffect(() => setCeldas(iniciales), [iniciales]);

    const cambiadas = Object.keys(celdas).filter(
        (k) => celdas[k] !== iniciales[k],
    );

    const ir = (cambios: Record<string, string>) =>
        router.get(
            kpis.targets.index().url,
            { fecha: trimestre.start, medida: medida ?? '', ...cambios },
            { preserveState: false, replace: true },
        );

    const guardar = () => {
        const filaPorClave = Object.fromEntries(filas.map((f) => [f.key, f]));

        router.post(
            kpis.targets.store.url(),
            {
                kpi_measure_id: medida,
                reason: motivo || null,
                cells: cambiadas.map((k) => {
                    const [clave, tipo, inicio] = k.split('|');
                    const fila = filaPorClave[clave];

                    return {
                        scope: fila.scope,
                        id: fila.id,
                        period_type: tipo,
                        period_start: inicio,
                        value: celdas[k] === '' ? null : celdas[k],
                    };
                }),
            },
            {
                preserveScroll: true,
                onStart: () => setGuardando(true),
                onFinish: () => setGuardando(false),
                onSuccess: () => setMotivo(''),
            },
        );
    };

    if (props.medidas.length === 0) {
        return (
            <AppLayout>
                <Head title="Metas" />
                <NavegacionKpis puedeAdministrar />
                <Heading overline="KPIs · Metas" title="Metas" />
                <p className="text-muted-foreground">
                    Aún no hay medidas con meta. Créalas en{' '}
                    <Link
                        href={kpis.settings().url}
                        className="text-primary underline underline-offset-4"
                    >
                        Configuración
                    </Link>
                    .
                </p>
            </AppLayout>
        );
    }

    let grupoAnterior = '';

    return (
        <AppLayout>
            <Head title="Metas" />
            <NavegacionKpis puedeAdministrar />

            <Heading
                overline="KPIs · Metas"
                title={trimestre.label}
                description="Los meses no tienen que ser parejos. La semana y el día se reparten solos por día natural."
                className="mb-5"
            />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => ir({ fecha: props.anterior })}
                        aria-label="Trimestre anterior"
                    >
                        <ChevronLeft />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => ir({ fecha: props.siguiente })}
                        aria-label="Trimestre siguiente"
                    >
                        <ChevronRight />
                    </Button>
                </div>
                <Select
                    value={medida ?? ''}
                    onValueChange={(v) => ir({ medida: v })}
                >
                    <SelectTrigger
                        className="w-full sm:w-80"
                        aria-label="Medida"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {props.medidas.map((m) => (
                            <SelectItem key={m.value} value={m.value}>
                                {m.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {!sumable && (
                <p className="bg-muted text-muted-foreground mb-4 rounded-lg px-4 py-3 text-[14px]">
                    Esta medida no se suma (es un promedio o una razón): su meta
                    es un nivel que vale para cada día del mes.
                </p>
            )}

            <div className="bg-card border-border overflow-x-auto rounded-xl border shadow-(--shadow-card)">
                <table className="w-full min-w-[640px] text-[14px]">
                    <thead>
                        <tr className="text-muted-foreground text-left">
                            <th className="px-4 py-3 font-medium">Quién</th>
                            {meses.map((m) => (
                                <th
                                    key={m.start}
                                    className="px-2 py-3 font-medium"
                                >
                                    {m.label}
                                </th>
                            ))}
                            <th className="px-2 py-3 font-medium">Trimestre</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map((fila) => {
                            const encabezado =
                                fila.group !== grupoAnterior &&
                                fila.group !== 'General';
                            grupoAnterior = fila.group;

                            return (
                                <FilaMetas
                                    key={fila.key}
                                    fila={fila}
                                    encabezado={encabezado ? fila.group : null}
                                    meses={meses}
                                    trimestre={trimestre.start}
                                    celdas={celdas}
                                    iniciales={iniciales}
                                    unidad={unidad}
                                    sumable={sumable}
                                    onCambio={(k, v) =>
                                        setCeldas((c) => ({ ...c, [k]: v }))
                                    }
                                />
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {filas.length === 1 && (
                <p className="text-muted-foreground mt-3 text-[14px]">
                    Para fijar metas por equipo o por asesor, da de alta los
                    equipos y a su gente en{' '}
                    <Link
                        href={kpis.settings().url}
                        className="text-primary underline underline-offset-4"
                    >
                        Configuración
                    </Link>
                    .
                </p>
            )}

            <div className="bg-card border-border sticky bottom-4 mt-4 flex flex-col gap-3 rounded-xl border p-3 shadow-(--shadow-pop) sm:flex-row sm:items-center">
                <Input
                    value={motivo}
                    onChange={(e) => setMotivo(e.target.value)}
                    placeholder="Motivo del cambio (opcional)"
                    aria-label="Motivo del cambio"
                    className="flex-1"
                />
                <Button
                    onClick={guardar}
                    disabled={cambiadas.length === 0 || guardando}
                >
                    {cambiadas.length === 0
                        ? 'Sin cambios'
                        : `Guardar ${cambiadas.length} ${cambiadas.length === 1 ? 'cambio' : 'cambios'}`}
                </Button>
            </div>

            {props.historial.length > 0 && (
                <section className="mt-10">
                    <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                        <History className="size-5" aria-hidden /> Historial de
                        esta medida
                    </h3>
                    <ul className="flex flex-col gap-2 text-[14px]">
                        {props.historial.map((h) => (
                            <li
                                key={h.id}
                                className={cn(
                                    'flex flex-wrap gap-x-2',
                                    h.replaced && 'text-muted-foreground',
                                )}
                            >
                                <span className="font-medium">{h.who}</span>
                                <span>· {h.period}:</span>
                                <span
                                    className={cn(
                                        'tabular-nums',
                                        h.replaced && 'line-through',
                                    )}
                                >
                                    {h.value}
                                </span>
                                {h.reason && (
                                    <span className="text-muted-foreground">
                                        — «{h.reason}»
                                    </span>
                                )}
                                <span className="text-muted-foreground">
                                    {h.by ?? 'Sistema'}
                                    {h.at &&
                                        `, ${new Date(h.at).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}`}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </AppLayout>
    );
}

function FilaMetas({
    fila,
    encabezado,
    meses,
    trimestre,
    celdas,
    iniciales,
    unidad,
    sumable,
    onCambio,
}: {
    fila: Fila;
    encabezado: string | null;
    meses: { start: string; label: string }[];
    trimestre: string;
    celdas: Celdas;
    iniciales: Celdas;
    unidad: Unidad;
    sumable: boolean;
    onCambio: (k: string, v: string) => void;
}) {
    // La suma de lo que hay en pantalla, no solo de lo guardado: se ve mientras se escribe.
    const suma = meses.reduce(
        (total, m) =>
            total + (Number(celdas[llave(fila, 'mes', m.start)]) || 0),
        0,
    );
    const hayMeses = meses.some(
        (m) => celdas[llave(fila, 'mes', m.start)] !== '',
    );

    const celda = (k: string, etiqueta: string, placeholder?: string) => (
        <td key={k} className="px-2 py-1.5">
            <Input
                type="number"
                min={0}
                step="any"
                inputMode="decimal"
                value={celdas[k] ?? ''}
                placeholder={placeholder}
                aria-label={etiqueta}
                onChange={(e) => onCambio(k, e.target.value)}
                className={cn(
                    'h-9 w-28 tabular-nums',
                    celdas[k] !== iniciales[k] && 'border-primary',
                )}
            />
        </td>
    );

    return (
        <>
            {encabezado && (
                <tr>
                    <th
                        colSpan={meses.length + 2}
                        className="text-muted-foreground bg-muted/50 px-4 py-2 text-left text-[12px] font-semibold tracking-wide uppercase"
                    >
                        {encabezado}
                    </th>
                </tr>
            )}
            <tr className="border-border border-t">
                <td className="px-4 py-1.5">
                    <span className="block font-medium">{fila.name}</span>
                    {fila.team && (
                        <span className="text-muted-foreground block text-[12px]">
                            {fila.team}
                        </span>
                    )}
                </td>
                {meses.map((m) =>
                    celda(
                        llave(fila, 'mes', m.start),
                        `${fila.name}, ${m.label}`,
                    ),
                )}
                {celda(
                    llave(fila, 'trimestre', trimestre),
                    `${fila.name}, trimestre`,
                    sumable && hayMeses
                        ? `= ${formatear(suma, unidad)}`
                        : undefined,
                )}
            </tr>
        </>
    );
}
