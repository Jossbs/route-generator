import { Head, router, usePage } from '@inertiajs/react';
import { Info, ShieldX } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { BloquesPropuesta } from '@/components/rutas/bloques-propuesta';
import { ParrafosEditables } from '@/components/rutas/parrafos-editables';
import { Pasos } from '@/components/rutas/pasos';
import { Button } from '@/components/ui/button';
import { useRutaEditable } from '@/hooks/use-ruta-editable';
import { useValidacionInicial } from '@/hooks/use-validacion-inicial';
import AppLayout from '@/layouts/app-layout';
import { avisarErrores } from '@/lib/avisar-errores';
import { fieldErrors, jsonRequest } from '@/lib/json-request';
import {
    create,
    edit,
    store,
    update,
    validate,
    revalidate,
} from '@/routes/routes';
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
    advertencias: Bloqueo[];
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
 * El paso que convierte al asesor en autor del documento.
 *
 * Nada se ha guardado todavía: lo que se genera al final es lo que quedó en esta
 * pantalla, no lo que el motor propuso.
 */
export default function RutaPropuesta() {
    const {
        bloques,
        totales: totalesIniciales,
        bloqueos: bloqueosIniciales,
        advertencias: advertenciasIniciales,
        captura,
        catalogo,
        editando,
        parrafos,
        ajustes: ajustesIniciales,
    } = usePage<PageProps>().props;

    const {
        bloques: editables,
        fuera,
        fijos,
        finales,
        alternar,
        agregar,
        reemplazar,
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

    const body = JSON.stringify({ ...captura, course_ids: finales, ajustes });
    const validacion = useValidacionInicial(
        editando ? revalidate(editando.id).url : validate().url,
        body,
        {
            totales: totalesIniciales,
            bloqueos: bloqueosIniciales,
            advertencias: advertenciasIniciales,
        },
    );
    const { totales, bloqueos, advertencias } = validacion;
    const [errorEnvio, setErrorEnvio] = useState<{
        body: string;
        errores: Record<string, string>;
        mensaje?: string;
    } | null>(null);
    const errores = {
        ...(errorEnvio?.body === body ? errorEnvio.errores : {}),
        ...validacion.errores,
    };
    const erroresGenerales = Object.entries(errores).filter(
        ([key]) =>
            !key.startsWith('ajustes.') &&
            !(key === 'course_ids' && bloqueos.length > 0),
    );
    const erroresDeTexto = Object.keys(errores).some((key) =>
        key.startsWith('ajustes.'),
    );
    const puedeGenerar =
        validacion.listo &&
        bloqueos.length === 0 &&
        Object.keys(errores).length === 0 &&
        finales.length > 0;

    const generar = async () => {
        if (!puedeGenerar || generando) {
            return;
        }

        setGenerando(true);
        setErrorEnvio(null);

        try {
            const response = await jsonRequest(
                editando ? update(editando.id).url : store().url,
                body,
                editando ? 'PUT' : 'POST',
            );
            const data = (await response.json()) as {
                url?: string;
                errors?: Record<string, string | string[]>;
            };

            if (response.status === 422) {
                const errores = fieldErrors(data);
                setErrorEnvio({ body, errores });
                avisarErrores(errores);
                validacion.revalidar();
                setGenerando(false);

                return;
            }

            if (!response.ok || !data.url) {
                throw new Error('No se pudo generar el documento.');
            }

            router.visit(data.url, {
                onFinish: () => setGenerando(false),
            });
        } catch {
            setErrorEnvio({
                body,
                errores: {},
                mensaje:
                    'No se pudo completar la solicitud. Revisa la bandeja antes de volver a generar.',
            });
            setGenerando(false);
        }
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
                            {totales?.programas ??
                                (validacion.pendiente ? '…' : '—')}
                        </div>
                        <div className="text-muted-foreground text-xs">
                            programas distintos
                        </div>
                    </div>
                    <div className="bg-muted/50 rounded-lg px-3 py-3">
                        <div className="text-2xl font-semibold tabular-nums">
                            {totales?.horas_netas ??
                                (validacion.pendiente ? '…' : '—')}
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

                <p className="text-muted-foreground mb-6 text-sm">
                    Los adicionales son una propuesta inicial. Puedes agregar,
                    reemplazar, quitar y ordenar los que consideres adecuados,
                    sin un número obligatorio.
                </p>

                {bloqueos.length > 0 && (
                    <div
                        role="alert"
                        className="bg-destructive/10 text-destructive mb-6 space-y-2 rounded-xl p-4"
                    >
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

                {advertencias.length > 0 && (
                    <section className="mb-6 space-y-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <p className="flex items-center gap-2 font-medium">
                            <Info className="size-4 shrink-0" />
                            Advertencias: puedes continuar
                        </p>
                        <ul className="list-inside list-disc space-y-1 text-sm">
                            {advertencias.map((advertencia) => (
                                <li key={advertencia.codigo}>
                                    {advertencia.mensaje}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {validacion.pendiente && (
                    <p
                        role="status"
                        className="text-muted-foreground mb-4 text-sm"
                    >
                        Revisando cursos y textos…
                    </p>
                )}
                {validacion.error && (
                    <div
                        role="alert"
                        className="mb-4 space-y-2 rounded-xl border p-4 text-sm"
                    >
                        <p>{validacion.error}</p>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={validacion.revalidar}
                        >
                            Reintentar revisión
                        </Button>
                    </div>
                )}
                {(erroresGenerales.length > 0 ||
                    erroresDeTexto ||
                    (errorEnvio?.body === body && errorEnvio.mensaje)) && (
                    <div
                        role="alert"
                        className="bg-destructive/10 text-destructive mb-4 rounded-xl p-4 text-sm"
                    >
                        {errorEnvio?.body === body && errorEnvio.mensaje && (
                            <p>{errorEnvio.mensaje}</p>
                        )}
                        {erroresGenerales.map(([key, message]) => (
                            <p key={key}>{message}</p>
                        ))}
                        {erroresDeTexto && (
                            <p>Revisa los textos marcados abajo.</p>
                        )}
                    </div>
                )}

                <fieldset disabled={generando} className="min-w-0">
                    <BloquesPropuesta
                        bloques={editables}
                        catalogo={catalogo}
                        fuera={fuera}
                        onToggle={alternar}
                        onAgregar={agregar}
                        orden={orden}
                        onReemplazar={reemplazar}
                    />

                    <ParrafosEditables
                        parrafos={parrafos ?? []}
                        ajustes={ajustes}
                        onChange={setAjustes}
                        errores={errores}
                    />
                </fieldset>

                <div className="mt-8 flex flex-wrap items-center gap-3 border-t pt-6">
                    <Button
                        onClick={() => void generar()}
                        size="lg"
                        className="max-sm:w-full"
                        disabled={generando || !puedeGenerar}
                    >
                        {generando
                            ? 'Generando…'
                            : validacion.pendiente
                              ? 'Revisando…'
                              : editando
                                ? 'Reemplazar el PDF'
                                : 'Generar el PDF'}
                    </Button>
                    <span className="text-muted-foreground text-sm">
                        {validacion.pendiente
                            ? 'Estamos comprobando los cambios.'
                            : !puedeGenerar
                              ? 'Revisa los avisos antes de generar.'
                              : editando
                                ? 'El PDF anterior se reemplaza y queda disponible para descargarlo.'
                                : 'La ruta queda guardada. Puedes compartir el enlace o descargar el PDF desde su ficha.'}
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}
