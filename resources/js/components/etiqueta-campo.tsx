import type { ComponentProps } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * La etiqueta de un campo con su obligatoriedad a la vista.
 *
 * Obligatorio lleva el asterisco rosa; opcional, la palabra. Sin leyenda aparte:
 * el asterisco se entiende solo junto a su pareja «Opcional». Los dos a la vez
 * porque el formulario los mezcla: con una sola marca, el asesor tendría que
 * adivinar qué significa la ausencia. El asterisco es decorativo para el lector
 * de pantalla, que en su lugar oye «obligatorio».
 */
export function EtiquetaCampo({
    requerido = false,
    opcional = false,
    className,
    children,
    ...props
}: ComponentProps<typeof Label> & {
    requerido?: boolean;
    opcional?: boolean;
}) {
    return (
        <Label
            className={cn(
                'flex flex-wrap items-baseline gap-x-1.5 gap-y-1 leading-snug',
                className,
            )}
            {...props}
        >
            <span>
                {children}
                {requerido && (
                    <>
                        <span
                            aria-hidden="true"
                            className="text-rosa-texto ml-0.5 font-bold"
                        >
                            *
                        </span>
                        <span className="sr-only"> (obligatorio)</span>
                    </>
                )}
            </span>
            {opcional && (
                <span className="text-muted-foreground bg-muted rounded-full px-2 py-0.5 text-[0.6875rem] leading-none font-medium">
                    Opcional
                </span>
            )}
        </Label>
    );
}
