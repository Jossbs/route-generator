import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    FilePenLine,
    FileSpreadsheet,
    Plus,
    Sparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { FormularioEvento } from '@/components/kpis/formulario-evento';
import { NavegacionKpis } from '@/components/kpis/navegacion-kpis';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCargando } from '@/hooks/use-cargando';
import AppLayout from '@/layouts/app-layout';
import { fechaCorta } from '@/lib/kpis';
import { cn } from '@/lib/utils';
import kpis from '@/routes/kpis';
import type { Evento, OpcionSimple, Periodo, TipoEvento } from '@/types/kpis';

type Paginado<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    periodo: Periodo;
    anterior: string;
    siguiente: string;
    filtros: { tipo: string; asesor: string; estado: string };
    eventos: Paginado<Evento>;
    sugeridos: Evento[];
    tipos: TipoEvento[];
    asesores: OpcionSimple[];
    mesesCerrados: string[];
    hoy: string;
};

const TODOS = 'todos';

/**
 * La captura del MVP: el administrador registra cada evento (decidido el
 * 2026-09-26). Arriba, lo que el generador de rutas sugiere y espera
 * confirmación; abajo, lo registrado en el mes.
 */
export default function Eventos(props: Props) {
    const { periodo, filtros, eventos, sugeridos, tipos, asesores } = props;
    const cargando = useCargando();
    const [formulario, setFormulario] = useState<{
        abierto: boolean;
        evento: Evento | null;
    }>({ abierto: false, evento: null });
    const [porAnular, setPorAnular] = useState<Evento | null>(null);
    const [porConfirmar, setPorConfirmar] = useState<Evento | null>(null);

    const filtrar = (cambios: Partial<Props['filtros']> & { fecha?: string }) =>
        router.get(
            kpis.events.index().url,
            Object.fromEntries(
                Object.entries({
                    fecha: periodo.start,
                    ...filtros,
                    ...cambios,
                }).filter(([, v]) => v !== ''),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const cerrado = (fecha: string) =>
        props.mesesCerrados.includes(fecha.slice(0, 7));

    const confirmar = (evento: Evento) => {
        if (cerrado(evento.occurred_on)) {
            setPorConfirmar(evento);

            return;
        }

        router.post(
            kpis.events.confirm.url(evento.id),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout>
            <Head title="Captura de eventos" />
            <NavegacionKpis puedeAdministrar />

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <Heading
                    overline="KPIs · Captura"
                    title="Eventos"
                    className="mb-0"
                    description="Cada venta, cobro o acción, uno por uno. Los totales se calculan solos."
                />
                <Button
                    onClick={() =>
                        setFormulario({ abierto: true, evento: null })
                    }
                    disabled={tipos.length === 0}
                >
                    <Plus /> Registrar evento
                </Button>
            </div>

            {tipos.length === 0 && (
                <p className="bg-muted mb-6 rounded-lg px-4 py-3 text-[14px]">
                    Primero crea un tipo de evento en{' '}
                    <Link
                        href={kpis.settings().url}
                        className="text-primary underline underline-offset-4"
                    >
                        Configuración
                    </Link>
                    .
                </p>
            )}

            {sugeridos.length > 0 && (
                <section className="mb-8" aria-labelledby="sugeridos">
                    <h3
                        id="sugeridos"
                        className="mb-1 flex items-center gap-2 text-lg font-semibold"
                    >
                        <Sparkles className="text-primary size-5" aria-hidden />
                        Por confirmar ({sugeridos.length})
                    </h3>
                    <p className="text-muted-foreground mb-3 text-[14px]">
                        Membresías que se registraron en el generador de rutas.
                        No cuentan hasta que las confirmes.
                    </p>
                    <ul className="flex flex-col gap-2">
                        {sugeridos.map((e) => (
                            <li
                                key={e.id}
                                className="bg-card border-border flex flex-col gap-3 rounded-xl border p-4 shadow-(--shadow-card) sm:flex-row sm:items-center"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        {e.type} · {e.advisor}
                                        <span className="text-muted-foreground font-normal">
                                            {' '}
                                            · {fechaCorta(e.occurred_on)}
                                        </span>
                                    </p>
                                    <p className="text-muted-foreground truncate text-[14px]">
                                        {[e.summary, e.notes]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                kpis.events.discard.url(e.id),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <X /> Descartar
                                    </Button>
                                    <Button
                                        size="sm"
                                        onClick={() => confirmar(e)}
                                    >
                                        <Check /> Confirmar
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => filtrar({ fecha: props.anterior })}
                        aria-label="Mes anterior"
                    >
                        <ChevronLeft />
                    </Button>
                    <span className="min-w-36 text-center font-semibold first-letter:uppercase">
                        {periodo.label}
                    </span>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => filtrar({ fecha: props.siguiente })}
                        aria-label="Mes siguiente"
                    >
                        <ChevronRight />
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <Filtro
                        etiqueta="Tipo"
                        todos="Todos los tipos"
                        valor={filtros.tipo}
                        opciones={tipos.map((t) => ({
                            value: String(t.id),
                            label: t.name,
                        }))}
                        onCambio={(v) => filtrar({ tipo: v })}
                    />
                    <Filtro
                        etiqueta="Asesor"
                        todos="Todos los asesores"
                        valor={filtros.asesor}
                        opciones={asesores}
                        onCambio={(v) => filtrar({ asesor: v })}
                    />
                    <Filtro
                        etiqueta="Estado"
                        todos="Todos"
                        valor={filtros.estado}
                        opciones={[
                            { value: 'ajustes', label: 'Solo ajustes' },
                            { value: 'anulados', label: 'Solo anulados' },
                            {
                                value: 'descartados',
                                label: 'Sugerencias descartadas',
                            },
                        ]}
                        onCambio={(v) => filtrar({ estado: v })}
                    />
                    <Button variant="outline" asChild>
                        <a
                            href={`${kpis.events.export().url}?${new URLSearchParams({ fecha: periodo.start, ...(filtros.tipo ? { tipo: filtros.tipo } : {}) })}`}
                            download
                        >
                            <FileSpreadsheet /> Excel
                        </a>
                    </Button>
                </div>
            </div>

            <div
                className={cn(
                    'bg-card border-border overflow-hidden rounded-xl border shadow-(--shadow-card) transition-opacity',
                    cargando && 'opacity-60',
                )}
            >
                {eventos.data.length === 0 ? (
                    <p className="text-muted-foreground p-8 text-center text-[14px]">
                        No hay eventos registrados con estos filtros.
                    </p>
                ) : (
                    <ul>
                        {eventos.data.map((e) => (
                            <li
                                key={e.id}
                                className={cn(
                                    'border-border flex items-start gap-3 border-t px-4 py-3 first:border-t-0',
                                    e.voided && 'opacity-60',
                                )}
                            >
                                <span className="text-muted-foreground w-14 shrink-0 pt-0.5 text-[13px] tabular-nums">
                                    {fechaCorta(e.occurred_on)}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p
                                        className={cn(
                                            'font-medium',
                                            e.voided && 'line-through',
                                        )}
                                    >
                                        {e.type} · {e.advisor}
                                    </p>
                                    <p className="text-muted-foreground text-[14px]">
                                        {e.summary || 'Sin datos'}
                                    </p>
                                    {e.notes && (
                                        <p className="text-muted-foreground text-[13px] italic">
                                            {e.notes}
                                        </p>
                                    )}
                                    <div className="mt-1 flex flex-wrap gap-1.5">
                                        {e.source !== 'Captura manual' && (
                                            <Chip>{e.source}</Chip>
                                        )}
                                        {e.status === 'descartado' && (
                                            <Chip>Descartada</Chip>
                                        )}
                                        {e.is_adjustment && (
                                            <Chip
                                                title={
                                                    e.adjustment_reason ??
                                                    undefined
                                                }
                                            >
                                                Ajuste: {e.adjustment_reason}
                                            </Chip>
                                        )}
                                        {e.voided && (
                                            <Chip>
                                                Anulado: {e.void_reason}
                                            </Chip>
                                        )}
                                    </div>
                                </div>
                                {!e.voided && e.status === 'confirmado' && (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Acciones del evento de ${e.advisor}`}
                                            >
                                                <EllipsisVertical />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    setFormulario({
                                                        abierto: true,
                                                        evento: e,
                                                    })
                                                }
                                            >
                                                <FilePenLine /> Corregir
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() => setPorAnular(e)}
                                            >
                                                <X /> Anular
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {eventos.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-[14px]">
                    <span className="text-muted-foreground">
                        {eventos.total} eventos
                    </span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!eventos.prev_page_url}
                            onClick={() =>
                                eventos.prev_page_url &&
                                router.get(
                                    eventos.prev_page_url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Anterior
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!eventos.next_page_url}
                            onClick={() =>
                                eventos.next_page_url &&
                                router.get(
                                    eventos.next_page_url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Siguiente
                        </Button>
                    </div>
                </div>
            )}

            <FormularioEvento
                abierto={formulario.abierto}
                evento={formulario.evento}
                onCerrar={() => setFormulario({ abierto: false, evento: null })}
                tipos={tipos}
                asesores={asesores}
                mesesCerrados={props.mesesCerrados}
                hoy={props.hoy}
            />

            <DialogoMotivo
                evento={porAnular}
                titulo="Anular evento"
                descripcion="Deja de contar, pero no se borra: queda con quién, cuándo y por qué."
                campo="reason"
                boton="Anular"
                url={(e) => kpis.events.void.url(e.id)}
                onCerrar={() => setPorAnular(null)}
            />

            <DialogoMotivo
                evento={porConfirmar}
                titulo="Confirmar en un mes cerrado"
                descripcion="Esa venta cae en un mes que ya se cerró. Se confirma como ajuste, con su motivo."
                campo="adjustment_reason"
                boton="Confirmar"
                url={(e) => kpis.events.confirm.url(e.id)}
                onCerrar={() => setPorConfirmar(null)}
            />
        </AppLayout>
    );
}

function Chip({
    children,
    title,
}: {
    children: React.ReactNode;
    title?: string;
}) {
    return (
        <span
            title={title}
            className="bg-muted text-muted-foreground max-w-full truncate rounded-full px-2 py-0.5 text-[12px]"
        >
            {children}
        </span>
    );
}

function Filtro({
    etiqueta,
    todos,
    valor,
    opciones,
    onCambio,
}: {
    etiqueta: string;
    todos: string;
    valor: string;
    opciones: OpcionSimple[];
    onCambio: (v: string) => void;
}) {
    return (
        <Select
            value={valor || TODOS}
            onValueChange={(v) => onCambio(v === TODOS ? '' : v)}
        >
            <SelectTrigger
                className="w-full sm:w-48"
                aria-label={`Filtrar por ${etiqueta.toLowerCase()}`}
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={TODOS}>{todos}</SelectItem>
                {opciones.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/** Un motivo antes de actuar: para anular o para confirmar sobre un mes cerrado. */
function DialogoMotivo({
    evento,
    titulo,
    descripcion,
    campo,
    boton,
    url,
    onCerrar,
}: {
    evento: Evento | null;
    titulo: string;
    descripcion: string;
    campo: 'reason' | 'adjustment_reason';
    boton: string;
    url: (e: Evento) => string;
    onCerrar: () => void;
}) {
    const [motivo, setMotivo] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [enviando, setEnviando] = useState(false);

    const cerrar = () => {
        setMotivo('');
        setError(undefined);
        onCerrar();
    };

    return (
        <Dialog open={evento !== null} onOpenChange={(o) => !o && cerrar()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{titulo}</DialogTitle>
                    <DialogDescription>{descripcion}</DialogDescription>
                </DialogHeader>
                <Textarea
                    rows={3}
                    value={motivo}
                    onChange={(e) => setMotivo(e.target.value)}
                    placeholder="Motivo"
                    aria-label="Motivo"
                />
                <InputError message={error} />
                <DialogFooter>
                    <Button variant="outline" onClick={cerrar}>
                        Cancelar
                    </Button>
                    <Button
                        disabled={enviando || motivo.trim() === ''}
                        onClick={() =>
                            evento &&
                            router.post(
                                url(evento),
                                { [campo]: motivo },
                                {
                                    preserveScroll: true,
                                    onStart: () => setEnviando(true),
                                    onFinish: () => setEnviando(false),
                                    onSuccess: cerrar,
                                    onError: (errores) =>
                                        setError(Object.values(errores)[0]),
                                },
                            )
                        }
                    >
                        {boton}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
