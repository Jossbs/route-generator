import { usePage } from '@inertiajs/react';
import { FileText, Megaphone, Route } from 'lucide-react';
import { SysHeader } from '@/components/sys-header';
import { index as embudos } from '@/routes/funnels';
import { create as generar, index as rutas } from '@/routes/routes';
import type { NavItem } from '@/types';

/*
 * Tres secciones, en el orden en que se usan. Generar es el trabajo de todos los
 * días y por eso abre; las rutas generadas se consultan cuando hay que volver a
 * mandar un documento; el catálogo de embudos es trabajo de mercadotecnia.
 */
const navegacion: NavItem[] = [
    { title: 'Generar ruta', href: generar(), icon: Route },
    { title: 'Rutas', href: rutas(), icon: FileText },
    { title: 'Embudos', href: embudos(), icon: Megaphone },
];

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { url } = usePage();

    return (
        <div className="flex min-h-svh w-full flex-col">
            <a href="#contenido" className="sys-skip-link">
                Saltar al contenido
            </a>

            <SysHeader items={navegacion} />

            {/*
             * Los márgenes de la página se deciden aquí y en ningún otro lado:
             * una vista que los repita termina con el doble en el celular, que
             * es donde menos sobra.
             *
             * La `key` es lo que hace que la entrada se vuelva a animar en cada
             * navegación: Inertia reemplaza el contenido sin desmontar el
             * layout, así que sin ella la animación solo correría una vez.
             */}
            <main
                id="contenido"
                key={url}
                className="sys-page-enter mx-auto w-full max-w-[1180px] flex-1 px-4 py-6 sm:px-8 sm:py-10"
            >
                {children}
            </main>
        </div>
    );
}
