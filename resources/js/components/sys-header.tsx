import { Link, usePage } from '@inertiajs/react';
import { FileDown, Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { SelectorTema } from '@/components/selector-tema';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn, toUrl } from '@/lib/utils';
import catalog from '@/routes/catalog';
import type { NavItem } from '@/types';

type Props = {
    items: NavItem[];
};

/**
 * El encabezado del sistema, calcado del de academy.dpsys.com.mx: barra vinosa
 * translúcida, logo a la izquierda, enlaces al centro con subrayado rosa en el
 * activo, y la identidad a la derecha.
 *
 * Es deliberado que sea el mismo: el asesor abre este módulo desde el sistema
 * de DP SyS y el lead recibe un documento con esta misma marca. Una carcasa
 * neutra rompía esa continuidad.
 *
 * En el celular los tres enlaces no caben junto al logo y la identidad, así que
 * se van al panel lateral. Ahí van con su icono y en renglones altos: el asesor
 * navega con el pulgar, no con el cursor.
 */
export function SysHeader({ items }: Props) {
    const { props, url } = usePage();
    const { auth } = props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const getInitials = useInitials();
    const [menuAbierto, setMenuAbierto] = useState(false);

    // Navegar con Inertia no desmonta el header, así que el panel se quedaría
    // abierto encima de la pantalla a la que se acaba de llegar.
    useEffect(() => setMenuAbierto(false), [url]);

    // La raíz es prefijo de todo, así que ahí la coincidencia tiene que ser
    // exacta o el módulo entero se marcaría activo estando en cualquier otra
    // sección.
    const esActivo = (item: NavItem) =>
        toUrl(item.href) === '/'
            ? isCurrentUrl(item.href)
            : isCurrentOrParentUrl(item.href);

    return (
        <header className="bg-header/85 border-sys-rosa/16 sticky top-0 z-50 border-b backdrop-blur-[18px]">
            <div className="mx-auto flex h-(--header-h) w-full max-w-[1180px] items-center gap-3 px-4 sm:gap-7 sm:px-8">
                <Sheet open={menuAbierto} onOpenChange={setMenuAbierto}>
                    <SheetTrigger
                        className="focus-visible:ring-ring focus-visible:ring-offset-header -ml-2 flex size-10 shrink-0 items-center justify-center rounded-full text-white/80 transition-colors outline-none hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-offset-2 md:hidden"
                        aria-label="Abrir el menú"
                        data-test="sys-header-menu"
                    >
                        <Menu className="size-5" />
                    </SheetTrigger>

                    <SheetContent
                        side="left"
                        // El panel no lleva bajada: los tres enlaces se explican
                        // solos y Radix avisa si se la damos vacía.
                        aria-describedby={undefined}
                        className="bg-header w-72 gap-0 border-r-0 p-0 text-white"
                    >
                        <SheetTitle className="border-sys-rosa/16 flex h-(--header-h) items-center border-b px-5 text-white">
                            <img
                                src="/img/dpsys/logo-blanco.svg"
                                alt="Desarrollo Profesional SyS"
                                className="h-9 w-auto"
                            />
                        </SheetTitle>

                        <nav className="flex flex-col gap-1 p-3">
                            {items.map((item) => {
                                const activo = esActivo(item);
                                const Icono = item.icon;

                                return (
                                    <Link
                                        key={item.title}
                                        href={item.href}
                                        prefetch
                                        aria-current={
                                            activo ? 'page' : undefined
                                        }
                                        className={cn(
                                            'flex min-h-12 items-center gap-3 rounded-xl px-3 text-[15px] transition-colors',
                                            activo
                                                ? 'bg-primary text-primary-foreground font-semibold'
                                                : 'font-medium text-white/75 hover:bg-white/10 hover:text-white',
                                        )}
                                    >
                                        {Icono && (
                                            <Icono className="size-[18px] shrink-0" />
                                        )}
                                        {item.title}
                                    </Link>
                                );
                            })}
                        </nav>

                        {auth.user && (
                            <div className="border-sys-rosa/16 mt-auto flex items-center gap-3 border-t px-5 py-4">
                                <span className="bg-primary text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                                    {getInitials(auth.user.name)}
                                </span>
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-semibold">
                                        {auth.user.name}
                                    </span>
                                    <span className="block truncate text-xs text-white/60">
                                        {auth.user.email}
                                    </span>
                                </span>
                            </div>
                        )}
                    </SheetContent>
                </Sheet>

                <Link
                    href={items[0]?.href ?? '/'}
                    className="focus-visible:ring-ring focus-visible:ring-offset-header flex shrink-0 items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                    aria-label="Desarrollo Profesional SyS"
                >
                    <img
                        src="/img/dpsys/logo-blanco.svg"
                        alt="Desarrollo Profesional SyS"
                        className="h-9 w-auto sm:h-11"
                    />
                </Link>

                <nav className="hidden items-center gap-7 md:flex">
                    {items.map((item) => {
                        const activo = esActivo(item);

                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                prefetch
                                aria-current={activo ? 'page' : undefined}
                                className={cn(
                                    'relative py-1 text-[15px] transition-colors',
                                    activo
                                        ? 'after:bg-primary font-semibold text-white after:absolute after:inset-x-0 after:-bottom-1.5 after:h-[3px] after:rounded-[3px] after:content-[""]'
                                        : 'font-medium text-white/70 hover:text-white',
                                )}
                            >
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>

                <div className="ml-auto flex min-w-0 items-center gap-1 sm:gap-2">
                    {/* El catálogo completo con sus horas, para mandárselo a
                        cualquier lead. Es una descarga, no una pantalla: un
                        enlace normal, no uno de Inertia. */}
                    <a
                        href={catalog.pdf.url()}
                        download
                        title="Descargar el catálogo de cursos en PDF"
                        className="focus-visible:ring-ring focus-visible:ring-offset-header flex h-9 shrink-0 items-center gap-2 rounded-full px-2.5 text-[14px] font-medium text-white/80 transition-colors outline-none hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-offset-2 sm:px-3"
                        data-test="sys-header-catalogo"
                    >
                        <FileDown className="size-[18px]" />
                        <span className="hidden sm:inline">Catálogo</span>
                    </a>

                    <SelectorTema />

                    {auth.user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                className="focus-visible:ring-ring focus-visible:ring-offset-header flex min-w-0 items-center gap-2.5 rounded-full p-1 text-[15px] font-semibold text-white/90 transition-colors outline-none hover:text-white focus-visible:ring-2 focus-visible:ring-offset-2"
                                data-test="sys-header-user"
                            >
                                <span className="hidden max-w-40 truncate lg:inline">
                                    {auth.user.name}
                                </span>
                                <span className="bg-primary text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                                    {getInitials(auth.user.name)}
                                </span>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="min-w-56 rounded-lg"
                                align="end"
                            >
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>
        </header>
    );
}
