import { Head, Link, router } from '@inertiajs/react';
import { Lock, Send, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { NavegacionKpis } from '@/components/kpis/navegacion-kpis';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { fechaCorta } from '@/lib/kpis';
import kpis from '@/routes/kpis';
import type { OpcionSimple } from '@/types/kpis';

type Props = {
    cierres: {
        id: number;
        month: string;
        fecha: string;
        closed_at: string;
        by: string | null;
        adjustments: number;
    }[];
    porCerrar: OpcionSimple[];
    resumenes: {
        id: number;
        kind: 'diario' | 'semanal';
        period_start: string;
        period_end: string;
        body: string;
        sent_at: string | null;
        error: string | null;
        created_at: string | null;
    }[];
    telegramConfigurado: boolean;
};

/**
 * El HTML reducido de Telegram, leído como texto. `DOMParser` y no `innerHTML`:
 * un documento inerte no carga imágenes ni corre manejadores.
 */
function textoDeTelegram(html: string): string {
    return (
        new DOMParser().parseFromString(html, 'text/html').body.textContent ??
        ''
    );
}

/**
 * Lo que ya se reportó: los meses cerrados y los resúmenes que se mandaron al
 * grupo de ventas.
 *
 * Cerrar un mes congela sus números; después, cualquier cambio sobre ese mes
 * entra como ajuste con motivo, y aquí se ve cuántos hubo.
 */
export default function Cierres({
    cierres,
    porCerrar,
    resumenes,
    telegramConfigurado,
}: Props) {
    const [porConfirmar, setPorConfirmar] = useState<OpcionSimple | null>(null);
    const [enviando, setEnviando] = useState(false);

    return (
        <AppLayout>
            <Head title="Cierres y resúmenes" />
            <NavegacionKpis puedeAdministrar />
            <Heading
                overline="KPIs · Cierres"
                title="Cierres y resúmenes"
                className="mb-6"
            />

            <div className="grid gap-8 lg:grid-cols-2">
                <section>
                    <h3 className="mb-1 text-lg font-semibold">
                        Cierre de mes
                    </h3>
                    <p className="text-muted-foreground mb-4 text-[14px]">
                        Congela los números de un mes que ya terminó: lo que se
                        reportó y sobre lo que se pagará comisión.
                    </p>

                    {porCerrar.length > 0 ? (
                        <ul className="mb-6 flex flex-col gap-2">
                            {porCerrar.map((m) => (
                                <li
                                    key={m.value}
                                    className="bg-card border-border flex items-center justify-between rounded-xl border p-3 pl-4 shadow-(--shadow-card)"
                                >
                                    <span className="font-medium">
                                        {m.label}
                                    </span>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setPorConfirmar(m)}
                                    >
                                        <Lock /> Cerrar mes
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-muted-foreground mb-6 text-[14px]">
                            No hay meses terminados pendientes de cerrar.
                        </p>
                    )}

                    {cierres.length > 0 && (
                        <ul className="flex flex-col gap-1 text-[14px]">
                            {cierres.map((c) => (
                                <li
                                    key={c.id}
                                    className="border-border flex flex-wrap items-baseline justify-between gap-2 border-t py-2"
                                >
                                    <Link
                                        href={
                                            kpis.dashboard({
                                                query: {
                                                    periodo: 'mes',
                                                    fecha: c.fecha,
                                                },
                                            }).url
                                        }
                                        className="font-medium underline-offset-4 hover:underline"
                                    >
                                        {c.month}
                                    </Link>
                                    <span className="text-muted-foreground">
                                        Cerrado el{' '}
                                        {fechaCorta(c.closed_at.slice(0, 10))}
                                        {c.by && ` por ${c.by}`}
                                        {c.adjustments > 0 &&
                                            ` · ${c.adjustments} ${c.adjustments === 1 ? 'ajuste' : 'ajustes'} después`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section>
                    <h3 className="mb-1 text-lg font-semibold">
                        Resúmenes a Telegram
                    </h3>
                    <p className="text-muted-foreground mb-4 text-[14px]">
                        El del día sale al cierre de la jornada y el de la
                        semana el lunes por la mañana, al grupo de ventas.
                    </p>

                    {!telegramConfigurado && (
                        <p className="bg-muted mb-4 flex gap-2 rounded-lg px-4 py-3 text-[14px]">
                            <TriangleAlert
                                className="text-kpi-cerca mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            <span>
                                Telegram no está configurado: los resúmenes se
                                guardan aquí pero no se envían. Falta{' '}
                                <code className="text-[13px]">
                                    TELEGRAM_BOT_TOKEN
                                </code>{' '}
                                y{' '}
                                <code className="text-[13px]">
                                    TELEGRAM_KPI_CHAT_ID
                                </code>
                                .
                            </span>
                        </p>
                    )}

                    <div className="mb-4 flex flex-wrap gap-2">
                        {(['diario', 'semanal'] as const).map((tipo) => (
                            <Button
                                key={tipo}
                                variant="outline"
                                size="sm"
                                disabled={enviando}
                                onClick={() =>
                                    router.post(
                                        kpis.digests.store.url(),
                                        { kind: tipo },
                                        {
                                            preserveScroll: true,
                                            onStart: () => setEnviando(true),
                                            onFinish: () => setEnviando(false),
                                        },
                                    )
                                }
                            >
                                <Send />{' '}
                                {tipo === 'diario'
                                    ? 'Mandar el de hoy'
                                    : 'Mandar el de la semana pasada'}
                            </Button>
                        ))}
                    </div>

                    {resumenes.length === 0 ? (
                        <p className="text-muted-foreground text-[14px]">
                            Todavía no se ha generado ningún resumen.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {resumenes.map((r) => (
                                <li
                                    key={r.id}
                                    className="bg-card border-border rounded-xl border p-4 shadow-(--shadow-card)"
                                >
                                    <p className="mb-2 flex flex-wrap justify-between gap-2 text-[13px]">
                                        <span className="font-semibold">
                                            {r.kind === 'diario'
                                                ? 'Del día'
                                                : 'De la semana'}{' '}
                                            · {fechaCorta(r.period_start)}
                                            {r.period_end !== r.period_start &&
                                                ` – ${fechaCorta(r.period_end)}`}
                                        </span>
                                        {r.sent_at ? (
                                            <span className="text-kpi-bien-texto">
                                                Enviado
                                            </span>
                                        ) : (
                                            <span
                                                className="text-muted-foreground"
                                                title={r.error ?? undefined}
                                            >
                                                No enviado
                                            </span>
                                        )}
                                    </p>
                                    <details>
                                        <summary className="text-muted-foreground hover:text-foreground cursor-pointer text-[13px]">
                                            Ver el mensaje
                                        </summary>
                                        <pre className="mt-2 font-sans text-[13px] leading-relaxed whitespace-pre-wrap">
                                            {textoDeTelegram(r.body)}
                                        </pre>
                                        {r.error && (
                                            <p className="text-muted-foreground mt-2 text-[12px]">
                                                {r.error}
                                            </p>
                                        )}
                                    </details>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <Dialog
                open={porConfirmar !== null}
                onOpenChange={(o) => !o && setPorConfirmar(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cerrar {porConfirmar?.label}</DialogTitle>
                        <DialogDescription>
                            Sus números quedan congelados tal como están hoy.
                            Después, cualquier cambio sobre ese mes pedirá un
                            motivo y quedará marcado como ajuste.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPorConfirmar(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={() =>
                                porConfirmar &&
                                router.post(
                                    kpis.closures.store.url(),
                                    { month: porConfirmar.value },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setPorConfirmar(null),
                                    },
                                )
                            }
                        >
                            Cerrar mes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
