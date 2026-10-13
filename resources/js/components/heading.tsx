import { cn } from '@/lib/utils';

/**
 * El encabezado de sección del sitio: antetítulo rosa en versalitas, título en
 * Anton mayúsculas y bajada en gris.
 *
 * Vive aquí y no en cada vista para que un título nuevo no vuelva a decidir
 * tipografía ni color.
 */
export default function Heading({
    overline,
    title,
    description,
    variant = 'default',
    className,
}: {
    overline?: string;
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    className?: string;
}) {
    const pequeno = variant === 'small';

    return (
        <header className={cn(pequeno ? 'mb-3' : 'mb-8', className)}>
            {overline && <p className="sys-overline mb-2">{overline}</p>}

            <h2
                className={cn(
                    'sys-display text-foreground break-words hyphens-auto',
                    pequeno ? 'text-xl' : 'text-3xl sm:text-4xl',
                )}
            >
                {title}
            </h2>

            {description && (
                <p
                    className={cn(
                        'text-muted-foreground leading-relaxed',
                        pequeno ? 'mt-1 text-sm' : 'mt-3 max-w-2xl',
                    )}
                >
                    {description}
                </p>
            )}
        </header>
    );
}
