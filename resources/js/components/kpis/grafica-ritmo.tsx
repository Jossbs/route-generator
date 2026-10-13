import { useEffect, useMemo, useRef, useState } from 'react';
import { fechaCorta, fechaLarga, formatear, formatearCorto } from '@/lib/kpis';
import type { PuntoSerie, Unidad } from '@/types/kpis';

type Props = {
    serie: PuntoSerie[];
    unidad: Unidad;
    /** Si la medida se suma: entonces se pinta lo acumulado contra el ritmo. */
    acumulable: boolean;
    titulo: string;
};

const ALTO = 200;
const MARGEN = { arriba: 16, derecha: 64, abajo: 26, izquierda: 48 };

/** Un tope «redondo» para el eje: 1, 2, 2.5 o 5 por potencia de diez. */
function topeRedondo(valor: number): number {
    if (valor <= 0) {
        return 1;
    }

    const potencia = 10 ** Math.floor(Math.log10(valor));

    for (const paso of [1, 2, 2.5, 5, 10]) {
        if (paso * potencia >= valor) {
            return paso * potencia;
        }
    }

    return 10 * potencia;
}

/**
 * Lo que se lleva contra el ritmo que llega a la meta, día por día.
 *
 * Es la gráfica que motiva sin desmeritar: no compara contra la meta completa
 * —que el día 5 siempre se ve lejos— sino contra lo esperado a cada día. Si la
 * línea rosa va por encima de la morada, vas en ritmo.
 *
 * Dos series con leyenda de líneas; la del ritmo va punteada para que no dependa
 * del color. Una retícula que busca el día más cercano al cursor lleva el
 * tooltip, y la tabla por día queda debajo para quien no usa el ratón.
 *
 * Una medida que no se suma —un promedio, una conversión— no acumula: se pinta
 * su valor de cada día, sin ritmo.
 */
export function GraficaRitmo({ serie, unidad, acumulable, titulo }: Props) {
    const contenedor = useRef<HTMLDivElement>(null);
    const [ancho, setAncho] = useState(320);
    const [activo, setActivo] = useState<number | null>(null);

    useEffect(() => {
        const nodo = contenedor.current;

        if (!nodo) {
            return;
        }

        const observador = new ResizeObserver(([entrada]) =>
            setAncho(Math.max(280, entrada.contentRect.width)),
        );
        observador.observe(nodo);

        return () => observador.disconnect();
    }, []);

    const real = serie.map((p) => (acumulable ? p.cumulative : p.value));
    const ritmo = acumulable ? serie.map((p) => p.expected) : [];
    const hayRitmo = ritmo.some((v) => v !== null);

    const maximo = useMemo(
        () =>
            topeRedondo(
                Math.max(
                    0,
                    ...real.filter((v): v is number => v !== null),
                    ...ritmo.filter((v): v is number => v !== null),
                ) * 1.05,
            ),
        [serie, acumulable],
    );

    const anchoUtil = ancho - MARGEN.izquierda - MARGEN.derecha;
    const altoUtil = ALTO - MARGEN.arriba - MARGEN.abajo;
    const x = (i: number) =>
        MARGEN.izquierda +
        (serie.length <= 1
            ? anchoUtil / 2
            : (i * anchoUtil) / (serie.length - 1));
    const y = (v: number) => MARGEN.arriba + altoUtil - (v / maximo) * altoUtil;

    const trazo = (valores: (number | null)[]) =>
        valores
            .map((v, i) => (v === null ? null : `${x(i)},${y(v)}`))
            .reduce<string[]>((partes, punto, i, todos) => {
                if (punto === null) {
                    return partes;
                }

                const empieza = i === 0 || todos[i - 1] === null;

                partes.push(`${empieza ? 'M' : 'L'}${punto}`);

                return partes;
            }, [])
            .join(' ');

    const ultimoReal = real.reduce<number>(
        (ultimo, v, i) => (v !== null ? i : ultimo),
        -1,
    );
    const area =
        ultimoReal >= 0
            ? `${trazo(real.slice(0, ultimoReal + 1))} L${x(ultimoReal)},${y(0)} L${x(0)},${y(0)} Z`
            : '';

    const marcas = [0, 0.25, 0.5, 0.75, 1].map((f) => f * maximo);
    // Pocas etiquetas de fecha: la primera, la última y unas intermedias.
    const cadaCuanto = Math.max(
        1,
        Math.ceil(serie.length / Math.max(2, Math.floor(anchoUtil / 70))),
    );

    const moverA = (clientX: number) => {
        const caja = contenedor.current?.getBoundingClientRect();

        if (!caja || serie.length === 0) {
            return;
        }

        const relativo = clientX - caja.left - MARGEN.izquierda;
        const indice = Math.round(
            (relativo / anchoUtil) * Math.max(1, serie.length - 1),
        );

        setActivo(Math.min(serie.length - 1, Math.max(0, indice)));
    };

    const punto = activo !== null ? serie[activo] : null;
    const tooltipIzquierda =
        activo !== null
            ? Math.min(Math.max(x(activo) - 90, 0), ancho - 180)
            : 0;

    return (
        <figure className="m-0">
            <div className="text-muted-foreground mb-2 flex flex-wrap gap-x-5 gap-y-1 text-[13px]">
                <span className="flex items-center gap-2">
                    <svg width="18" height="4" aria-hidden>
                        <line
                            x1="0"
                            y1="2"
                            x2="18"
                            y2="2"
                            className="stroke-kpi-real"
                            strokeWidth="2"
                            strokeLinecap="round"
                        />
                    </svg>
                    {acumulable ? 'Llevas (acumulado)' : 'Valor del día'}
                </span>
                {hayRitmo && (
                    <span className="flex items-center gap-2">
                        <svg width="18" height="4" aria-hidden>
                            <line
                                x1="0"
                                y1="2"
                                x2="18"
                                y2="2"
                                className="stroke-kpi-ritmo"
                                strokeWidth="2"
                                strokeDasharray="4 3"
                            />
                        </svg>
                        Ritmo para llegar a la meta
                    </span>
                )}
            </div>

            <div
                ref={contenedor}
                className="relative touch-pan-y"
                onPointerMove={(e) => moverA(e.clientX)}
                onPointerLeave={() => setActivo(null)}
            >
                <svg
                    // El ancho medido dibuja a escala 1:1; el 100 % es la red
                    // por si el contenedor cambia antes de que se mida.
                    width="100%"
                    viewBox={`0 0 ${ancho} ${ALTO}`}
                    role="img"
                    aria-label={`${titulo}: gráfica por día. La tabla con los mismos datos está debajo.`}
                    tabIndex={0}
                    className="focus-visible:ring-ring block rounded-md outline-none focus-visible:ring-2"
                    onKeyDown={(e) => {
                        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                            e.preventDefault();
                            const paso = e.key === 'ArrowRight' ? 1 : -1;
                            setActivo((a) =>
                                Math.min(
                                    serie.length - 1,
                                    Math.max(
                                        0,
                                        (a ?? (paso > 0 ? -1 : serie.length)) +
                                            paso,
                                    ),
                                ),
                            );
                        }
                    }}
                    onBlur={() => setActivo(null)}
                >
                    {marcas.map((m) => (
                        <g key={m}>
                            <line
                                x1={MARGEN.izquierda}
                                x2={ancho - MARGEN.derecha}
                                y1={y(m)}
                                y2={y(m)}
                                className={
                                    m === 0
                                        ? 'stroke-muted-foreground/40'
                                        : 'stroke-border'
                                }
                                strokeWidth="1"
                            />
                            <text
                                x={MARGEN.izquierda - 8}
                                y={y(m)}
                                dy="0.32em"
                                textAnchor="end"
                                className="fill-muted-foreground text-[11px] tabular-nums"
                            >
                                {formatearCorto(m, unidad)}
                            </text>
                        </g>
                    ))}

                    {serie.map((p, i) =>
                        i % cadaCuanto === 0 || i === serie.length - 1 ? (
                            <text
                                key={p.date}
                                x={x(i)}
                                y={ALTO - 6}
                                textAnchor="middle"
                                className="fill-muted-foreground text-[11px]"
                            >
                                {fechaCorta(p.date)}
                            </text>
                        ) : null,
                    )}

                    {area && <path d={area} className="fill-kpi-real/10" />}

                    {hayRitmo && (
                        <path
                            d={trazo(ritmo)}
                            fill="none"
                            className="stroke-kpi-ritmo"
                            strokeWidth="2"
                            strokeDasharray="5 4"
                            strokeLinecap="round"
                        />
                    )}

                    <path
                        d={trazo(real)}
                        fill="none"
                        className="stroke-kpi-real"
                        strokeWidth="2"
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />

                    {/* Etiquetas al final de cada línea: lo que llevas y la meta. */}
                    {ultimoReal >= 0 && real[ultimoReal] !== null && (
                        <g>
                            <circle
                                cx={x(ultimoReal)}
                                cy={y(real[ultimoReal] as number)}
                                r="4.5"
                                className="fill-kpi-real stroke-card"
                                strokeWidth="2"
                            />
                            {ultimoReal < serie.length - 1 && (
                                <text
                                    x={x(ultimoReal) + 8}
                                    y={y(real[ultimoReal] as number) - 8}
                                    className="fill-foreground text-[12px] font-semibold tabular-nums"
                                >
                                    {formatear(real[ultimoReal], unidad)}
                                </text>
                            )}
                        </g>
                    )}
                    {hayRitmo && ritmo[ritmo.length - 1] !== null && (
                        <text
                            x={x(serie.length - 1) + 8}
                            y={y(ritmo[ritmo.length - 1] as number)}
                            dy="0.32em"
                            className="fill-muted-foreground text-[11px] tabular-nums"
                        >
                            Meta{' '}
                            {formatearCorto(
                                ritmo[ritmo.length - 1] as number,
                                unidad,
                            )}
                        </text>
                    )}

                    {activo !== null && (
                        <g pointerEvents="none">
                            <line
                                x1={x(activo)}
                                x2={x(activo)}
                                y1={MARGEN.arriba}
                                y2={ALTO - MARGEN.abajo}
                                className="stroke-muted-foreground/60"
                                strokeWidth="1"
                            />
                            {real[activo] !== null && (
                                <circle
                                    cx={x(activo)}
                                    cy={y(real[activo] as number)}
                                    r="4.5"
                                    className="fill-kpi-real stroke-card"
                                    strokeWidth="2"
                                />
                            )}
                            {hayRitmo && ritmo[activo] !== null && (
                                <circle
                                    cx={x(activo)}
                                    cy={y(ritmo[activo] as number)}
                                    r="4"
                                    className="fill-kpi-ritmo stroke-card"
                                    strokeWidth="2"
                                />
                            )}
                        </g>
                    )}
                </svg>

                {punto && (
                    <div
                        role="status"
                        className="bg-popover text-popover-foreground border-border pointer-events-none absolute top-0 z-10 w-[180px] rounded-lg border p-2.5 text-[13px] shadow-(--shadow-pop)"
                        style={{ left: tooltipIzquierda }}
                    >
                        <p className="text-muted-foreground mb-1.5 capitalize">
                            {fechaLarga(punto.date)}
                        </p>
                        {punto.value === null ? (
                            <p className="text-muted-foreground">
                                Todavía no llega.
                            </p>
                        ) : (
                            <>
                                <FilaTooltip
                                    clase="stroke-kpi-real"
                                    valor={formatear(
                                        acumulable
                                            ? punto.cumulative
                                            : punto.value,
                                        unidad,
                                    )}
                                    etiqueta={
                                        acumulable ? 'acumulado' : 'del día'
                                    }
                                />
                                {acumulable && (
                                    <FilaTooltip
                                        valor={formatear(punto.value, unidad)}
                                        etiqueta="ese día"
                                    />
                                )}
                            </>
                        )}
                        {hayRitmo && punto.expected !== null && (
                            <FilaTooltip
                                clase="stroke-kpi-ritmo"
                                punteada
                                valor={formatear(punto.expected, unidad)}
                                etiqueta="esperado"
                            />
                        )}
                    </div>
                )}
            </div>

            <details className="mt-2 text-[13px]">
                <summary className="text-muted-foreground hover:text-foreground cursor-pointer">
                    Ver los datos por día
                </summary>
                <div className="mt-2 max-h-64 overflow-auto">
                    <table className="w-full text-left tabular-nums">
                        <thead className="text-muted-foreground">
                            <tr>
                                <th className="py-1 font-medium">Día</th>
                                <th className="py-1 text-right font-medium">
                                    Del día
                                </th>
                                {acumulable && (
                                    <th className="py-1 text-right font-medium">
                                        Acumulado
                                    </th>
                                )}
                                {hayRitmo && (
                                    <th className="py-1 text-right font-medium">
                                        Esperado
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {serie.map((p) => (
                                <tr
                                    key={p.date}
                                    className="border-border border-t"
                                >
                                    <td className="py-1">
                                        {fechaCorta(p.date)}
                                    </td>
                                    <td className="py-1 text-right">
                                        {formatear(p.value, unidad)}
                                    </td>
                                    {acumulable && (
                                        <td className="py-1 text-right">
                                            {formatear(p.cumulative, unidad)}
                                        </td>
                                    )}
                                    {hayRitmo && (
                                        <td className="py-1 text-right">
                                            {formatear(p.expected, unidad)}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </figure>
    );
}

function FilaTooltip({
    valor,
    etiqueta,
    clase,
    punteada = false,
}: {
    valor: string;
    etiqueta: string;
    clase?: string;
    punteada?: boolean;
}) {
    return (
        <p className="flex items-center gap-2">
            <svg
                width="12"
                height="4"
                aria-hidden
                className={clase ? '' : 'invisible'}
            >
                <line
                    x1="0"
                    y1="2"
                    x2="12"
                    y2="2"
                    className={clase}
                    strokeWidth="2"
                    strokeDasharray={punteada ? '3 2' : undefined}
                />
            </svg>
            <span className="font-semibold tabular-nums">{valor}</span>
            <span className="text-muted-foreground">{etiqueta}</span>
        </p>
    );
}
