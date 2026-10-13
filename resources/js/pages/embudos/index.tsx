import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { useCargando } from '@/hooks/use-cargando';
import { fechaCorta } from '@/lib/embudos';
import { cn } from '@/lib/utils';
import { create, destroy, edit, index, show } from '@/routes/funnels';
import type { EmbudoResumen, Especialidad } from '@/types/embudos';

type Paginado<T> = {
    data: T[];
    meta: { current_page: number; last_page: number; total: number };
    links: { prev: string | null; next: string | null };
};

type Filtros = {
    q: string;
    especialidad: string;
};

type PageProps = {
    embudos: Paginado<EmbudoResumen>;
    filtros: Filtros;
    especialidades: Especialidad[];
    puedeCrear: boolean;
};

const TODAS = 'todas';

/**
 * El listado interno de rutas curadas.
 *
 * Tres datos por renglón: cómo se llama, cuándo se armó y cuántos cursos lleva.
 * El número de cursos es lo que dice de un vistazo si está terminada; uno con cero
 * existe, pero no aparece en el selector del asesor.
 */
export default function EmbudosIndex({
    embudos,
    filtros,
    especialidades,
    puedeCrear,
}: PageProps) {
    const [busqueda, setBusqueda] = useState(filtros.q);
    const [porBorrar, setPorBorrar] = useState<EmbudoResumen | null>(null);
    const cargando = useCargando();

    const hayFiltros = filtros.q !== '' || filtros.especialidad !== '';

    const filtrar = (cambios: Partial<Filtros>) => {
        const siguientes = { ...filtros, q: busqueda, ...cambios };

        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(siguientes).filter(
                    ([, valor]) => valor !== '' && valor !== TODAS,
                ),
            ),
            { preserveState: true, replace: true },
        );
    };

    const limpiar = () => {
        setBusqueda('');
        router.get(index().url, {}, { preserveState: true, replace: true });
    };

    const vacia = embudos.data.length === 0;

    const mensajeVacio = hayFiltros
        ? 'No hay rutas que coincidan con estos filtros.'
        : 'Todavía no hay embudos. Arma el primero con los cursos de una rama.';

    return (
        <AppLayout>
            <Head title="Embudos" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        overline="Embudos"
                        title="Embudos y rutas principales"
                        description="Cada una junta los cursos de una rama. Es lo que el asesor elige como ruta principal del lead."
                        className="mb-0"
                    />

                    {puedeCrear && (
                        <Button asChild className="max-sm:w-full">
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nuevo embudo
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <form
                        className="relative sm:min-w-56 sm:flex-1"
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            filtrar({});
                        }}
                    >
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            type="search"
                            aria-label="Buscar ruta por nombre"
                            className="pl-9"
                            placeholder="Buscar por nombre"
                            value={busqueda}
                            onChange={(evento) =>
                                setBusqueda(evento.target.value)
                            }
                        />
                    </form>

                    <Select
                        value={filtros.especialidad || TODAS}
                        onValueChange={(valor) =>
                            filtrar({
                                especialidad: valor === TODAS ? '' : valor,
                            })
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-56"
                            aria-label="Filtrar por especialidad"
                        >
                            <SelectValue placeholder="Especialidad" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODAS}>
                                Todas las especialidades
                            </SelectItem>
                            {especialidades.map((especialidad) => (
                                <SelectItem
                                    key={especialidad.id}
                                    value={String(especialidad.id)}
                                >
                                    {especialidad.nombre}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {hayFiltros && (
                        <Button type="button" variant="ghost" onClick={limpiar}>
                            <X className="size-4" />
                            Borrar filtros
                        </Button>
                    )}
                </div>

                <div
                    aria-busy={cargando}
                    className={cn(
                        'transition-opacity duration-200',
                        cargando && 'pointer-events-none opacity-60',
                    )}
                >
                    {/* La tabla, de tableta para arriba. */}
                    <div className="bg-card shadow-card hidden overflow-hidden rounded-xl border md:block">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Ruta
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Creada
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Cursos
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Autor
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {embudos.data.map((embudo) => (
                                    <tr
                                        key={embudo.id}
                                        className="hover:bg-accent/30 border-t transition-colors"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                href={show(embudo.id)}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {embudo.nombre}
                                            </Link>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                            {fechaCorta(embudo.creado_en)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {embudo.cursos}
                                            {embudo.cursos === 0 && (
                                                <span className="text-muted-foreground ml-2 text-xs">
                                                    fuera del selector
                                                </span>
                                            )}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {embudo.autor ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Ver ${embudo.nombre}`}
                                                    asChild
                                                >
                                                    <Link
                                                        href={show(embudo.id)}
                                                    >
                                                        <Eye className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Editar ${embudo.nombre}`}
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit(embudo.id)}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive"
                                                    aria-label={`Eliminar ${embudo.nombre}`}
                                                    onClick={() =>
                                                        setPorBorrar(embudo)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}

                                {vacia && (
                                    <tr className="border-t">
                                        <td
                                            colSpan={5}
                                            className="text-muted-foreground px-4 py-10 text-center"
                                        >
                                            {mensajeVacio}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Las mismas rutas, una por tarjeta, en el celular. */}
                    <ul className="flex flex-col gap-3 md:hidden">
                        {embudos.data.map((embudo) => (
                            <li
                                key={embudo.id}
                                className="bg-card shadow-card rounded-xl border p-4"
                            >
                                <Link
                                    href={show(embudo.id)}
                                    className="block font-medium break-words underline-offset-4 hover:underline"
                                >
                                    {embudo.nombre}
                                </Link>

                                <p className="text-muted-foreground mt-1 text-xs break-words">
                                    {embudo.cursos} curso
                                    {embudo.cursos === 1 ? '' : 's'}
                                    {embudo.cursos === 0 &&
                                        ' · fuera del selector'}
                                    {' · '}
                                    {fechaCorta(embudo.creado_en)}
                                    {embudo.autor && ` · ${embudo.autor}`}
                                </p>

                                <div className="mt-3 flex items-center justify-end gap-2 border-t pt-3">
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={show(embudo.id)}>
                                            <Eye className="size-4" />
                                            Ver
                                        </Link>
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={edit(embudo.id)}>
                                            <Pencil className="size-4" />
                                            Editar
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="text-destructive"
                                        aria-label={`Eliminar ${embudo.nombre}`}
                                        onClick={() => setPorBorrar(embudo)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </li>
                        ))}

                        {vacia && (
                            <li className="text-muted-foreground bg-card rounded-xl border border-dashed p-8 text-center text-sm">
                                {mensajeVacio}
                            </li>
                        )}
                    </ul>
                </div>

                {embudos.meta.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span className="text-muted-foreground">
                            Página {embudos.meta.current_page} de{' '}
                            {embudos.meta.last_page} · {embudos.meta.total}{' '}
                            rutas
                        </span>

                        <div className="flex gap-2 max-sm:w-full">
                            <Button
                                variant="outline"
                                size="sm"
                                className="max-sm:flex-1"
                                disabled={!embudos.links.prev}
                                onClick={() =>
                                    embudos.links.prev &&
                                    router.get(embudos.links.prev)
                                }
                            >
                                Anterior
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="max-sm:flex-1"
                                disabled={!embudos.links.next}
                                onClick={() =>
                                    embudos.links.next &&
                                    router.get(embudos.links.next)
                                }
                            >
                                Siguiente
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <Dialog
                open={porBorrar !== null}
                onOpenChange={(abierto) => !abierto && setPorBorrar(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            ¿Eliminar «{porBorrar?.nombre}»?
                        </DialogTitle>
                        <DialogDescription>
                            Desaparece del selector del asesor. Los documentos
                            que ya salieron no cambian: cada uno vive de su
                            propio snapshot, congelado el día que se generó.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPorBorrar(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (porBorrar) {
                                    router.delete(destroy(porBorrar.id).url, {
                                        onFinish: () => setPorBorrar(null),
                                    });
                                }
                            }}
                        >
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
