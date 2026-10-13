import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Check,
    Copy,
    Download,
    ExternalLink,
    Eye,
    FileText,
    Pencil,
    RefreshCw,
    ShieldX,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { GuionAudio } from '@/components/rutas/guion-audio';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
// La misma dirección del archivo: en GET lo entrega, en POST lo vuelve a pedir.
import {
    create,
    edit,
    index,
    pdf as documentoPdf,
    show,
} from '@/routes/routes';
import type {
    CursoDelDocumento,
    GuionDeAudio,
    LeadDeRuta,
    MembresiaFicha,
    MensajeEscrito,
    PdfDeRuta,
    Rechazo,
    RutaResumen,
    TotalesRuta,
} from '@/types/rutas';

type PageProps = {
    ruta: RutaResumen;
    lead: LeadDeRuta;
    membresia: MembresiaFicha | null;
    totales: TotalesRuta | null;
    cursos: CursoDelDocumento[];
    guion: GuionDeAudio | null;
    mensaje: MensajeEscrito | null;
    rechazos: Rechazo[];
    aperturas: { fecha: string | null }[];
    copyPendiente: string[];
    sinWhatsApp: boolean;
    documentoUrl: string;
    pdf: PdfDeRuta;
    autoDescarga: boolean;
    puedeEmitirAnual: boolean;
    anualEmitido: number | null;
};

/** Cada cuánto se pregunta si el archivo ya salió de la cola. */
const ESPERA_MS = 1500;

/**
 * Cuántas veces se pregunta antes de rendirse.
 *
 * Hasta noventa segundos: incluye la espera de la cola y el timeout del render.
 */
const INTENTOS = 60;

/** Lo que el asesor pega en el chat cuando manda el enlace en vez del archivo. */
function mensajeWhatsApp(nombre: string, enlace: string): string {
    return `Hola ${nombre}, preparé una ruta formativa pensando en lo que me comentaste. Aquí la puedes ver: ${enlace}`;
}

export default function RutaDetalle() {
    const {
        ruta,
        lead,
        membresia,
        totales,
        cursos,
        guion,
        mensaje,
        rechazos,
        aperturas,
        copyPendiente,
        sinWhatsApp,
        documentoUrl,
        pdf,
        autoDescarga,
        puedeEmitirAnual,
        anualEmitido,
    } = usePage<PageProps>().props;

    const [copiado, setCopiado] = useState<string | null>(null);
    const [, copiarTexto] = useClipboard();
    const [reintentando, setReintentando] = useState(false);

    // Recién generada: el asesor viene de decir «generar», así que el archivo baja
    // solo. Se recuerda al montar porque el aviso de sesión se pierde en el primer
    // refresco, y lo que decide no es el estado de ahora sino de dónde se llegó.
    const reciente = useRef(autoDescarga);
    const yaBajo = useRef(false);
    const [intentos, setIntentos] = useState(0);

    const rechazada = ruta.estado === 'rejected';
    const fallida = ruta.estado === 'failed';
    const armando = !pdf.listo && !rechazada && !fallida;

    const copiar = (texto: string, clave: string) => {
        void copiarTexto(texto).then((ok) => {
            if (ok) {
                setCopiado(clave);
                window.setTimeout(() => setCopiado(null), 2000);
            }
        });
    };

    const reintentar = () => {
        setIntentos(0);
        yaBajo.current = false;
        reciente.current = true;
        router.post(
            documentoPdf(ruta.id).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setReintentando(true),
                onFinish: () => setReintentando(false),
            },
        );
    };

    const descargar = () => {
        const enlace = document.createElement('a');
        enlace.href = pdf.url;
        enlace.download = pdf.nombre;
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
    };

    // Mientras la cola arma el PDF, la ficha se pregunta sola si ya salió. Sin
    // esto el asesor tendría que refrescar a mano un documento que él no puso a
    // hacer: el render vive en cola justo para que no espere mirando.
    useEffect(() => {
        if (!armando || intentos >= INTENTOS) {
            return;
        }

        const reloj = window.setTimeout(() => {
            setIntentos((previos) => previos + 1);
            router.reload({ only: ['ruta', 'pdf'] });
        }, ESPERA_MS);

        return () => window.clearTimeout(reloj);
    }, [armando, intentos]);

    useEffect(() => {
        if (pdf.listo && reciente.current && !yaBajo.current) {
            yaBajo.current = true;
            descargar();
        }
        // `descargar` solo lee refs y props ya capturados: volver a correr esto
        // cuando cambia la identidad de la función descargaría dos veces.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pdf.listo]);

    return (
        <AppLayout>
            <Head title={`Ruta de ${lead.nombre}`} />

            <div className="flex min-w-0 flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <Heading
                            title={lead.nombre}
                            description={`${ruta.documento}${lead.especialidad ? ` · ${lead.especialidad}` : ''}`}
                            className="mb-3"
                        />
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                {ruta.estado_etiqueta}
                            </Badge>
                            {/*
                             * Cuántas veces se rehizo. Va junto al estado porque
                             * es lo que le falta al estado: que el documento sea
                             * el último no dice que sea el primero, y el PDF que
                             * el lead tiene en su chat puede ser otro.
                             */}
                            {ruta.revisiones > 0 && (
                                <Badge variant="secondary">
                                    Corregida {ruta.revisiones}{' '}
                                    {ruta.revisiones === 1 ? 'vez' : 'veces'}
                                </Badge>
                            )}
                            {lead.telefono && (
                                <span className="text-muted-foreground text-sm break-all">
                                    {lead.telefono}
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 max-sm:w-full">
                        <Button
                            variant="outline"
                            asChild
                            className="max-sm:w-full"
                        >
                            <Link href={edit(ruta.id)}>
                                <Pencil className="size-4" />
                                Corregir
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            asChild
                            className="max-sm:w-full"
                        >
                            <Link href={index()}>Ver todas las rutas</Link>
                        </Button>
                    </div>
                </div>

                {rechazada && (
                    <div className="bg-destructive/10 text-destructive space-y-2 rounded-xl p-4">
                        <p className="flex items-center gap-2 font-medium">
                            <ShieldX className="size-4 shrink-0" />
                            El validador detuvo esta ruta y no se entregó.
                        </p>
                        <ul className="list-inside list-disc space-y-1 text-sm">
                            {rechazos.map((rechazo) => (
                                <li key={rechazo.codigo}>{rechazo.mensaje}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {/*
                 * La entrega. El archivo es lo que se adjunta en Kommo, así que va
                 * primero y con nombre: el asesor tiene que reconocerlo entre los
                 * veinte que tiene en Descargas.
                 */}
                {!rechazada && (
                    <section className="bg-card shadow-card space-y-4 rounded-xl border p-5">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="min-w-0">
                                <h2 className="font-subtitle text-lg font-bold">
                                    El PDF para mandarle
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Adjúntalo en su conversación de Kommo. Se
                                    llama{' '}
                                    <span className="text-foreground [overflow-wrap:anywhere]">
                                        {pdf.nombre}
                                    </span>
                                    .
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 max-sm:w-full">
                                <Button
                                    onClick={descargar}
                                    disabled={!pdf.listo}
                                    size="lg"
                                    className="max-sm:w-full"
                                >
                                    {armando ? (
                                        <Spinner className="size-4" />
                                    ) : (
                                        <Download className="size-4" />
                                    )}
                                    {armando
                                        ? 'Preparando el PDF…'
                                        : 'Descargar el PDF'}
                                </Button>
                                <Button
                                    variant="outline"
                                    asChild
                                    className="max-sm:w-full"
                                >
                                    <a
                                        href={documentoUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <FileText className="size-4" />
                                        Verlo antes
                                    </a>
                                </Button>
                            </div>
                        </div>

                        {armando && intentos < INTENTOS && (
                            <p className="text-muted-foreground text-sm">
                                Tarda unos segundos. En cuanto esté, se descarga
                                solo.
                            </p>
                        )}

                        {/*
                         * Que el archivo no salga no puede dejar al asesor sin nada
                         * que mandar: se reintenta, y mientras tanto la vista
                         * imprimible es la misma hoja y no depende de la cola.
                         */}
                        {(fallida || (armando && intentos >= INTENTOS)) && (
                            <div className="bg-muted/60 text-muted-foreground flex flex-wrap items-center gap-3 rounded-lg p-3 text-sm">
                                <span className="min-w-0 flex-1">
                                    El archivo no se generó. Vuelve a
                                    intentarlo; si sigue sin salir, ábrelo en
                                    «Verlo antes» y descárgalo con Imprimir →
                                    Guardar como PDF.
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={reintentar}
                                    disabled={reintentando}
                                >
                                    <RefreshCw className="size-4" />
                                    Volver a intentarlo
                                </Button>
                            </div>
                        )}
                    </section>
                )}

                {/*
                 * El audio va junto al archivo porque salen juntos: el asesor
                 * graba, manda el PDF y el audio en la misma conversación. Si no
                 * puede grabar, el mensaje escrito ocupa su lugar.
                 */}
                {!rechazada && (guion || mensaje) && (
                    <GuionAudio guion={guion} mensaje={mensaje} />
                )}

                {/*
                 * El enlace sigue existiendo debajo del archivo, no en su lugar:
                 * sirve cuando el PDF pesa para el chat, y es lo único que avisa si
                 * el lead lo abrió.
                 */}
                {ruta.enlace && (
                    <section className="bg-card space-y-4 rounded-xl border p-5">
                        <div>
                            <h2 className="font-subtitle text-lg font-bold">
                                Si prefieres mandarle un enlace
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Es el mismo documento abierto en el navegador.
                                Este sí avisa cuándo lo abre.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="enlace">Enlace para el lead</Label>
                            <div className="flex min-w-0 gap-2 max-sm:flex-wrap">
                                <Input
                                    id="enlace"
                                    readOnly
                                    value={ruta.enlace}
                                    className="min-w-0 flex-1 font-mono text-xs max-sm:w-full max-sm:flex-none"
                                />
                                <Button
                                    variant="outline"
                                    className="shrink-0 max-sm:flex-1"
                                    onClick={() =>
                                        copiar(ruta.enlace ?? '', 'enlace')
                                    }
                                >
                                    {copiado === 'enlace' ? (
                                        <Check className="size-4" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                    Copiar
                                </Button>
                                <Button
                                    variant="ghost"
                                    className="shrink-0"
                                    aria-label="Abrir el enlace"
                                    asChild
                                >
                                    <a
                                        href={ruta.enlace}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <ExternalLink className="size-4" />
                                    </a>
                                </Button>
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="mensaje">
                                Mensaje para WhatsApp
                            </Label>
                            <Textarea
                                id="mensaje"
                                readOnly
                                rows={3}
                                className="resize-none"
                                value={mensajeWhatsApp(
                                    lead.nombre,
                                    ruta.enlace,
                                )}
                            />
                            <div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        copiar(
                                            mensajeWhatsApp(
                                                lead.nombre,
                                                ruta.enlace ?? '',
                                            ),
                                            'mensaje',
                                        )
                                    }
                                >
                                    {copiado === 'mensaje' ? (
                                        <Check className="size-4" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                    Copiar mensaje
                                </Button>
                            </div>
                        </div>

                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <Eye className="size-4 shrink-0" />
                            {aperturas.length === 0
                                ? 'El lead todavía no lo abre.'
                                : `Abierto ${aperturas.length} ${aperturas.length === 1 ? 'vez' : 'veces'}.`}
                        </div>
                    </section>
                )}

                {/*
                 * Lo que se vendió. El documento no imprime el importe —lo dice el
                 * asesor en la conversación— así que esta es la única pantalla
                 * donde se puede comprobar lo que quedó asentado.
                 */}
                {membresia && (
                    <section className="bg-card space-y-3 rounded-xl border p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="font-subtitle text-lg font-bold">
                                La membresía
                            </h2>
                            <span className="text-muted-foreground text-xs">
                                El importe no sale en el documento.
                            </span>
                        </div>

                        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-4">
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Plan
                                </dt>
                                <dd className="font-medium">
                                    {membresia.plan ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Importe
                                </dt>
                                <dd className="font-medium tabular-nums">
                                    {membresia.importe === null
                                        ? '—'
                                        : `$${membresia.importe}`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Desde
                                </dt>
                                <dd className="font-medium tabular-nums">
                                    {membresia.desde ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Hasta
                                </dt>
                                <dd className="font-medium tabular-nums">
                                    {membresia.hasta ?? '—'}
                                </dd>
                            </div>
                        </dl>
                    </section>
                )}

                {totales && (
                    <section className="bg-card space-y-4 rounded-xl border p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="font-subtitle text-lg font-bold">
                                Lo que dice el documento
                            </h2>
                            <div className="text-muted-foreground flex flex-wrap gap-4 text-sm tabular-nums">
                                <span>{totales.programas} programas</span>
                                <span>{totales.horas_netas} horas netas</span>
                                <span>{totales.cursos} constancias DC-3</span>
                            </div>
                        </div>

                        <ol className="divide-y">
                            {cursos
                                .filter((curso) => curso.capa === 'itinerario')
                                .map((curso) => (
                                    <li
                                        key={`${curso.orden}-${curso.nombre}`}
                                        className="flex items-baseline gap-3 py-2 text-sm"
                                    >
                                        <span className="text-muted-foreground w-6 shrink-0 text-right tabular-nums">
                                            {curso.orden}
                                        </span>
                                        <span className="min-w-0 flex-1 break-words">
                                            {curso.nombre}
                                            <span className="text-muted-foreground">
                                                {' · '}
                                                {curso.especialidad}
                                            </span>
                                        </span>
                                        <span className="text-muted-foreground shrink-0 tabular-nums">
                                            {curso.horas_absorbidas
                                                ? 'incluido'
                                                : curso.horas === null
                                                  ? '— h'
                                                  : `${curso.horas} h`}
                                        </span>
                                    </li>
                                ))}
                        </ol>

                        {sinWhatsApp && (
                            <p className="text-muted-foreground border-t pt-3 text-xs break-words">
                                El cierre salió sin el botón de WhatsApp: tu
                                perfil todavía no trae número. El documento se
                                lee igual, pero el lead tiene que buscar el chat
                                por su cuenta.
                            </p>
                        )}

                        {copyPendiente.length > 0 && (
                            <p className="text-muted-foreground border-t pt-3 text-xs break-words">
                                {copyPendiente.length}{' '}
                                {copyPendiente.length === 1
                                    ? 'bloque de texto sigue'
                                    : 'bloques de texto siguen'}{' '}
                                sin escribir, así que el documento sale sin
                                ellos: {copyPendiente.join(', ')}.
                            </p>
                        )}
                    </section>
                )}

                {puedeEmitirAnual && !anualEmitido && (
                    <section className="bg-card space-y-3 rounded-xl border p-5">
                        <h2 className="font-subtitle text-lg font-bold">
                            ¿Ya cerró la membresía?
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            La ruta final es otro documento: se arma sobre los
                            doce meses y también se descarga como PDF. Tiene su
                            propia captura porque pide la vigencia, que va
                            impresa.
                        </p>
                        <Button variant="outline" asChild>
                            <Link
                                href={create({
                                    query: {
                                        tipo: 'final',
                                        lead: lead.id,
                                        nombre: lead.nombre,
                                        telefono: lead.telefono ?? '',
                                        ocupacion: lead.ocupacion ?? '',
                                        area: lead.area ?? '',
                                        contexto: lead.contexto ?? '',
                                        // El embudo y el identificador de Kommo
                                        // viajan para que la ruta final salga del
                                        // mismo lead y no de uno nuevo.
                                        embudo: lead.embudo ?? '',
                                        kommo_id: lead.external_id ?? '',
                                    },
                                })}
                            >
                                Armar su ruta final
                            </Link>
                        </Button>
                    </section>
                )}

                {anualEmitido !== null && (
                    <p className="text-muted-foreground text-sm">
                        Este lead ya tiene su{' '}
                        <Link
                            href={show(anualEmitido)}
                            className="underline underline-offset-4"
                        >
                            ruta final
                        </Link>{' '}
                        generada.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
