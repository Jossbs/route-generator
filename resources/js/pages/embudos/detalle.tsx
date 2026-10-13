import { Head, Link, router } from '@inertiajs/react';
import { Layers, Pencil } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PanelValidador } from '@/components/embudos/panel-validador';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { fechaCorta, horas } from '@/lib/embudos';
import { edit, index, update } from '@/routes/funnels';
import type {
    Curso,
    Embudo,
    Especialidad,
    PermisosEmbudo,
    Traslapes,
    Validacion,
} from '@/types/embudos';

type PageProps = {
    embudo: Embudo;
    catalogo: Curso[];
    especialidades: Especialidad[];
    traslapes: Traslapes;
    validacion: Validacion;
    permisos: PermisosEmbudo;
};

/**
 * La lectura de una ruta curada: qué lleva, en qué orden y qué dice el validador.
 *
 * Agrupada por especialidad porque es como se va a presentar en el documento:
 * cada una bajo su propio título, nunca revueltas.
 *
 * El validador trae aquí las mismas salidas que en el armador —corregir con una
 * sugerencia o ignorar y seguir—, pero como en la ficha no hay nada a medio
 * editar, cada una se guarda en el momento.
 */
export default function EmbudoDetalle({
    embudo,
    catalogo,
    especialidades,
    traslapes,
    validacion,
    permisos,
}: PageProps) {
    const porId = useMemo(
        () => new Map(catalogo.map((curso) => [curso.id, curso])),
        [catalogo],
    );

    const [guardando, setGuardando] = useState(false);

    const guardar = (cursos: number[], ignoradas: string[]) =>
        router.put(
            update(embudo.id).url,
            {
                name: embudo.nombre,
                items: cursos.map((id) => ({ course_id: id })),
                waived_rules: ignoradas,
            },
            {
                preserveScroll: true,
                onStart: () => setGuardando(true),
                onFinish: () => setGuardando(false),
            },
        );

    const ignoradas = embudo.reglas_ignoradas ?? [];

    const nombreEspecialidad = useMemo(
        () => new Map(especialidades.map((e) => [e.id, e.nombre])),
        [especialidades],
    );

    const bloques = useMemo(() => {
        const grupos = new Map<number, Curso[]>();

        for (const id of embudo.cursos) {
            const curso = porId.get(id);

            if (!curso) {
                continue;
            }

            const actuales = grupos.get(curso.especialidad_id) ?? [];
            actuales.push(curso);
            grupos.set(curso.especialidad_id, actuales);
        }

        return [...grupos.entries()];
    }, [embudo.cursos, porId]);

    return (
        <AppLayout>
            <Head title={embudo.nombre} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        overline="Embudos"
                        title={embudo.nombre}
                        description={`${embudo.cursos.length} curso${embudo.cursos.length === 1 ? '' : 's'} · armada el ${fechaCorta(embudo.creado_en)}${embudo.autor ? ` por ${embudo.autor}` : ''}`}
                        className="mb-0"
                    />

                    <div className="flex gap-2 max-sm:w-full">
                        <Button
                            variant="ghost"
                            asChild
                            className="max-sm:flex-1"
                        >
                            <Link href={index()}>Ver todas</Link>
                        </Button>
                        {permisos.editar && (
                            <Button asChild className="max-sm:flex-1">
                                <Link href={edit(embudo.id)}>
                                    <Pencil className="size-4" />
                                    Editar
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <div className="space-y-4">
                        {bloques.length === 0 && (
                            <p className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                                Esta ruta no tiene cursos todavía, así que no
                                aparece en el selector del asesor.
                            </p>
                        )}

                        {bloques.map(([especialidadId, cursos]) => (
                            <section
                                key={especialidadId}
                                className="bg-card shadow-card rounded-xl border p-4 sm:p-5"
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <h2 className="font-subtitle min-w-0 text-lg font-bold break-words">
                                        {nombreEspecialidad.get(
                                            especialidadId,
                                        ) ?? 'Sin especialidad'}
                                    </h2>
                                    <span className="text-muted-foreground text-xs">
                                        {cursos.length} curso
                                        {cursos.length === 1 ? '' : 's'}
                                        {especialidadId ===
                                            embudo.especialidad_principal_id &&
                                            ' · abre el documento'}
                                    </span>
                                </div>

                                <ul className="mt-3 space-y-2">
                                    {cursos.map((curso) => {
                                        const otros = traslapes[curso.id] ?? [];

                                        return (
                                            <li
                                                key={curso.id}
                                                className="flex items-start justify-between gap-3 border-t pt-2 text-sm first:border-0 first:pt-0"
                                            >
                                                <div className="min-w-0">
                                                    <p className="font-medium">
                                                        {curso.nombre}
                                                    </p>
                                                    {otros.length > 0 && (
                                                        <p className="mt-0.5 flex items-center gap-1 text-xs text-amber-700 dark:text-amber-300">
                                                            <Layers className="size-3 shrink-0" />
                                                            También en{' '}
                                                            {otros
                                                                .map(
                                                                    (e) =>
                                                                        e.nombre,
                                                                )
                                                                .join(' · ')}
                                                        </p>
                                                    )}
                                                </div>
                                                <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                                    {horas(curso.horas)}
                                                </span>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </section>
                        ))}
                    </div>

                    <PanelValidador
                        validacion={validacion}
                        catalogo={porId}
                        revalidando={guardando}
                        sinGuardar={false}
                        acciones={
                            permisos.editar
                                ? {
                                      onAplicar: (cursos) =>
                                          guardar(cursos, ignoradas),
                                      onIgnorar: (codigo) =>
                                          guardar(embudo.cursos, [
                                              ...ignoradas.filter(
                                                  (c) => c !== codigo,
                                              ),
                                              codigo,
                                          ]),
                                      onRestaurar: (codigo) =>
                                          guardar(
                                              embudo.cursos,
                                              ignoradas.filter(
                                                  (c) => c !== codigo,
                                              ),
                                          ),
                                  }
                                : undefined
                        }
                    />
                </div>
            </div>
        </AppLayout>
    );
}
