import { CheckCircle2, Clock3, Flag, TrendingUp } from 'lucide-react';
import { ESTADOS } from '@/lib/kpis';
import { cn } from '@/lib/utils';
import type { Estado, MedidaTablero } from '@/types/kpis';

const ICONOS: Record<Estado, typeof CheckCircle2> = {
    logrado: CheckCircle2,
    en_ritmo: TrendingUp,
    cerca: Clock3,
    por_alcanzar: Flag,
    sin_meta: Flag,
};

/** El estado con icono y palabra: el color nunca va solo. */
export function EtiquetaEstado({
    estado,
    className,
}: {
    estado: Estado;
    className?: string;
}) {
    if (estado === 'sin_meta') {
        return null;
    }

    const Icono = ICONOS[estado];
    const { etiqueta, clase } = ESTADOS[estado];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 text-[13px] font-medium',
                clase,
                className,
            )}
        >
            <Icono
                className={cn('size-4', estado === 'cerca' && 'text-kpi-cerca')}
                aria-hidden
            />
            {etiqueta}
        </span>
    );
}

/**
 * La barra de avance con la marca de lo esperado a hoy: se ve de un vistazo si
 * lo que llevas va antes o después de esa raya.
 */
export function BarraAvance({
    porcentaje,
    esperado,
    estado,
    className,
}: {
    porcentaje: number | null;
    esperado?: number | null;
    estado: Estado;
    className?: string;
}) {
    const ancho = Math.min(100, Math.max(0, porcentaje ?? 0));

    return (
        <div className={cn('bg-muted relative h-2 rounded-full', className)}>
            <div
                className={cn(
                    'h-full rounded-full transition-[width] duration-500',
                    ESTADOS[estado].barra,
                )}
                style={{ width: `${ancho}%` }}
            />
            {esperado !== null &&
                esperado !== undefined &&
                esperado > 0 &&
                esperado < 100 && (
                    <span
                        className="bg-kpi-ritmo absolute -top-1 h-4 w-0.5 rounded-full"
                        style={{ left: `calc(${esperado}% - 1px)` }}
                        aria-hidden
                    />
                )}
        </div>
    );
}

type Props = {
    medida: MedidaTablero;
    seleccionada: boolean;
    onSeleccionar: () => void;
};

/**
 * Una medida en grande: lo que llevas, de cuánto, y la frase que dice qué sigue.
 * Al tocarla se ve su gráfica y su ranking debajo.
 */
export function TarjetaMedida({ medida, seleccionada, onSeleccionar }: Props) {
    const p = medida.progress;
    const esperado =
        p.target && p.expected !== null ? (p.expected / p.target) * 100 : null;

    return (
        <button
            type="button"
            onClick={onSeleccionar}
            aria-pressed={seleccionada}
            className={cn(
                'bg-card text-card-foreground focus-visible:ring-ring flex w-full flex-col gap-3 rounded-xl border p-4 text-left shadow-(--shadow-card) transition-colors outline-none focus-visible:ring-2 sm:p-5',
                seleccionada
                    ? 'border-primary'
                    : 'border-border hover:border-primary/40',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <span className="text-muted-foreground text-[14px] font-medium">
                    {medida.name}
                </span>
                {p.milestone !== null && p.milestone < 100 && (
                    <span className="bg-accent text-accent-foreground rounded-full px-2 py-0.5 text-[12px] font-semibold">
                        {p.milestone} %
                    </span>
                )}
            </div>

            <div className="flex flex-wrap items-baseline gap-x-2">
                <span className="text-[32px] leading-none font-semibold tracking-tight">
                    {p.value_label}
                </span>
                {p.target !== null && (
                    <span className="text-muted-foreground text-[15px]">
                        de {p.target_label}
                    </span>
                )}
            </div>

            {p.target !== null && (
                <BarraAvance
                    porcentaje={p.percent}
                    esperado={esperado}
                    estado={p.status}
                />
            )}

            <div className="flex flex-col gap-1">
                <EtiquetaEstado estado={p.status} />
                <p className="text-muted-foreground text-[14px] leading-snug">
                    {p.message}
                </p>
            </div>
        </button>
    );
}
