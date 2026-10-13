import { Head, router, usePage } from '@inertiajs/react';
import { Info, ShieldX } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { BloquesPropuesta } from '@/components/rutas/bloques-propuesta';
import { ParrafosEditables } from '@/components/rutas/parrafos-editables';
import { Pasos } from '@/components/rutas/pasos';
import { Button } from '@/components/ui/button';
import { useRutaEditable } from '@/hooks/use-ruta-editable';
import AppLayout from '@/layouts/app-layout';
import { avisarErrores } from '@/lib/avisar-errores';
import { create, edit, store, update } from '@/routes/routes';
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
    captura: Record<string, string | number | null>;
    catalogo: CursoDelCatalogo[];
    /** Los párrafos que el asesor puede ajustar, con su texto de catálogo. */
    parrafos: ParrafoEditable[];
    /** Lo que ya había ajustado, si esto corrige un documento que salió. */
    ajustes: AjustesDeTexto;
    errors: Record<string, string | undefined>;
    /** Presente = esto pisa un documento que ya salió. */
    editando: RutaEditandose | null;
};

/**
 * Cuántos recomienda el motor, y también el techo.
 *
 * Son dos, nunca más: es lo que el asesor puede defender uno por uno en la
 * conversación, y los que el guión del audio le presenta al lead. Ver
 * App\Services\RouteComposition\Support\RecommendedTopUp.
 */
const MINIMO_RECOMENDADOS = 2;

const MAXIMO_RECOMENDADOS = 2;

/**
 * El paso que convierte al asesor en autor del documento.
 *
 * Nada se ha guardado todavía: lo que se genera al final es lo que quedó en esta
 * pantalla, no lo que el motor propuso.
 */
export default function RutaPropuesta() {
    const {
        bloques,
        totales,
        bloqueos,
        captura,
        catalogo,
        editando,
        parrafos,
        ajustes: ajustesIniciales,
        errors,
    } = usePage<PageProps>().props;

    const {
        bloques: editables,
        fuera,
        fijos,
        finales,
        alternar,
        agregar,
        orden,
    } = useRutaEditable(bloques);
    const [generando, setGenerando] = useState(false);
    const [ajustes, setAjustes] = useState<AjustesDeTexto>(
        ajustesIniciales ?? {},
    );

    // Los recomendados son todo lo que no vino del embudo. Se cuentan aparte
    // porque son la única parte que el asesor decide: de la ruta principal no
    // puede quitar nada, así que contarla aquí escondería si podó de más.
    const recomendados = finales.filter((id) => !fijos.has(id)).length;

    const generar = () => {
        setGenerando(true);

        const datos = { ...captura, course_ids: finales, ajustes };
        const fin = {
            onFinish: () => setGenerando(false),
            onError: avisarErrores,
        };

        // Corregir pisa el documento anterior en vez de emitir otro: la bandeja
        // lleva un renglón por documento, y dos rutas del mismo lead en el mismo
        // día son dos versiones que nadie sabe cuál mandó.
        if (editando) {
            router.put(update(editando.id).url, datos, fin);

            return;
        }

        router.post(store().url, datos, fin);
    };

    return (
        <AppLayout>
            <Head title="Cursos propuestos" />

            <div className="mx-auto flex w-full max-w-4xl flex-col">
                <Pasos
                    volver={
                        editando
                            ? { title: 'Datos', href: edit(editando.id) }
                            : { title: 'Captura', href: create() }
                    }
                    actual="Cursos propuestos"
                    siguientes={[
                        editando ? 'PDF corregido' : 'PDF para mandarle',
                    ]}
                />

                <Heading
                    title={`La ruta de ${String(captura.name ?? '').split(' ')[0] || 'el lead'}`}
                    description={
                        bloqueos.length > 0
                            ? 'Hay algo que no puede ir — te lo marqué abajo.'
                            : `Arriba va la ruta de la campaña completa, ${fijos.size} cursos que no se tocan. Abajo lo que le recomiendo por su área y por lo que te dijo: eso sí lo quitas, lo cambias o lo acomodas.`
                    }
                />

                <div className="mb-6 grid grid-cols-2 gap-3 text-center sm:grid-cols-4">
                    <div className="bg-muted/50 rounded-lg px-3 py-3">
                        <div className="text-2xl font-semibold tabular-nums">
                            {finales.length}
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
                    <div className="bg-muted/50 rounded-lg px-3 py-3">
                        <div className="text-2xl font-semibold tabular-nums">
                            {recomendados}
                        </div>
                        <div className="text-muted-foreground text-xs">
                            recomendados
                        </div>
                    </div>
                </div>

                {/*
                 * Menos de dos recomendados deja al documento siendo la campaña
                 * tal cual. No bloquea —el asesor sabe algo que el motor no— pero
                 * tampoco pasa callado: quitar de más es fácil y nadie lo nota
                 * hasta que el lead lo abre.
                 */}
                {bloqueos.length === 0 &&
                    recomendados < MINIMO_RECOMENDADOS && (
                        <div className="bg-muted/60 text-muted-foreground mb-6 flex items-start gap-2 rounded-xl p-4 text-sm">
                            <Info className="mt-0.5 size-4 shrink-0" />
                            <span>
                                {recomendados === 0
                                    ? 'No queda ningún curso recomendado.'
                                    : 'Queda un solo curso recomendado.'}{' '}
                                Con menos de {MINIMO_RECOMENDADOS} el documento
                                es la campaña tal cual, sin nada que demuestre
                                que miraste su caso: agrega alguno o devuelve
                                los que quitaste.
                            </span>
                        </div>
                    )}

                {bloqueos.length > 0 && (
                    <div className="bg-destructive/10 text-destructive mb-6 space-y-2 rounded-xl p-4">
                        <p className="flex items-center gap-2 font-medium">
                            <ShieldX className="size-4" />
                            Esto impide generar el documento
                        </p>
                        <ul className="list-inside list-disc space-y-1 text-sm">
                            {bloqueos.map((bloqueo) => (
                                <li key={bloqueo.codigo}>{bloqueo.mensaje}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <BloquesPropuesta
                    bloques={editables}
                    catalogo={catalogo}
                    fuera={fuera}
                    onToggle={alternar}
                    onAgregar={agregar}
                    orden={orden}
                    lleno={recomendados >= MAXIMO_RECOMENDADOS}
                />

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
                            bloqueos.length > 0 ||
                            finales.length === 0
                        }
                    >
                        {generando
                            ? 'Generando…'
                            : editando
                              ? 'Reemplazar el PDF'
                              : 'Generar el PDF'}
                    </Button>
                    <span className="text-muted-foreground text-sm">
                        {bloqueos.length > 0
                            ? 'Resuelve lo de arriba y se activa.'
                            : editando
                              ? 'El PDF anterior se reemplaza y el nuevo se descarga solo.'
                              : 'Se descarga solo en cuanto esté, listo para adjuntarlo en Kommo.'}
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}
