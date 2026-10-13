import { Check, Layers, Plus, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { horas } from '@/lib/embudos';
import { cn } from '@/lib/utils';
import type { Curso, Especialidad, Traslapes } from '@/types/embudos';

type Props = {
    catalogo: Curso[];
    especialidades: Especialidad[];
    traslapes: Traslapes;
    elegidos: number[];
    resaltados: Set<number>;
    deshabilitado: boolean;
    onChange: (cursos: number[]) => void;
};

const TODAS = 'todas';

/**
 * Los cursos del embudo: el catálogo completo de un lado, lo elegido del otro.
 *
 * El buscador es lo primero porque son 64 cursos en 12 especialidades y armar una
 * rama a fuerza de bajar la lista es lo que hace que nadie arme embudos. Filtrar
 * por especialidad es el otro atajo: la estrategia es juntar lo que pertenece a
 * una rama, así que casi siempre se empieza por ahí.
 *
 * El orden se edita moviendo renglones y no arrastrando: se opera con teclado,
 * funciona en pantallas chicas y no suma dependencias. La posición no se captura,
 * se deriva del orden de la lista.
 */
export function SelectorCursos({
    catalogo,
    especialidades,
    traslapes,
    elegidos,
    resaltados,
    deshabilitado,
    onChange,
}: Props) {
    const [busqueda, setBusqueda] = useState('');
    const [especialidad, setEspecialidad] = useState(TODAS);

    const porId = useMemo(
        () => new Map(catalogo.map((curso) => [curso.id, curso])),
        [catalogo],
    );

    const nombreEspecialidad = useMemo(
        () => new Map(especialidades.map((e) => [e.id, e.nombre])),
        [especialidades],
    );

    const yaElegidos = new Set(elegidos);
    const termino = busqueda.trim().toLowerCase();

    const disponibles = catalogo
        .filter((curso) => !yaElegidos.has(curso.id))
        .filter(
            (curso) =>
                especialidad === TODAS ||
                String(curso.especialidad_id) === especialidad,
        )
        .filter(
            (curso) =>
                termino === '' ||
                curso.nombre.toLowerCase().includes(termino) ||
                (nombreEspecialidad.get(curso.especialidad_id) ?? '')
                    .toLowerCase()
                    .includes(termino),
        );

    const agregar = (curso: Curso) => onChange([...elegidos, curso.id]);
    const quitar = (cursoId: number) =>
        onChange(elegidos.filter((id) => id !== cursoId));

    const mover = (desde: number, hacia: number) => {
        if (hacia < 0 || hacia >= elegidos.length) {
            return;
        }

        const siguiente = [...elegidos];
        const [movido] = siguiente.splice(desde, 1);
        siguiente.splice(hacia, 0, movido);
        onChange(siguiente);
    };

    /** Toda la especialidad de un golpe: es como se arma una rama. */
    const agregarEspecialidad = () => {
        const nuevos = catalogo
            .filter(
                (curso) =>
                    String(curso.especialidad_id) === especialidad &&
                    !yaElegidos.has(curso.id),
            )
            .map((curso) => curso.id);

        onChange([...elegidos, ...nuevos]);
    };

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <section className="space-y-3">
                <div className="flex items-baseline justify-between gap-2">
                    <h3 className="text-sm font-semibold">
                        Cursos de esta ruta
                    </h3>
                    <span className="text-muted-foreground text-xs tabular-nums">
                        {elegidos.length} elegido
                        {elegidos.length === 1 ? '' : 's'}
                    </span>
                </div>

                {elegidos.length === 0 && (
                    <p className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">
                        Todavía no hay cursos. Elige los de la rama con la que
                        vas a enganchar al lead.
                    </p>
                )}

                <ol className="space-y-2">
                    {elegidos.map((cursoId, indice) => {
                        const curso = porId.get(cursoId);
                        const otros = traslapes[cursoId] ?? [];

                        return (
                            <li
                                key={cursoId}
                                className={cn(
                                    'bg-card flex items-start gap-2 rounded-lg border p-2.5 text-sm',
                                    resaltados.has(cursoId) &&
                                        'border-destructive/60 bg-destructive/5',
                                )}
                            >
                                <span className="text-muted-foreground w-5 shrink-0 pt-0.5 text-right text-xs tabular-nums">
                                    {indice + 1}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        {curso?.nombre ?? `Curso ${cursoId}`}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {curso
                                            ? nombreEspecialidad.get(
                                                  curso.especialidad_id,
                                              )
                                            : '—'}{' '}
                                        · {horas(curso?.horas ?? null)}
                                    </p>

                                    {otros.length > 0 && (
                                        <p className="mt-1 flex items-start gap-1 text-xs text-amber-700 dark:text-amber-300">
                                            <Layers className="mt-0.5 size-3 shrink-0" />
                                            <span>
                                                Ya está en{' '}
                                                {otros
                                                    .map((e) => e.nombre)
                                                    .join(' · ')}
                                            </span>
                                        </p>
                                    )}
                                </div>

                                <div className="flex shrink-0 gap-0.5">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="size-8 px-0"
                                        aria-label={`Subir ${curso?.nombre ?? cursoId}`}
                                        disabled={deshabilitado || indice === 0}
                                        onClick={() =>
                                            mover(indice, indice - 1)
                                        }
                                    >
                                        ↑
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="size-8 px-0"
                                        aria-label={`Bajar ${curso?.nombre ?? cursoId}`}
                                        disabled={
                                            deshabilitado ||
                                            indice === elegidos.length - 1
                                        }
                                        onClick={() =>
                                            mover(indice, indice + 1)
                                        }
                                    >
                                        ↓
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8"
                                        aria-label={`Quitar ${curso?.nombre ?? cursoId}`}
                                        disabled={deshabilitado}
                                        onClick={() => quitar(cursoId)}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </section>

            <section className="space-y-3">
                <div className="flex items-baseline justify-between gap-2">
                    <h3 className="text-sm font-semibold">Catálogo</h3>
                    <span className="text-muted-foreground text-xs tabular-nums">
                        {disponibles.length} disponible
                        {disponibles.length === 1 ? '' : 's'}
                    </span>
                </div>

                <div className="flex flex-wrap gap-2">
                    <div className="relative min-w-full flex-1 sm:min-w-48">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            className="pl-9"
                            placeholder="Buscar curso"
                            aria-label="Buscar curso en el catálogo"
                            value={busqueda}
                            onChange={(event) =>
                                setBusqueda(event.target.value)
                            }
                        />
                    </div>

                    <Select
                        value={especialidad}
                        onValueChange={setEspecialidad}
                    >
                        <SelectTrigger
                            className="w-full sm:w-52"
                            aria-label="Filtrar por especialidad"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODAS}>
                                Todas las especialidades
                            </SelectItem>
                            {especialidades.map((e) => (
                                <SelectItem key={e.id} value={String(e.id)}>
                                    {e.nombre}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {especialidad !== TODAS && !deshabilitado && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="max-sm:w-full"
                        onClick={agregarEspecialidad}
                        disabled={disponibles.length === 0}
                    >
                        <Plus className="size-4" />
                        Agregar la especialidad completa
                    </Button>
                )}

                <ul className="max-h-[28rem] space-y-1 overflow-y-auto pr-1">
                    {disponibles.map((curso) => {
                        const otros = traslapes[curso.id] ?? [];

                        return (
                            <li key={curso.id}>
                                <button
                                    type="button"
                                    disabled={deshabilitado}
                                    onClick={() => agregar(curso)}
                                    className="hover:bg-accent flex w-full items-start gap-2 rounded-lg border p-2.5 text-left text-sm disabled:opacity-50"
                                >
                                    <Plus className="text-muted-foreground mt-0.5 size-4 shrink-0" />

                                    <span className="min-w-0 flex-1">
                                        <span className="block font-medium">
                                            {curso.nombre}
                                        </span>
                                        <span className="text-muted-foreground block text-xs">
                                            {nombreEspecialidad.get(
                                                curso.especialidad_id,
                                            )}{' '}
                                            · {horas(curso.horas)}
                                        </span>

                                        {otros.length > 0 && (
                                            <span className="mt-1 block text-xs text-amber-700 dark:text-amber-300">
                                                Ya está en{' '}
                                                {otros
                                                    .map((e) => e.nombre)
                                                    .join(' · ')}
                                            </span>
                                        )}
                                    </span>

                                    {curso.revisar_duracion && (
                                        <Badge
                                            variant="outline"
                                            className="shrink-0 text-[0.65rem]"
                                            title="Sus horas están en disputa entre fuentes"
                                        >
                                            horas en disputa
                                        </Badge>
                                    )}
                                    {curso.revisar_traslape && (
                                        <Badge
                                            variant="outline"
                                            className="shrink-0 text-[0.65rem]"
                                            title="Traslape de contenido sospechado, sin confirmar"
                                        >
                                            traslape
                                        </Badge>
                                    )}
                                </button>
                            </li>
                        );
                    })}

                    {disponibles.length === 0 && (
                        <li className="text-muted-foreground rounded-lg border border-dashed p-6 text-center text-sm">
                            {elegidos.length === catalogo.length ? (
                                <span className="flex items-center justify-center gap-2">
                                    <Check className="size-4" />
                                    Ya elegiste todo el catálogo.
                                </span>
                            ) : (
                                'No hay cursos que coincidan.'
                            )}
                        </li>
                    )}
                </ul>
            </section>
        </div>
    );
}
