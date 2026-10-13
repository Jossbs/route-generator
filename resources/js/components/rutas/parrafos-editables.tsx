import { ChevronDown, Pencil, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { AjustesDeTexto, ParrafoEditable } from '@/types/rutas';

/** El largo máximo de un párrafo ajustado. Ver App\Services\Documents\EditableCopy::MAX. */
const MAXIMO = 220;

/**
 * Los párrafos del documento que se arman con la captura y a veces quedan
 * torpes: el objetivo, «por qué esta ruta» y la bajada de la portada.
 *
 * Va cerrado: casi siempre el texto de catálogo basta y la revisión es de
 * cursos, no de redacción. Cuando se abre, cada párrafo se ajusta en su sitio y
 * vuelve al original con un clic. Lo ajustado pasa por el mismo escaneo de
 * frases prohibidas que el resto del documento, así que un «equivale a» se
 * rechaza aquí mismo, con la frase que lo sustituye.
 */
export function ParrafosEditables({
    parrafos,
    ajustes,
    onChange,
    errores,
}: {
    parrafos: ParrafoEditable[];
    ajustes: AjustesDeTexto;
    onChange: (ajustes: AjustesDeTexto) => void;
    errores: Record<string, string | undefined>;
}) {
    const ajustados = parrafos.filter((p) => ajustes[p.slug] !== undefined);
    const conError = parrafos.some((p) => errores[`ajustes.${p.slug}`]);
    const [abierto, setAbierto] = useState(ajustados.length > 0 || conError);
    const [editando, setEditando] = useState<string | null>(null);

    if (parrafos.length === 0) {
        return null;
    }

    const cambiar = (slug: string, texto: string) =>
        onChange({ ...ajustes, [slug]: texto });

    const restaurar = (slug: string) => {
        const siguiente = { ...ajustes };
        delete siguiente[slug];
        onChange(siguiente);
        setEditando(null);
    };

    return (
        <Collapsible
            open={abierto || conError}
            onOpenChange={setAbierto}
            className="mt-8 rounded-xl border"
        >
            <CollapsibleTrigger className="hover:bg-muted/40 flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left">
                <Pencil className="text-muted-foreground size-4 shrink-0" />
                <span className="flex-1">
                    <span className="block font-medium">
                        Textos del documento
                    </span>
                    <span className="text-muted-foreground block text-sm">
                        Por si alguno quedó torpe con los datos de la captura.
                    </span>
                </span>
                {ajustados.length > 0 && (
                    <Badge variant="secondary">
                        {ajustados.length === 1
                            ? '1 ajustado'
                            : `${ajustados.length} ajustados`}
                    </Badge>
                )}
                <ChevronDown
                    className={cn(
                        'text-muted-foreground size-4 shrink-0 transition-transform',
                        (abierto || conError) && 'rotate-180',
                    )}
                />
            </CollapsibleTrigger>

            <CollapsibleContent className="space-y-5 border-t px-4 py-4">
                {parrafos.map((parrafo) => {
                    const ajustado = ajustes[parrafo.slug];
                    const texto = ajustado ?? parrafo.texto;
                    const error = errores[`ajustes.${parrafo.slug}`];
                    const abiertoAqui =
                        editando === parrafo.slug || error !== undefined;
                    const id = `ajuste-${parrafo.slug.replace(/\W+/g, '-')}`;

                    return (
                        <div key={parrafo.slug} className="grid gap-2">
                            <div className="flex items-center gap-2">
                                <label
                                    htmlFor={id}
                                    className="text-sm font-medium"
                                >
                                    {parrafo.etiqueta}
                                </label>
                                {ajustado !== undefined && (
                                    <Badge variant="outline">Ajustado</Badge>
                                )}
                                <span className="ml-auto flex gap-1">
                                    {ajustado !== undefined && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                restaurar(parrafo.slug)
                                            }
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Volver al original
                                        </Button>
                                    )}
                                    {!abiertoAqui && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setEditando(parrafo.slug)
                                            }
                                        >
                                            <Pencil className="size-3.5" />
                                            Ajustar
                                        </Button>
                                    )}
                                </span>
                            </div>

                            {abiertoAqui ? (
                                <>
                                    <Textarea
                                        id={id}
                                        value={texto}
                                        maxLength={MAXIMO}
                                        rows={3}
                                        aria-invalid={!!error || undefined}
                                        onChange={(event) =>
                                            cambiar(
                                                parrafo.slug,
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <div className="text-muted-foreground flex justify-between text-xs">
                                        <span>
                                            Sin precios ni comparaciones con
                                            otras certificaciones: se revisa
                                            igual que el resto del documento.
                                        </span>
                                        <span className="tabular-nums">
                                            {texto.length}/{MAXIMO}
                                        </span>
                                    </div>
                                    <InputError message={error} />
                                </>
                            ) : (
                                <p
                                    id={id}
                                    className="bg-muted/40 rounded-lg px-3 py-2 text-sm"
                                >
                                    {texto}
                                </p>
                            )}
                        </div>
                    );
                })}
            </CollapsibleContent>
        </Collapsible>
    );
}
