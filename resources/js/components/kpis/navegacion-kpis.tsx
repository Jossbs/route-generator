import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import kpis from '@/routes/kpis';

type Props = { puedeAdministrar: boolean };

/**
 * Las secciones del módulo. El tablero lo ve todo ventas; lo demás es de quien
 * administra, y a los demás ni se les muestra.
 */
export function NavegacionKpis({ puedeAdministrar }: Props) {
    const { url } = usePage();
    const ruta = url.split('?')[0];

    const secciones = [
        { titulo: 'Tablero', href: kpis.dashboard().url, exacta: true },
        ...(puedeAdministrar
            ? [
                  { titulo: 'Captura', href: kpis.events.index().url },
                  { titulo: 'Metas', href: kpis.targets.index().url },
                  { titulo: 'Cierres', href: kpis.closures.index().url },
                  { titulo: 'Configuración', href: kpis.settings().url },
              ]
            : []),
    ];

    if (secciones.length === 1) {
        return null;
    }

    return (
        <nav
            aria-label="Secciones de KPIs"
            className="border-border -mx-4 mb-6 flex [scrollbar-width:none] gap-1 overflow-x-auto overflow-y-hidden border-b px-4 sm:mx-0 sm:px-0"
        >
            {secciones.map((s) => {
                const activa = s.exacta
                    ? ruta === s.href
                    : ruta.startsWith(s.href);

                return (
                    <Link
                        key={s.href}
                        href={s.href}
                        prefetch
                        aria-current={activa ? 'page' : undefined}
                        className={cn(
                            '-mb-px shrink-0 border-b-2 px-3 py-2.5 text-[15px] transition-colors',
                            activa
                                ? 'border-primary text-foreground font-semibold'
                                : 'text-muted-foreground hover:text-foreground border-transparent',
                        )}
                    >
                        {s.titulo}
                    </Link>
                );
            })}
        </nav>
    );
}
