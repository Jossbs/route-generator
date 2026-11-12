import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type LecturaDocumento = {
    visitas: number;
    avance: number;
    seccion_mas_lejana: string | null;
    segundos: number;
    ultima_actividad: string | null;
    ultima_seccion: string | null;
    secciones: { id: string; nombre: string; segundos: number }[];
    clics: { nombre: string; visitas: number }[];
    sugerencia: string;
};

function tiempo(segundos: number) {
    return segundos < 60
        ? `${segundos} s`
        : `${Math.floor(segundos / 60)} min ${segundos % 60} s`;
}

export function SeguimientoLectura({ lectura }: { lectura: LecturaDocumento }) {
    return (
        <section className="bg-card space-y-4 rounded-xl border p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-subtitle text-lg font-bold">
                    Seguimiento del enlace
                </h2>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => router.reload({ only: ['lectura'] })}
                >
                    <RefreshCw className="size-4" /> Actualizar
                </Button>
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <div>
                    <p className="text-muted-foreground text-xs">
                        Visitas con actividad
                    </p>
                    <p className="text-xl font-bold">{lectura.visitas}</p>
                </div>
                <div>
                    <p className="text-muted-foreground text-xs">
                        Avance máximo
                    </p>
                    <p className="text-xl font-bold">{lectura.avance}%</p>
                </div>
                <div>
                    <p className="text-muted-foreground text-xs">
                        Tiempo activo
                    </p>
                    <p className="text-xl font-bold">
                        {tiempo(lectura.segundos)}
                    </p>
                </div>
            </div>
            <progress
                className="h-2 w-full"
                max={100}
                value={lectura.avance}
                aria-label="Avance máximo en el documento"
            />
            {lectura.seccion_mas_lejana && (
                <p className="text-sm">
                    Llegó hasta: <strong>{lectura.seccion_mas_lejana}</strong>.
                </p>
            )}
            {lectura.ultima_actividad && (
                <p className="text-muted-foreground text-sm">
                    Última actividad:{' '}
                    {new Date(lectura.ultima_actividad).toLocaleString('es-MX')}
                    .
                    {lectura.ultima_seccion &&
                        ` Última sección vista: ${lectura.ultima_seccion}.`}
                </p>
            )}
            {lectura.secciones.length > 0 && (
                <div>
                    <h3 className="text-sm font-semibold">Secciones vistas</h3>
                    <ul className="mt-2 divide-y text-sm">
                        {lectura.secciones.map((seccion) => (
                            <li
                                key={seccion.id}
                                className="flex justify-between gap-3 py-2"
                            >
                                <span>{seccion.nombre}</span>
                                <span className="text-muted-foreground shrink-0">
                                    {tiempo(seccion.segundos)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {lectura.clics.length > 0 && (
                <div className="space-y-1 text-sm">
                    <h3 className="font-semibold">Botones utilizados</h3>
                    {lectura.clics.map((clic) => (
                        <p key={clic.nombre}>
                            {clic.nombre} · {clic.visitas}{' '}
                            {clic.visitas === 1 ? 'visita' : 'visitas'}
                        </p>
                    ))}
                </div>
            )}
            <div className="bg-muted/60 rounded-lg p-3 text-sm">
                <p className="font-semibold">Seguimiento sugerido</p>
                <p className="mt-1">{lectura.sugerencia}</p>
            </div>
            <p className="text-muted-foreground text-xs">
                La señal indica visibilidad en pantalla, sin confirmar
                comprensión ni intención de compra. El tiempo se pausa con la
                pestaña oculta o tras 30 segundos sin interacción. Los PDF
                descargados quedan fuera de este seguimiento. Las visitas
                corresponden a quien tenga el enlace. Se muestra la revisión
                actual del documento.
            </p>
        </section>
    );
}
