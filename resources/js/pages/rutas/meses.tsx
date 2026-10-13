import { Head, router, usePage } from '@inertiajs/react';
import { ShieldX, Undo2, Wand2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { BloquesPropuesta } from '@/components/rutas/bloques-propuesta';
import { ParrafosEditables } from '@/components/rutas/parrafos-editables';
import { Pasos } from '@/components/rutas/pasos';
import { Button } from '@/components/ui/button';
import { useSecuenciaAnual } from '@/hooks/use-secuencia-anual';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { avisarErrores } from '@/lib/avisar-errores';
import { create, edit } from '@/routes/routes';
import final from '@/routes/routes/final';
import type {
    AjustesDeTexto,
    Bloqueo,
    BloquePropuesto,
    CursoDelCatalogo,
    ParrafoEditable,
    RutaEditandose,
    TotalesRuta,
} from '@/types/rutas';

type PageProps = {
    bloques: BloquePropuesto[];
    totales: TotalesRuta;
    bloqueos: Bloqueo[];
    curso_ids: number[];
    /** Cuánto del año del miembro ocupa lo que se ve. Nulo sin horas capturadas. */
    ritmo: {
        horas_semana: number;
        capacidad: number;
        horas: number;
    } | null;
    captura: Record<string, string | number | null>;
    catalogo: CursoDelCatalogo[];
    /** Los párrafos que el asesor puede ajustar, con su texto de catálogo. */
    parrafos: ParrafoEditable[];
    /** Lo que ya había ajustado: viaja en cada vuelta de la secuencia. */
    ajustes: AjustesDeTexto;
    errors: Record<string, string | undefined>;
    /** Presente = esto pisa el documento anual que ya se entregó. */
    editando: RutaEditandose | null;
};

/**
 * El paso intermedio de la ruta final: el recorrido de los doce meses, antes de
 * dar de alta la membresía y generar el documento.
 *
 * Lo que se ve aquí es el itinerario recomendado, que es lo único que el asesor
 * ajusta. El inventario de la membresía —el catálogo completo, que también va
 * impreso— no se propone ni se poda: el miembro lo tiene lo lea o no, y quitarle
 * un renglón aquí daría a entender que se lo quitamos.
 *
 * El asesor quita, agrega y ordena; el motor decide dónde termina cada mes. Cada
 * ajuste vuelve al servidor, así que el mes que se ve es siempre el que va a
 * salir impreso. Si el orden pone un curso antes de lo que necesita, el
 * validador lo bloquea y la pantalla ofrece acomodarlo con un clic.
 *
 * Decidido el 2026-09-22; antes el orden era solo del motor.
 */
export default function RutaMeses() {
    const {
        bloques,
        totales,
        bloqueos,
        captura,
        catalogo,
        editando,
        ritmo,
        parrafos,
        ajustes: ajustesIniciales,
        errors,
    } = usePage<PageProps>().props;

    const [generando, setGenerando] = useState(false);
    const [ajustes, setAjustes] = useState<AjustesDeTexto>(
        ajustesIniciales ?? {},
    );
    const [recortando, setRecortando] = useState(false);

    /** La secuencia que el asesor acaba de dejar, de vuelta al motor. */
    const reproponer = (cursoIds: number[]) =>
        router.post(
            editando ? final.repropose(editando.id).url : final.propose().url,
            { ...captura, course_ids: cursoIds, ajustes },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onStart: () => setRecortando(true),
                onFinish: () => setRecortando(false),
            },
        );

    const { secuencia, quitados, quitar, devolver, agregar, arreglar, orden } =
        useSecuenciaAnual(bloques, reproponer, recortando);

    const generar = () => {
        setGenerando(true);

        const datos = { ...captura, course_ids: secuencia, ajustes };
        const fin = {
            onFinish: () => setGenerando(false),
            onError: avisarErrores,
        };

        // Corregir pisa el documento anterior y su membresía: la venta fue una, y
        // el expediente del miembro no debe contarla dos veces.
        if (editando) {
            router.put(final.update(editando.id).url, datos, fin);

            return;
        }

        router.post(final.store().url, datos, fin);
    };

    return (
        <AppLayout>
            <Head title="Los doce meses" />

            <div className="mx-auto flex w-full max-w-4xl flex-col">
                <Pasos
                    volver={
                        editando
                            ? { title: 'Datos', href: edit(editando.id) }
                            : {
                                  title: 'Captura',
                                  href: create({ query: { tipo: 'final' } }),
                              }
                    }
                    actual="Los doce meses"
                    siguientes={[
                        editando ? 'PDF corregido' : 'PDF para descargar',
                    ]}
                />

                <Heading
                    title={`El año de ${String(captura.full_name ?? '').split(' ')[0] || 'el miembro'}`}
                    description="Su membresía incluye todo el catálogo; esto es lo que le recomendamos recorrer. Quita, agrega o acomoda con las flechas: cada mes se llena con las horas que tiene por semana."
                />

                <div className="mb-6 grid grid-cols-3 gap-3 text-center">
                    <div className="bg-muted/50 rounded-lg px-3 py-3">
                        <div className="text-2xl font-semibold tabular-nums">
                            {secuencia.length}
                        </div>
                        <div className="text-muted-foreground text-xs">
                            cursos
                        </div>
                    </div>
                    <div className="bg-muted/50 rounded-lg px-3 py-3">
                        <div className="text-2xl font-semibold tabular-nums">
                            {totales.programas}
                        </div>
                        <div className="text-muted-foreground text-xs">
                            programas distintos
                        </div>
                    </div>
                    <div className="bg-muted/50 rounded-lg px-3 py-3">
                        <div className="text-2xl font-semibold tabular-nums">
                            {totales.horas_netas}
                        </div>
                        <div className="text-muted-foreground text-xs">
                            horas netas
                        </div>
                    </div>
                </div>

                {ritmo && (
                    <div className="mb-6 space-y-1.5">
                        <div className="flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                            <span>
                                A {ritmo.horas_semana} h por semana caben{' '}
                                {ritmo.capacidad} h en su año.
                            </span>
                            <span
                                className={cn(
                                    'tabular-nums',
                                    ritmo.horas > ritmo.capacidad
                                        ? 'text-destructive font-medium'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {ritmo.horas > ritmo.capacidad
                                    ? `Sobran ${ritmo.horas - ritmo.capacidad} h`
                                    : `Usa ${ritmo.horas} de ${ritmo.capacidad} h`}
                            </span>
                        </div>
                        <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                            <div
                                className={cn(
                                    'h-full rounded-full',
                                    ritmo.horas > ritmo.capacidad
                                        ? 'bg-destructive'
                                        : 'bg-primary',
                                )}
                                style={{
                                    width: `${Math.min(100, (ritmo.horas / Math.max(1, ritmo.capacidad)) * 100)}%`,
                                }}
                            />
                        </div>
                    </div>
                )}

                {bloqueos.length > 0 && (
                    <div className="bg-destructive/10 text-destructive mb-6 space-y-2 rounded-xl p-4">
                        <p className="flex items-center gap-2 font-medium">
                            <ShieldX className="size-4" />
                            Esto impide generar el documento
                        </p>
                        <ul className="space-y-2 text-sm">
                            {bloqueos.map((bloqueo) => (
                                <li
                                    key={bloqueo.codigo}
                                    className="flex flex-wrap items-center gap-x-3 gap-y-2"
                                >
                                    <span className="min-w-0 flex-1">
                                        {bloqueo.mensaje}
                                    </span>
                                    {bloqueo.arreglo &&
                                        bloqueo.arreglo.length > 0 && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={recortando}
                                                onClick={() =>
                                                    arreglar(bloqueo.arreglo!)
                                                }
                                            >
                                                <Wand2 className="size-4" />
                                                Acomodar
                                            </Button>
                                        )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <BloquesPropuesta
                    bloques={bloques}
                    catalogo={catalogo}
                    fuera={[]}
                    onToggle={quitar}
                    onAgregar={agregar}
                    orden={orden}
                />

                {quitados.length > 0 && (
                    <div className="mt-6 space-y-2">
                        <p className="text-muted-foreground text-sm">
                            Quitados ({quitados.length}). Siguen incluidos en su
                            membresía; solo no van en esta hoja.
                        </p>
                        <ul className="divide-y rounded-lg border">
                            {quitados.map((curso) => (
                                <li
                                    key={curso.id}
                                    className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm"
                                >
                                    <span className="min-w-0 flex-1 break-words line-through">
                                        {curso.nombre}
                                    </span>
                                    <span className="shrink-0 text-xs">
                                        {curso.especialidad}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8"
                                        disabled={recortando}
                                        aria-label={`Devolver ${curso.nombre}`}
                                        onClick={() => devolver(curso.id)}
                                    >
                                        <Undo2 className="size-4" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <ParrafosEditables
                    parrafos={parrafos ?? []}
                    ajustes={ajustes}
                    onChange={setAjustes}
                    errores={errors ?? {}}
                />

                <div className="mt-8 flex flex-wrap items-center gap-3 border-t pt-6">
                    <Button
                        onClick={generar}
                        size="lg"
                        className="max-sm:w-full"
                        disabled={
                            generando ||
                            recortando ||
                            bloqueos.length > 0 ||
                            secuencia.length === 0
                        }
                    >
                        {editando ? 'Reemplazar el PDF' : 'Generar el PDF'}
                    </Button>
                    <span className="text-muted-foreground text-sm">
                        {bloqueos.length > 0
                            ? 'Resuelve lo de arriba y se activa.'
                            : secuencia.length === 0
                              ? 'Quitaste todo: el itinerario no puede ir vacío.'
                              : editando
                                ? 'El PDF anterior se reemplaza y el nuevo se descarga solo.'
                                : 'El PDF se descarga solo en cuanto está listo.'}
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}
