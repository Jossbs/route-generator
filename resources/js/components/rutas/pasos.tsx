import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Props = {
    volver?: BreadcrumbItem;
    actual: string;
    siguientes: string[];
};

/**
 * Los tres pasos del documento, siempre a la vista.
 *
 * El asesor tiene que saber en cuál va y qué falta: el de en medio no se salta,
 * porque es donde revisa antes de que salga nada. En la captura no hay a dónde
 * volver —es la primera pantalla—, así que el enlace es opcional.
 */
export function Pasos({ volver, actual, siguientes }: Props) {
    const pasos = [actual, ...siguientes];

    return (
        <nav className="mb-6 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
            {volver && (
                <Link
                    href={volver.href}
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 transition-colors"
                >
                    <ChevronLeft className="size-4" />
                    {volver.title}
                </Link>
            )}

            <ol className="flex flex-wrap items-center gap-x-3 gap-y-2">
                {pasos.map((paso, indice) => {
                    const esActual = paso === actual;

                    return (
                        <li key={paso} className="flex items-center gap-3">
                            {indice > 0 && (
                                <span
                                    aria-hidden="true"
                                    className="text-border select-none"
                                >
                                    →
                                </span>
                            )}
                            <span
                                aria-current={esActual ? 'step' : undefined}
                                className={cn(
                                    'inline-flex items-center gap-2',
                                    esActual
                                        ? 'text-foreground font-semibold'
                                        : 'text-muted-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-5 items-center justify-center rounded-full text-[11px] font-semibold',
                                        esActual
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {indice + 1}
                                </span>
                                {paso}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
