import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarPlus,
    Check,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    FileDown,
    FilePenLine,
    FileText,
    History,
    MessageCircle,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCargando } from '@/hooks/use-cargando';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import { fechaCorta } from '@/lib/embudos';
import { cn } from '@/lib/utils';
import { create, destroy, edit, index, pdf, show } from '@/routes/routes';
import type {
    DocumentoEnBandeja,
    OpcionSimple,
    RutaEnBandeja,
} from '@/types/rutas';

type Paginado<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

type Filtros = {
    q: string;
    periodo: string;
    desde: string;
    hasta: string;
    etapa: string;
    asesor: string;
    especialidad: string;
};

type PageProps = {
    rutas: Paginado<RutaEnBandeja>;
    filtros: Filtros;
    periodos: OpcionSimple[];
    etapas: OpcionSimple[];
    asesores: OpcionSimple[];
    especialidades: OpcionSimple[];
};

type PorBorrar = { ruta: RutaEnBandeja; documento: DocumentoEnBandeja };

/** El valor con el que un select dice «no filtres por esto». */
const TODOS = 'todos';

/**
 * «12 sep», y el año solo cuando no es este: en un chip el espacio pesa y casi
 * todo lo que se busca aquí es de las últimas semanas.
 */
function fechaChip(valor: string | null): string {
    if (!valor) {
        return '—';
    }

    const fecha = new Date(valor);

    return fecha.toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        ...(fecha.getFullYear() !== new Date().getFullYear() && {
            year: '2-digit',
        }),
    });
}

/**
 * Las fechas de la membresía llegan como día sin hora. `new Date('2026-10-01')`
 * las lee en UTC y en México saldrían un día antes; al mediodía no hay zona que
 * las mueva.
 */
function fechaDeDia(valor: string): string {
    return fechaCorta(`${valor}T12:00:00`);
}

/** Las páginas que se muestran: la primera, la última y las vecinas de la actual. */
function paginasVisibles(actual: number, ultima: number): (number | null)[] {
    const paginas = new Set(
        [1, actual - 1, actual, actual + 1, ultima].filter(
            (p) => p >= 1 && p <= ultima,
        ),
    );
    const ordenadas = [...paginas].sort((a, b) => a - b);

    return ordenadas.flatMap((pagina, i) =>
        i > 0 && pagina - ordenadas[i - 1] > 1 ? [null, pagina] : [pagina],
    );
}

/**
 * La bandeja: lo que ya salió, un renglón por lead.
 *
 * Existe para no volver a generar lo que ya se mandó y para administrarlo: cada
 * documento se abre, se corrige y se borra. Desde el 2026-09-23 el renglón es el
 * lead y no el documento: la ruta inicial y la final van lado a lado, y una final
 * pendiente se ve como pendiente, con el botón que la genera ya prellenada.
 *
 * Cada documento es un chip que abre su ficha y trae su propia descarga; la
 * final que falta es un chip punteado que la genera. A la derecha solo queda
 * visible copiar el mensaje escrito, que es lo único de la fila que se pega en
 * otro lado —el guión del audio se lee en la ficha, no se copia—; corregir y
 * borrar, que se hacen de vez en cuando, van en el menú, separadas por documento.
 */
export default function RutasIndex({
    rutas,
    filtros,
    periodos,
    etapas,
    asesores,
    especialidades,
}: PageProps) {
    const [busqueda, setBusqueda] = useState(filtros.q);
    const [porBorrar, setPorBorrar] = useState<PorBorrar | null>(null);
    const [borrando, setBorrando] = useState(false);
    const cargando = useCargando();
    // El mensaje escrito sí se pega en WhatsApp: se copia sin abrir la ficha.
    const [copiado, copiar] = useClipboard();

    const hayFiltros =
        filtros.q !== '' ||
        filtros.periodo !== '' ||
        filtros.etapa !== '' ||
        filtros.asesor !== '' ||
        filtros.especialidad !== '';

    // Sin `page`, la petición cae en la primera: cambiar un filtro regresa al
    // principio porque la cuarta página de otro filtro puede no existir.
    const filtrar = (cambios: Partial<Filtros> & { page?: number }) => {
        const siguientes = { ...filtros, q: busqueda.trim(), ...cambios };

        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(siguientes).filter(
                    ([, valor]) => valor !== '' && valor !== 1,
                ),
            ),
            { preserveState: true, replace: true },
        );
    };

    // La búsqueda corre sola al dejar de escribir: el asesor no tiene que saber
    // que hay que dar Enter.
    useEffect(() => {
        if (busqueda.trim() === filtros.q) {
            return;
        }

        const espera = setTimeout(() => filtrar({}), 400);

        return () => clearTimeout(espera);
        // Solo la búsqueda dispara esto; los filtros navegan por su cuenta.
    }, [busqueda]);

    const limpiar = () => {
        setBusqueda('');
        router.get(index().url, {}, { preserveState: true, replace: true });
    };

    const borrar = () => {
        if (!porBorrar) {
            return;
        }

        setBorrando(true);

        router.delete(destroy(porBorrar.documento.id).url, {
            preserveScroll: true,
            onFinish: () => {
                setBorrando(false);
                setPorBorrar(null);
            },
        });
    };

    const vacio = hayFiltros ? (
        <>
            Ningún lead coincide con estos filtros.
            <Button
                type="button"
                variant="link"
                className="ml-1 h-auto p-0"
                onClick={limpiar}
            >
                Borrar filtros
            </Button>
        </>
    ) : (
        'Todavía no se ha generado ninguna ruta. La primera aparece aquí en cuanto salga.'
    );

    return (
        <AppLayout>
            <Head title="Rutas generadas" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        overline="Rutas"
                        title="Lo que ya se mandó"
                        description="Un renglón por lead, con su ruta inicial y su final. Una final pendiente es un lead que todavía no cierra."
                        className="mb-0"
                    />

                    <Button asChild className="max-sm:w-full">
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nueva ruta
                        </Link>
                    </Button>
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
                            aria-label="Buscar por nombre o WhatsApp del lead"
                            className="pl-9"
                            placeholder="Buscar por nombre o WhatsApp"
                            value={busqueda}
                            onChange={(evento) =>
                                setBusqueda(evento.target.value)
                            }
                        />
                    </form>

                    <div className="grid grid-cols-2 gap-3 sm:contents">
                        <Filtro
                            etiqueta="Periodo"
                            todos="Cualquier fecha"
                            valor={filtros.periodo}
                            opciones={periodos}
                            className="sm:w-40"
                            onCambio={(periodo) =>
                                filtrar({
                                    periodo,
                                    ...(periodo !== 'personalizado' && {
                                        desde: '',
                                        hasta: '',
                                    }),
                                })
                            }
                        />
                        <Filtro
                            etiqueta="Etapa"
                            todos="Todas las etapas"
                            valor={filtros.etapa}
                            opciones={etapas}
                            className="sm:w-48"
                            onCambio={(etapa) => filtrar({ etapa })}
                        />
                        <Filtro
                            etiqueta="Asesor"
                            todos="Todos los asesores"
                            valor={filtros.asesor}
                            opciones={asesores}
                            className="sm:w-44"
                            onCambio={(asesor) => filtrar({ asesor })}
                        />
                        <Filtro
                            etiqueta="Especialidad"
                            todos="Todas las especialidades"
                            valor={filtros.especialidad}
                            opciones={especialidades}
                            className="sm:w-48"
                            onCambio={(especialidad) =>
                                filtrar({ especialidad })
                            }
                        />

                        {hayFiltros && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="max-sm:col-span-2"
                                onClick={limpiar}
                            >
                                <X className="size-4" />
                                Borrar filtros
                            </Button>
                        )}
                    </div>

                    {/*
                     * El rango libre solo aparece cuando se pide: los seis
                     * periodos fijos cubren casi todo lo que se busca, y dos
                     * campos de fecha siempre visibles eran ruido.
                     */}
                    {filtros.periodo === 'personalizado' && (
                        <div className="flex items-center gap-2 sm:basis-full">
                            <Input
                                type="date"
                                aria-label="Desde"
                                className="sm:w-44"
                                value={filtros.desde}
                                max={filtros.hasta || undefined}
                                onChange={(evento) =>
                                    filtrar({ desde: evento.target.value })
                                }
                            />
                            <span className="text-muted-foreground text-sm">
                                a
                            </span>
                            <Input
                                type="date"
                                aria-label="Hasta"
                                className="sm:w-44"
                                value={filtros.hasta}
                                min={filtros.desde || undefined}
                                onChange={(evento) =>
                                    filtrar({ hasta: evento.target.value })
                                }
                            />
                        </div>
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
                        <table className="w-full table-fixed text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left">
                                <tr>
                                    <th className="w-[30%] px-4 py-3 font-medium">
                                        Para quién
                                    </th>
                                    <th className="w-[46%] px-4 py-3 font-medium">
                                        Documentos
                                    </th>
                                    <th className="w-[16%] px-4 py-3 font-medium">
                                        Lo armó
                                    </th>
                                    <th className="w-[8%] px-4 py-3 text-right font-medium">
                                        <span className="sr-only">
                                            Acciones
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rutas.data.map((ruta) => (
                                    <tr
                                        key={ruta.id}
                                        className="hover:bg-accent/30 border-t align-top transition-colors"
                                    >
                                        <td className="px-4 py-3">
                                            <Lead ruta={ruta} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <Documentos ruta={ruta} />
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            <span
                                                className="block truncate"
                                                title={ruta.asesor ?? undefined}
                                            >
                                                {ruta.asesor ?? '—'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Acciones
                                                ruta={ruta}
                                                copiado={copiado}
                                                copiar={copiar}
                                                borrar={setPorBorrar}
                                            />
                                        </td>
                                    </tr>
                                ))}

                                {rutas.data.length === 0 && (
                                    <tr className="border-t">
                                        <td
                                            colSpan={4}
                                            className="text-muted-foreground px-4 py-10 text-center"
                                        >
                                            {vacio}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/*
                     * Los mismos leads, uno por tarjeta, en el celular: el asesor
                     * abre esto entre conversación y conversación, con el
                     * teléfono en la mano.
                     */}
                    <ul className="flex flex-col gap-3 md:hidden">
                        {rutas.data.map((ruta) => (
                            <li
                                key={ruta.id}
                                className="bg-card shadow-card rounded-xl border p-4"
                            >
                                <Lead ruta={ruta} />

                                <div className="mt-3">
                                    <Documentos ruta={ruta} />
                                </div>

                                <div className="mt-3 flex items-center justify-between gap-2 border-t pt-3">
                                    <span className="text-muted-foreground min-w-0 truncate text-xs">
                                        {ruta.asesor ?? '—'}
                                    </span>
                                    <Acciones
                                        ruta={ruta}
                                        copiado={copiado}
                                        copiar={copiar}
                                        borrar={setPorBorrar}
                                    />
                                </div>
                            </li>
                        ))}

                        {rutas.data.length === 0 && (
                            <li className="text-muted-foreground bg-card rounded-xl border border-dashed p-8 text-center text-sm">
                                {vacio}
                            </li>
                        )}
                    </ul>
                </div>

                {rutas.meta.total > 0 && (
                    <Paginacion
                        meta={rutas.meta}
                        irA={(page) => filtrar({ page })}
                    />
                )}
            </div>

            {/*
             * Borrar una ruta no es borrar un borrador: es un documento que ya
             * viajó por WhatsApp. El diálogo dice exactamente qué deja de
             * funcionar —el enlace y el archivo— y qué se queda, porque lo que se
             * queda es lo que permite demostrar después que el documento se validó
             * antes de mandarse. Borra un documento, no al lead: el otro se queda.
             */}
            <Dialog
                open={porBorrar !== null}
                onOpenChange={(abierto) => !abierto && setPorBorrar(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            ¿Borrar la{' '}
                            {porBorrar?.documento.documento.toLowerCase()} de{' '}
                            {porBorrar?.ruta.lead}?
                        </DialogTitle>
                        <DialogDescription>
                            Sale de la bandeja, el enlace deja de abrir y el PDF
                            se borra del disco. El que ya le mandaste por
                            WhatsApp sigue en su teléfono: eso no se puede
                            deshacer desde aquí.
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
                            disabled={borrando}
                            onClick={borrar}
                        >
                            {borrando ? 'Borrando…' : 'Borrar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function Filtro({
    etiqueta,
    todos,
    valor,
    opciones,
    className,
    onCambio,
}: {
    etiqueta: string;
    todos: string;
    valor: string;
    opciones: OpcionSimple[];
    className?: string;
    onCambio: (valor: string) => void;
}) {
    return (
        <Select
            value={valor || TODOS}
            onValueChange={(nuevo) => onCambio(nuevo === TODOS ? '' : nuevo)}
        >
            <SelectTrigger
                className={cn('w-full', className)}
                aria-label={`Filtrar por ${etiqueta.toLowerCase()}`}
            >
                <SelectValue placeholder={etiqueta} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={TODOS}>{todos}</SelectItem>
                {opciones.map((opcion) => (
                    <SelectItem key={opcion.value} value={opcion.value}>
                        {opcion.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function Lead({ ruta }: { ruta: RutaEnBandeja }) {
    // El nombre abre el documento más reciente: si ya compró, lo que se busca
    // es la final.
    const reciente = ruta.final ?? ruta.inicial;

    return (
        <div className="min-w-0">
            {reciente ? (
                <Link
                    href={show(reciente.id)}
                    className="block truncate font-medium underline-offset-4 hover:underline"
                    title={ruta.lead}
                >
                    {ruta.lead}
                </Link>
            ) : (
                <span className="block truncate font-medium" title={ruta.lead}>
                    {ruta.lead}
                </span>
            )}
            {ruta.telefono && (
                <span className="text-muted-foreground block truncate text-xs">
                    {ruta.telefono}
                </span>
            )}
            {ruta.especialidad && (
                <span
                    className="text-muted-foreground block truncate text-xs"
                    title={ruta.especialidad}
                >
                    {ruta.especialidad}
                </span>
            )}
        </div>
    );
}

/**
 * Los dos momentos del lead. La final que falta se pinta punteada y es, ella
 * misma, el botón que la genera: el hueco está justo donde irá el documento, y
 * el formulario abre con los datos del lead ya puestos.
 */
function Documentos({ ruta }: { ruta: RutaEnBandeja }) {
    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex flex-wrap gap-2">
                {ruta.inicial && (
                    <ChipDocumento
                        etiqueta="Inicial"
                        documento={ruta.inicial}
                    />
                )}
                {ruta.final ? (
                    <ChipDocumento etiqueta="Final" documento={ruta.final} />
                ) : (
                    ruta.para_final && (
                        <Link
                            href={create({ query: ruta.para_final })}
                            className="text-muted-foreground hover:border-primary hover:text-primary group inline-flex items-center gap-1.5 rounded-md border border-dashed px-2 py-1 text-xs transition-colors"
                            title="Generar la ruta final, con sus datos ya puestos"
                            aria-label={`Generar la ruta final de ${ruta.lead}`}
                        >
                            <span className="border-muted-foreground group-hover:border-primary size-1.5 rounded-full border" />
                            <span className="font-medium">Final</span>
                            pendiente
                            <CalendarPlus className="size-3.5" />
                        </Link>
                    )
                )}
            </div>
            {ruta.vigencia && (
                <span className="text-muted-foreground text-xs">
                    Membresía {fechaDeDia(ruta.vigencia.desde)} –{' '}
                    {fechaDeDia(ruta.vigencia.hasta)}
                </span>
            )}
        </div>
    );
}

function ChipDocumento({
    etiqueta,
    documento,
}: {
    etiqueta: string;
    documento: DocumentoEnBandeja;
}) {
    const detalle = [
        `${documento.documento} · ${fechaCorta(documento.creada_en)}`,
        documento.asesor && `Lo armó ${documento.asesor}`,
        // Lo que el estado no decía: que este documento se rehizo. El asesor
        // necesita saber que el PDF de su chat puede no ser el último.
        documento.revisiones > 0 &&
            `Corregida ${documento.revisiones} ${documento.revisiones === 1 ? 'vez' : 'veces'}, la última el ${fechaCorta(documento.corregida_en)}`,
        documento.aperturas > 0 &&
            `El lead abrió el enlace ${documento.aperturas} ${documento.aperturas === 1 ? 'vez' : 'veces'}`,
    ]
        .filter(Boolean)
        .join('\n');

    return (
        <span className="bg-background inline-flex items-stretch overflow-hidden rounded-md border text-xs">
            <Link
                href={show(documento.id)}
                className="hover:bg-accent/40 inline-flex items-center gap-1.5 px-2 py-1 transition-colors"
                title={detalle}
                aria-label={`Abrir la ${documento.documento.toLowerCase()}`}
            >
                <span className="bg-primary size-1.5 rounded-full" />
                <span className="font-medium">{etiqueta}</span>
                <span className="text-muted-foreground whitespace-nowrap">
                    {fechaChip(documento.creada_en)}
                </span>
                {/* v2, v3…: el PDF del chat del lead puede no ser el último. */}
                {documento.revisiones > 0 && (
                    <span className="bg-muted text-muted-foreground rounded px-1 text-[10px] leading-4 font-medium">
                        v{documento.revisiones + 1}
                    </span>
                )}
            </Link>
            {documento.pdf && (
                <a
                    href={pdf(documento.id).url}
                    className="hover:bg-accent/40 text-muted-foreground hover:text-foreground inline-flex items-center border-l px-1.5 transition-colors"
                    title="Descargar el PDF"
                    aria-label={`Descargar el PDF de la ${documento.documento.toLowerCase()}`}
                >
                    <FileDown className="size-3.5" />
                </a>
            )}
        </span>
    );
}

function Acciones({
    ruta,
    copiado,
    copiar,
    borrar,
}: {
    ruta: RutaEnBandeja;
    copiado: string | null;
    copiar: (texto: string) => void;
    borrar: (porBorrar: PorBorrar) => void;
}) {
    const documentos = [ruta.inicial, ruta.final].filter(
        (documento): documento is DocumentoEnBandeja => documento !== null,
    );

    return (
        <div className="-my-1.5 flex shrink-0 justify-end gap-1">
            {ruta.mensaje && (
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Copiar el mensaje para ${ruta.lead}`}
                    title="Copiar el mensaje para WhatsApp"
                    onClick={() => copiar(ruta.mensaje ?? '')}
                >
                    {copiado === ruta.mensaje ? (
                        <Check className="size-4 text-emerald-600 dark:text-emerald-400" />
                    ) : (
                        <MessageCircle className="size-4" />
                    )}
                </Button>
            )}

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Más acciones para ${ruta.lead}`}
                        title="Más acciones"
                    >
                        <EllipsisVertical className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    {documentos.map((documento, i) => (
                        <Fragment key={documento.id}>
                            {i > 0 && <DropdownMenuSeparator />}
                            <DropdownMenuLabel className="text-muted-foreground text-xs font-medium">
                                {documento.documento}
                            </DropdownMenuLabel>
                            <DropdownMenuItem asChild>
                                <Link href={show(documento.id)}>
                                    <FileText />
                                    Abrir la ficha
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href={edit(documento.id)}>
                                    <FilePenLine />
                                    Corregir y volver a emitir
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => borrar({ ruta, documento })}
                            >
                                <Trash2 />
                                Borrar
                            </DropdownMenuItem>
                        </Fragment>
                    ))}

                    {ruta.anteriores.length > 0 && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel className="text-muted-foreground text-xs font-medium">
                                Emitidas antes
                            </DropdownMenuLabel>
                            {ruta.anteriores.map((anterior) => (
                                <DropdownMenuItem key={anterior.id} asChild>
                                    <Link href={show(anterior.id)}>
                                        <History />
                                        {anterior.documento} ·{' '}
                                        {fechaChip(anterior.creada_en)}
                                    </Link>
                                </DropdownMenuItem>
                            ))}
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

function Paginacion({
    meta,
    irA,
}: {
    meta: Paginado<RutaEnBandeja>['meta'];
    irA: (pagina: number) => void;
}) {
    const actual = meta.current_page;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
            <span className="text-muted-foreground">
                {meta.from}–{meta.to} de {meta.total}{' '}
                {meta.total === 1 ? 'lead' : 'leads'}
            </span>

            {meta.last_page > 1 && (
                <nav
                    aria-label="Páginas"
                    className="flex items-center gap-1 max-sm:w-full max-sm:justify-between"
                >
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Página anterior"
                        disabled={actual <= 1}
                        onClick={() => irA(actual - 1)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>

                    {paginasVisibles(actual, meta.last_page).map((pagina, i) =>
                        pagina === null ? (
                            <span
                                key={`hueco-${i}`}
                                className="text-muted-foreground px-1"
                            >
                                …
                            </span>
                        ) : (
                            <Button
                                key={pagina}
                                variant={
                                    pagina === actual ? 'outline' : 'ghost'
                                }
                                size="icon"
                                aria-label={`Página ${pagina}`}
                                aria-current={
                                    pagina === actual ? 'page' : undefined
                                }
                                onClick={() => pagina !== actual && irA(pagina)}
                            >
                                {pagina}
                            </Button>
                        ),
                    )}

                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Página siguiente"
                        disabled={actual >= meta.last_page}
                        onClick={() => irA(actual + 1)}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </nav>
            )}
        </div>
    );
}
