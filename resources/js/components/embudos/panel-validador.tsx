import {
    AlertTriangle,
    EyeOff,
    Loader2,
    RotateCcw,
    ShieldCheck,
    ShieldX,
    Wand2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Curso, Hallazgo, Validacion } from '@/types/embudos';

/** Lo que el armador deja hacer con un hallazgo. Sin ellas el panel solo informa. */
type Acciones = {
    onAplicar: (cursoIds: number[]) => void;
    onIgnorar: (codigo: string) => void;
    onRestaurar: (codigo: string) => void;
};

type Props = {
    validacion: Validacion;
    catalogo: Map<number, Curso>;
    revalidando: boolean;
    sinGuardar: boolean;
    acciones?: Acciones;
};

/**
 * El elemento central del armador.
 *
 * Con la rotación actual de campañas el volumen de rutas armadas a mano es alto, y
 * el criterio de quien redacta la promoción no puede ser la única defensa contra un
 * error que el lead va a leer con el nombre de la empresa encima. Por eso el panel
 * está siempre a la vista y no detrás de un botón «revisar». Nunca impide
 * guardar la ruta: lo que impide es emitir el documento, más adelante, con estas
 * mismas reglas.
 *
 * Cada hallazgo nombra los cursos involucrados en vez de dejar a la persona
 * buscándolos, y ofrece dos salidas: primero, cómo corregirlo con un clic; después,
 * ignorarlo y seguir con la ruta como está, para que no frene el documento. Las
 * ocho restricciones críticas del catálogo no traen la segunda.
 */
export function PanelValidador({
    validacion,
    catalogo,
    revalidando,
    sinGuardar,
    acciones,
}: Props) {
    const { bloqueantes, advertencias, totales } = validacion;
    const ignorados = validacion.ignorados ?? [];
    const limpio = bloqueantes.length === 0 && advertencias.length === 0;

    return (
        /*
         * De `lg` para arriba el panel acompaña el scroll, descontando el header
         * —que también es sticky— para no quedar debajo de él. Abajo de `lg` va
         * a ancho completo dentro del flujo: pegado taparía justo la lista que
         * se está editando.
         */
        <aside className="bg-card flex flex-col gap-4 rounded-xl border p-4 lg:sticky lg:top-[calc(var(--header-h)+1rem)] lg:max-h-[calc(100svh-var(--header-h)-2rem)] lg:overflow-y-auto">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">Validador</h3>
                {revalidando && (
                    <Loader2
                        className="text-muted-foreground size-4 animate-spin"
                        aria-label="Revalidando"
                    />
                )}
            </div>

            <dl className="grid grid-cols-3 gap-2 text-center">
                <Cifra etiqueta="Cursos" valor={totales.cursos} />
                <Cifra etiqueta="Programas" valor={totales.programas} />
                <Cifra etiqueta="Horas netas" valor={totales.horas_netas} />
            </dl>

            {totales.cursos !== totales.programas && (
                <p className="text-muted-foreground text-xs">
                    Los paquetes «Completo» contienen a sus niveles: las horas
                    ya van descontadas y los programas son el contenido
                    distinto.
                </p>
            )}

            {limpio && (
                <p className="flex items-start gap-2 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    {ignorados.length === 0
                        ? 'Sin observaciones. La ruta está lista para el asesor.'
                        : 'Lista para el asesor. Lo que ignoraste no frena el documento.'}
                </p>
            )}

            <Grupo
                titulo="Impide generar el documento"
                hallazgos={bloqueantes}
                catalogo={catalogo}
                icono={<ShieldX className="mt-0.5 size-4 shrink-0" />}
                clase="bg-destructive/10 text-destructive dark:text-red-300"
                acciones={acciones}
            />

            <Grupo
                titulo="Revísalo antes de mandarla"
                hallazgos={advertencias}
                catalogo={catalogo}
                icono={<AlertTriangle className="mt-0.5 size-4 shrink-0" />}
                clase="bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200"
                acciones={acciones}
            />

            <Grupo
                titulo="Ignorado en este embudo"
                hallazgos={ignorados}
                catalogo={catalogo}
                icono={<EyeOff className="mt-0.5 size-4 shrink-0" />}
                clase="bg-muted/60 text-muted-foreground"
                acciones={acciones}
                ignorado
            />

            {sinGuardar && (
                <p className="text-muted-foreground border-t pt-3 text-xs">
                    Este veredicto es sobre lo que tienes en pantalla. Guarda
                    para que quede en la ruta.
                </p>
            )}
        </aside>
    );
}

function Cifra({ etiqueta, valor }: { etiqueta: string; valor: number }) {
    return (
        <div className="bg-muted/50 rounded-lg px-2 py-3">
            <dt className="text-muted-foreground text-xs">{etiqueta}</dt>
            <dd className="text-lg font-semibold tabular-nums">{valor}</dd>
        </div>
    );
}

function Grupo({
    titulo,
    hallazgos,
    catalogo,
    icono,
    clase,
    acciones,
    ignorado = false,
}: {
    titulo: string;
    hallazgos: Hallazgo[];
    catalogo: Map<number, Curso>;
    icono: React.ReactNode;
    clase: string;
    acciones?: Acciones;
    ignorado?: boolean;
}) {
    if (hallazgos.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <h4 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                {titulo}
            </h4>

            {hallazgos.map((hallazgo) => (
                <div
                    key={hallazgo.codigo}
                    className={cn('flex gap-2 rounded-lg p-3 text-sm', clase)}
                >
                    {icono}
                    <div className="min-w-0 flex-1 space-y-1">
                        <p>{hallazgo.mensaje}</p>

                        {hallazgo.curso_ids.length > 0 && (
                            <p className="text-xs opacity-90">
                                {hallazgo.curso_ids
                                    .map(
                                        (id) =>
                                            catalogo.get(id)?.nombre ??
                                            `Curso ${id}`,
                                    )
                                    .join(' · ')}
                            </p>
                        )}

                        <p className="font-mono text-[0.65rem] opacity-70">
                            {hallazgo.codigo}
                        </p>

                        {acciones && (
                            <Salidas
                                hallazgo={hallazgo}
                                acciones={acciones}
                                ignorado={ignorado}
                            />
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

/**
 * Las dos salidas de un hallazgo, en el orden en que conviene tomarlas: corregir
 * la lista primero, ignorar después. Ignorar solo existe para lo que no es una
 * restricción crítica.
 */
function Salidas({
    hallazgo,
    acciones,
    ignorado,
}: {
    hallazgo: Hallazgo;
    acciones: Acciones;
    ignorado: boolean;
}) {
    if (ignorado) {
        return (
            <div className="pt-2">
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="h-7 px-2 text-xs"
                    onClick={() => acciones.onRestaurar(hallazgo.codigo)}
                >
                    <RotateCcw className="size-3.5" />
                    Volver a tomarlo en cuenta
                </Button>
            </div>
        );
    }

    const { sugerencias } = hallazgo;

    if (sugerencias.length === 0 && !hallazgo.ignorable) {
        return null;
    }

    return (
        <div className="space-y-2 pt-2">
            {sugerencias.length > 0 && (
                <div className="space-y-1.5">
                    <p className="text-xs font-medium">Cómo mejorarla</p>
                    <div className="flex flex-wrap gap-1.5">
                        {sugerencias.map((sugerencia) => (
                            <Button
                                key={sugerencia.etiqueta}
                                type="button"
                                size="sm"
                                variant="outline"
                                className="bg-background text-foreground h-auto min-h-7 px-2 py-1 text-left text-xs whitespace-normal"
                                onClick={() =>
                                    acciones.onAplicar(sugerencia.curso_ids)
                                }
                            >
                                <Wand2 className="size-3.5 shrink-0" />
                                {sugerencia.etiqueta}
                            </Button>
                        ))}
                    </div>
                </div>
            )}

            {hallazgo.ignorable ? (
                <div className="border-t border-current/15 pt-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-7 px-2 text-xs"
                        onClick={() => acciones.onIgnorar(hallazgo.codigo)}
                    >
                        <EyeOff className="size-3.5" />
                        Ignorar y seguir con esta ruta
                    </Button>
                </div>
            ) : (
                <p className="text-xs opacity-80">
                    Es una restricción crítica del catálogo: no se puede
                    ignorar.
                </p>
            )}
        </div>
    );
}
