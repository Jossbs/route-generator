import { router } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { PanelValidador } from '@/components/embudos/panel-validador';
import { SelectorCursos } from '@/components/embudos/selector-cursos';
import { EtiquetaCampo } from '@/components/etiqueta-campo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    destroy,
    store,
    update,
    validate,
    validateNew,
} from '@/routes/funnels';
import { avisarErrores } from '@/lib/avisar-errores';
import type {
    Curso,
    Embudo,
    Especialidad,
    PermisosEmbudo,
    Traslapes,
    Validacion,
} from '@/types/embudos';

type Props = {
    embudo: Embudo | null;
    catalogo: Curso[];
    especialidades: Especialidad[];
    traslapes: Traslapes;
    validacion: Validacion | null;
    validacionEnVivo?: Validacion;
    permisos: PermisosEmbudo;
};

/**
 * Armar un embudo: un nombre y los cursos que lo componen.
 *
 * Eso es todo lo que se captura. El identificador se deriva del nombre y la
 * especialidad que abre la ruta se deriva de los cursos, porque las dos cosas
 * salen de ahí de todos modos y pedirlas sería pedirle a alguien que repita lo que
 * ya dijo.
 *
 * El validador va al lado y nunca impide guardar: un embudo a medio armar es un
 * borrador legítimo. Lo que sí impide es emitir el documento, y eso ocurre más
 * adelante, con el mismo validador y las mismas reglas. Salvo lo que aquí se
 * ignore: ignorar es parte del embudo, se guarda con él y el documento lo respeta.
 */
export function ArmadorEmbudo({
    embudo,
    catalogo,
    especialidades,
    traslapes,
    validacion,
    validacionEnVivo,
    permisos,
}: Props) {
    const [nombre, setNombre] = useState(embudo?.nombre ?? '');
    const [cursos, setCursos] = useState<number[]>(embudo?.cursos ?? []);
    const [ignoradas, setIgnoradas] = useState<string[]>(
        embudo?.reglas_ignoradas ?? [],
    );
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);
    const [revalidando, setRevalidando] = useState(false);
    const [confirmandoBorrado, setConfirmandoBorrado] = useState(false);

    const guardados = useMemo(
        () => JSON.stringify(embudo?.cursos ?? []),
        [embudo],
    );
    const sinGuardar =
        JSON.stringify(cursos) !== guardados ||
        nombre !== (embudo?.nombre ?? '') ||
        JSON.stringify([...ignoradas].sort()) !==
            JSON.stringify([...(embudo?.reglas_ignoradas ?? [])].sort());

    const porId = useMemo(
        () => new Map(catalogo.map((curso) => [curso.id, curso])),
        [catalogo],
    );

    // Al crear, una lista vacía no tiene veredicto: el último que llegó sería
    // de cursos que ya no están.
    const veredicto =
        embudo === null && cursos.length === 0
            ? null
            : (validacionEnVivo ?? validacion);

    const resaltados = new Set(
        (veredicto?.bloqueantes ?? [])
            .concat(veredicto?.advertencias ?? [])
            .flatMap((hallazgo) => hallazgo.curso_ids),
    );

    /*
     * El veredicto se pide mientras se mueven cursos, no después de guardar. Va
     * con retardo para no disparar una petición por clic. También al crear: las
     * sugerencias sirven más mientras se arma la lista que después de guardarla.
     */
    const primeraCarga = useRef(true);

    useEffect(() => {
        if (embudo === null && cursos.length === 0) {
            return;
        }

        if (primeraCarga.current && embudo !== null) {
            primeraCarga.current = false;

            return;
        }

        const temporizador = window.setTimeout(() => {
            setRevalidando(true);

            router.post(
                embudo === null ? validateNew().url : validate(embudo.id).url,
                {
                    // La especialidad que abre la ruta la deriva el servidor de
                    // estos cursos, igual que al guardar.
                    items: cursos.map((id) => ({ course_id: id })),
                    copy_text: [nombre],
                    waived_rules: ignoradas,
                },
                {
                    only: ['validacionEnVivo'],
                    preserveState: true,
                    preserveScroll: true,
                    onFinish: () => setRevalidando(false),
                },
            );
        }, 400);

        return () => window.clearTimeout(temporizador);
    }, [cursos, nombre, ignoradas, embudo]);

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        const datos = {
            name: nombre,
            items: cursos.map((id) => ({ course_id: id })),
            waived_rules: ignoradas,
        };

        const opciones = {
            onStart: () => setGuardando(true),
            onFinish: () => setGuardando(false),
            onError: (nuevos: Record<string, string>) => {
                setErrores(nuevos);
                avisarErrores(nuevos);
            },
        };

        if (embudo === null) {
            router.post(store().url, datos, opciones);

            return;
        }

        router.put(update(embudo.id).url, datos, opciones);
    };

    const eliminar = () => {
        if (embudo === null) {
            return;
        }

        router.delete(destroy(embudo.id).url, {
            onFinish: () => setConfirmandoBorrado(false),
        });
    };

    const horasElegidas = cursos.reduce(
        (total, id) => total + (porId.get(id)?.horas ?? 0),
        0,
    );

    return (
        <form
            onSubmit={guardar}
            noValidate
            // El hueco de abajo es el alto de la barra de guardado, que en
            // pantallas chicas va fija al pie y si no taparía el último renglón.
            className="grid gap-6 max-lg:pb-24 lg:grid-cols-[minmax(0,1fr)_20rem]"
        >
            <div className="space-y-6">
                <div className="bg-card shadow-card space-y-2 rounded-xl border p-4 sm:p-6">
                    <EtiquetaCampo htmlFor="name" requerido>
                        ¿Cómo se llama esta ruta?
                    </EtiquetaCampo>
                    <Input
                        id="name"
                        name="name"
                        required
                        aria-invalid={!!errores.name || undefined}
                        autoFocus={embudo === null}
                        autoComplete="off"
                        className="max-w-lg"
                        placeholder="Ej. Calidad y mejora continua"
                        value={nombre}
                        disabled={!permisos.editar}
                        onChange={(evento) => setNombre(evento.target.value)}
                    />
                    <p className="sys-ayuda text-muted-foreground text-xs">
                        Es lo que el asesor va a buscar en su selector al
                        generar el documento de un lead.
                    </p>
                    <InputError message={errores.name} />
                </div>

                <div className="bg-card shadow-card space-y-4 rounded-xl border p-4 sm:p-6">
                    <SelectorCursos
                        catalogo={catalogo}
                        especialidades={especialidades}
                        traslapes={traslapes}
                        elegidos={cursos}
                        resaltados={resaltados}
                        deshabilitado={!permisos.editar}
                        onChange={setCursos}
                    />
                    <InputError message={errores.items} />
                </div>
            </div>

            <aside className="space-y-4 max-lg:contents">
                <div className="max-lg:order-1 max-lg:space-y-4">
                    {veredicto !== null && (
                        <PanelValidador
                            validacion={veredicto}
                            catalogo={porId}
                            revalidando={revalidando}
                            sinGuardar={sinGuardar}
                            acciones={
                                permisos.editar
                                    ? {
                                          onAplicar: setCursos,
                                          onIgnorar: (codigo) =>
                                              setIgnoradas((actuales) =>
                                                  actuales.includes(codigo)
                                                      ? actuales
                                                      : [...actuales, codigo],
                                              ),
                                          onRestaurar: (codigo) =>
                                              setIgnoradas((actuales) =>
                                                  actuales.filter(
                                                      (c) => c !== codigo,
                                                  ),
                                              ),
                                      }
                                    : undefined
                            }
                        />
                    )}

                    {veredicto === null && (
                        <div className="bg-card text-muted-foreground rounded-xl border p-4 text-sm">
                            <p className="text-foreground font-semibold">
                                {cursos.length} curso
                                {cursos.length === 1 ? '' : 's'} ·{' '}
                                {horasElegidas} h
                            </p>
                            <p className="mt-2 text-xs">
                                El validador revisa esta lista en cuanto la
                                guardes: traslapes de liderazgo, secuencia de
                                niveles y las demás restricciones del catálogo.
                            </p>
                        </div>
                    )}
                </div>

                {permisos.editar && (
                    <div className="max-lg:bg-background/92 max-lg:border-border max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-30 max-lg:border-t max-lg:px-4 max-lg:py-3 max-lg:backdrop-blur sm:max-lg:px-8">
                        <Button
                            type="submit"
                            className="w-full"
                            size="lg"
                            disabled={guardando || nombre.trim() === ''}
                        >
                            <Save className="size-4" />
                            {embudo === null
                                ? 'Crear la ruta'
                                : 'Guardar cambios'}
                        </Button>
                    </div>
                )}

                {permisos.eliminar && embudo !== null && (
                    <Dialog
                        open={confirmandoBorrado}
                        onOpenChange={setConfirmandoBorrado}
                    >
                        {/* Al final de todo en el celular: eliminar no compite
                        con guardar. */}
                        <DialogTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive w-full max-lg:order-3"
                            >
                                <Trash2 className="size-4" />
                                Eliminar esta ruta
                            </Button>
                        </DialogTrigger>

                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    ¿Eliminar «{embudo.nombre}»?
                                </DialogTitle>
                                <DialogDescription>
                                    Desaparece del selector del asesor. Los
                                    documentos que ya salieron no cambian: cada
                                    uno vive de su propio snapshot, congelado el
                                    día que se generó.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setConfirmandoBorrado(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={eliminar}
                                >
                                    Eliminar
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </aside>
        </form>
    );
}
