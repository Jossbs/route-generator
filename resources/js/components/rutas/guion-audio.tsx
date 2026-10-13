import { Check, ChevronDown, Copy, MessageCircle, Mic } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import type { GuionDeAudio, MensajeEscrito } from '@/types/rutas';

/** Lo que dura un audio que se escucha completo. Ver App\Services\Documents\AudioScript. */
const SEGUNDOS_MIN = 45;
const SEGUNDOS_MAX = 60;

/**
 * Lo que el sistema no puede garantizar por el asesor.
 *
 * El módulo anterior pedía dieciséis comprobaciones, pero casi todas eran sobre
 * el texto —que dijera «personalizadas», que nombrara dos cursos, que mencionara
 * los doce meses— y el texto ya sale armado con eso. Lo que queda es cómo se
 * graba, que solo lo sabe quien lo grabó.
 */
const REVISION = [
    'Dije su nombre en los primeros 3 segundos.',
    'Dije «SyS» completo y se entiende.',
    'Se oye el énfasis en las frases resaltadas.',
    'Lo que me contó lo dije con sus palabras, no leído.',
    'No prometí nada que no controlo.',
    `Duró entre ${SEGUNDOS_MIN} y ${SEGUNDOS_MAX} segundos, en una sola toma.`,
];

function ritmo(segundos: number): { texto: string; enPunto: boolean } {
    if (segundos < SEGUNDOS_MIN) {
        return { texto: 'corto', enPunto: false };
    }

    if (segundos > SEGUNDOS_MAX) {
        return { texto: 'largo', enPunto: false };
    }

    return { texto: 'en el punto', enPunto: true };
}

type Segmento = { texto: string; dato: string | null; enfasis: boolean };

type Pestana = 'audio' | 'mensaje';

/**
 * Lo que acompaña al PDF: el guión del audio, o el mensaje escrito que lo
 * sustituye cuando el asesor no puede grabar. Los dos salen de la misma ruta.
 *
 * Lo resaltado en amarillo salió de la captura o de la ruta —es lo que conviene
 * revisar—; lo que va en negritas es donde se sube la voz en el audio, y la
 * negrita de WhatsApp en el mensaje.
 */
export function GuionAudio({
    guion,
    mensaje,
}: {
    guion: GuionDeAudio | null;
    mensaje: MensajeEscrito | null;
}) {
    const [copiado, copiar] = useClipboard();
    const [pestana, setPestana] = useState<Pestana>(
        guion ? 'audio' : 'mensaje',
    );
    const [revisados, setRevisados] = useState<Set<number>>(new Set());

    const alternar = (indice: number) =>
        setRevisados((actual) => {
            const siguiente = new Set(actual);

            if (siguiente.has(indice)) {
                siguiente.delete(indice);
            } else {
                siguiente.add(indice);
            }

            return siguiente;
        });

    const enAudio = pestana === 'audio' && guion !== null;
    const actual = enAudio ? guion : mensaje;

    if (actual === null) {
        return null;
    }

    const medida = guion ? ritmo(guion.segundos) : null;

    return (
        <section
            id="guion"
            className="bg-card shadow-card space-y-4 rounded-xl border p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-2">
                    <h2 className="font-subtitle flex items-center gap-2 text-lg font-bold">
                        {enAudio ? (
                            <Mic className="size-4" />
                        ) : (
                            <MessageCircle className="size-4" />
                        )}
                        Lo que acompaña al PDF
                    </h2>

                    {guion && mensaje && (
                        <div
                            role="group"
                            aria-label="Audio o mensaje"
                            className="bg-muted inline-flex gap-1 rounded-xl p-1"
                        >
                            {(
                                [
                                    ['audio', 'Audio', Mic],
                                    ['mensaje', 'Mensaje', MessageCircle],
                                ] as const
                            ).map(([valor, etiqueta, Icono]) => (
                                <button
                                    key={valor}
                                    type="button"
                                    aria-pressed={pestana === valor}
                                    onClick={() => setPestana(valor)}
                                    className={cn(
                                        'focus-visible:ring-ring/50 flex min-h-9 items-center gap-1.5 rounded-lg px-3 text-sm transition-colors outline-none focus-visible:ring-[3px]',
                                        pestana === valor
                                            ? 'bg-card text-foreground font-semibold shadow-xs'
                                            : 'text-muted-foreground hover:bg-card/60 hover:text-foreground',
                                    )}
                                >
                                    <Icono className="size-4" />
                                    {etiqueta}
                                </button>
                            ))}
                        </div>
                    )}

                    <p className="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        {enAudio && guion && medida ? (
                            <>
                                <span>
                                    {guion.segundos} s · {guion.palabras}{' '}
                                    palabras · su nombre{' '}
                                    {guion.menciones_nombre}{' '}
                                    {guion.menciones_nombre === 1
                                        ? 'vez'
                                        : 'veces'}
                                </span>
                                <Badge
                                    variant={
                                        medida.enPunto ? 'secondary' : 'outline'
                                    }
                                >
                                    {medida.texto}
                                </Badge>
                            </>
                        ) : (
                            <span>
                                Para cuando no puedes grabar: pégalo junto al
                                PDF.
                            </span>
                        )}
                    </p>
                </div>

                <Button
                    variant="outline"
                    className="max-sm:w-full"
                    onClick={() => copiar(actual.texto)}
                >
                    {copiado === actual.texto ? (
                        <Check className="size-4" />
                    ) : (
                        <Copy className="size-4" />
                    )}
                    {copiado === actual.texto
                        ? 'Copiado'
                        : enAudio
                          ? 'Copiar el guión'
                          : 'Copiar el mensaje'}
                </Button>
            </div>

            <div className="space-y-3 text-[15px] leading-7">
                {actual.bloques.map((bloque) => (
                    <p key={bloque.clave}>
                        <Segmentos segmentos={bloque.segmentos} />
                    </p>
                ))}
            </div>

            <p className="text-muted-foreground text-xs">
                <span className="rounded bg-amber-100 px-0.5 dark:bg-amber-400/20">
                    Resaltado
                </span>
                : sale de lo que capturaste, revísalo.{' '}
                <span className="text-foreground font-semibold">Negritas</span>
                {enAudio
                    ? ': ahí sube la voz.'
                    : ': al copiarlo ya van con los asteriscos de WhatsApp.'}
            </p>

            {enAudio && guion && (
                <div className="flex flex-col gap-2 border-t pt-4">
                    <Collapsible>
                        <CollapsibleTrigger className="group text-muted-foreground hover:text-foreground flex items-center gap-1 text-sm">
                            <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                            Los bloques y sus tiempos
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <ul className="mt-2 divide-y text-sm">
                                {guion.bloques.map((bloque) => (
                                    <li
                                        key={bloque.clave}
                                        className="flex justify-between gap-4 py-1.5"
                                    >
                                        <span>{bloque.titulo}</span>
                                        <span className="text-muted-foreground tabular-nums">
                                            {bloque.segundos} s
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CollapsibleContent>
                    </Collapsible>

                    <Collapsible>
                        <CollapsibleTrigger className="group text-muted-foreground hover:text-foreground flex items-center gap-1 text-sm">
                            <ChevronDown className="size-4 transition-transform group-data-[state=open]:rotate-180" />
                            Revísalo antes de mandarlo ({revisados.size}/
                            {REVISION.length})
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <ul className="mt-2 space-y-2">
                                {REVISION.map((punto, indice) => (
                                    <li key={punto}>
                                        <label className="flex cursor-pointer items-start gap-2 text-sm">
                                            <Checkbox
                                                className="mt-0.5"
                                                checked={revisados.has(indice)}
                                                onCheckedChange={() =>
                                                    alternar(indice)
                                                }
                                            />
                                            {punto}
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            )}
        </section>
    );
}

function Segmentos({ segmentos }: { segmentos: Segmento[] }) {
    return segmentos.map((segmento, indice) => (
        <span
            key={indice}
            className={cn(
                segmento.enfasis && 'font-semibold',
                segmento.dato &&
                    'rounded bg-amber-100 px-0.5 dark:bg-amber-400/20',
            )}
        >
            {segmento.texto}
        </span>
    ));
}
