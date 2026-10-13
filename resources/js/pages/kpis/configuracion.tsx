import { Head, router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { EditorKpi } from '@/components/kpis/editor-kpi';
import type { OpcionesKpi } from '@/components/kpis/editor-kpi';
import { EditorTipo } from '@/components/kpis/editor-tipo';
import { NavegacionKpis } from '@/components/kpis/navegacion-kpis';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { fechaCorta } from '@/lib/kpis';
import { cn } from '@/lib/utils';
import kpis from '@/routes/kpis';
import type {
    KpiDefinicion,
    MedidaDefinicion,
    OpcionSimple,
    TipoEvento,
} from '@/types/kpis';

type Asignacion = {
    id: number;
    user_id: number;
    user: string;
    sales_team_id: number | null;
    team: string | null;
    role: string;
    role_label: string;
    starts_on: string;
    ends_on: string | null;
};

type Props = {
    tipos: TipoEvento[];
    kpis: KpiDefinicion[];
    medidas: OpcionSimple[];
    equipos: { id: number; name: string; is_active: boolean }[];
    asignaciones: Asignacion[];
    usuarios: OpcionSimple[];
    opciones: OpcionesKpi & {
        tiposDeDato: OpcionSimple[];
        roles: OpcionSimple[];
    };
};

type Pestana = 'kpis' | 'tipos' | 'equipos';

/**
 * Qué se registra, qué se mide y quién es quién. Es lo que hace que un KPI nuevo
 * sea un alta y no un deploy (decidido el 2026-09-26).
 */
export default function Configuracion(props: Props) {
    const [pestana, setPestana] = useState<Pestana>('kpis');
    const [tipo, setTipo] = useState<{
        abierto: boolean;
        tipo: TipoEvento | null;
    }>({ abierto: false, tipo: null });
    const [kpi, setKpi] = useState<{
        abierto: boolean;
        kpi: KpiDefinicion | null;
    }>({ abierto: false, kpi: null });

    return (
        <AppLayout>
            <Head title="Configuración de KPIs" />
            <NavegacionKpis puedeAdministrar />
            <Heading
                overline="KPIs · Configuración"
                title="Configuración"
                className="mb-5"
            />

            <ToggleGroup
                type="single"
                variant="outline"
                value={pestana}
                onValueChange={(v) => v && setPestana(v as Pestana)}
                className="mb-6 grid grid-cols-3 sm:flex"
                aria-label="Sección"
            >
                <ToggleGroupItem
                    value="kpis"
                    className="h-auto min-h-11 px-2 py-2 whitespace-normal sm:px-4"
                >
                    KPIs
                </ToggleGroupItem>
                <ToggleGroupItem
                    value="tipos"
                    className="h-auto min-h-11 px-2 py-2 whitespace-normal sm:px-4"
                >
                    Tipos de evento
                </ToggleGroupItem>
                <ToggleGroupItem
                    value="equipos"
                    className="h-auto min-h-11 px-2 py-2 whitespace-normal sm:px-4"
                >
                    Equipos y personas
                </ToggleGroupItem>
            </ToggleGroup>

            {pestana === 'kpis' && (
                <section>
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <p className="text-muted-foreground text-[14px]">
                            Cada KPI se arma con medidas sobre los eventos que
                            se registran.
                        </p>
                        <Button
                            onClick={() => setKpi({ abierto: true, kpi: null })}
                            disabled={props.tipos.length === 0}
                        >
                            <Plus /> Nuevo KPI
                        </Button>
                    </div>
                    <ul className="grid gap-3 md:grid-cols-2">
                        {props.kpis.map((k) => (
                            <li
                                key={k.id}
                                className={cn(
                                    'bg-card border-border rounded-xl border p-4 shadow-(--shadow-card)',
                                    !k.is_active && 'opacity-60',
                                )}
                            >
                                <div className="mb-2 flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold">
                                            {k.name}
                                            {!k.is_active && (
                                                <span className="text-muted-foreground font-normal">
                                                    {' '}
                                                    · inactivo
                                                </span>
                                            )}
                                        </p>
                                        {k.description && (
                                            <p className="text-muted-foreground text-[13px]">
                                                {k.description}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            setKpi({ abierto: true, kpi: k })
                                        }
                                        aria-label={`Editar ${k.name}`}
                                    >
                                        <Pencil />
                                    </Button>
                                </div>
                                <ul className="flex flex-col gap-1 text-[14px]">
                                    {k.measures.map((m) => (
                                        <li key={m.id}>
                                            <span className="font-medium">
                                                {m.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {' '}
                                                —{' '}
                                                {describir(
                                                    m,
                                                    props.tipos,
                                                    props.medidas,
                                                    props.opciones,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {pestana === 'tipos' && (
                <section>
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <p className="text-muted-foreground text-[14px]">
                            Lo que ventas registra, y qué datos lleva cada uno.
                        </p>
                        <Button
                            onClick={() =>
                                setTipo({ abierto: true, tipo: null })
                            }
                        >
                            <Plus /> Nuevo tipo
                        </Button>
                    </div>
                    <ul className="grid gap-3 md:grid-cols-2">
                        {props.tipos.map((t) => (
                            <li
                                key={t.id}
                                className={cn(
                                    'bg-card border-border rounded-xl border p-4 shadow-(--shadow-card)',
                                    !t.is_active && 'opacity-60',
                                )}
                            >
                                <div className="mb-2 flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold">
                                            {t.name}
                                            {!t.is_active && (
                                                <span className="text-muted-foreground font-normal">
                                                    {' '}
                                                    · inactivo
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-muted-foreground text-[13px]">
                                            {t.events_count ?? 0} eventos
                                            registrados
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            setTipo({ abierto: true, tipo: t })
                                        }
                                        aria-label={`Editar ${t.name}`}
                                    >
                                        <Pencil />
                                    </Button>
                                </div>
                                <p className="text-[14px]">
                                    {t.fields.length === 0 ? (
                                        <span className="text-muted-foreground">
                                            Sin campos
                                        </span>
                                    ) : (
                                        t.fields
                                            .map(
                                                (f) =>
                                                    `${f.label}${f.is_required ? '*' : ''}`,
                                            )
                                            .join(' · ')
                                    )}
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {pestana === 'equipos' && <Equipos {...props} />}

            <EditorTipo
                abierto={tipo.abierto}
                tipo={tipo.tipo}
                onCerrar={() => setTipo({ abierto: false, tipo: null })}
                tiposDeDato={props.opciones.tiposDeDato}
            />
            <EditorKpi
                abierto={kpi.abierto}
                kpi={kpi.kpi}
                onCerrar={() => setKpi({ abierto: false, kpi: null })}
                tipos={props.tipos}
                medidas={props.medidas}
                opciones={props.opciones}
            />
        </AppLayout>
    );
}

/** «Suma Importe cobrado de Venta, donde Plan es Expert Anual». */
function describir(
    m: MedidaDefinicion,
    tipos: TipoEvento[],
    medidas: OpcionSimple[],
    opciones: OpcionesKpi,
): string {
    if (m.kind === 'formula') {
        const nombre = (id: number | null) =>
            medidas.find((o) => o.value === String(id))?.label ?? '?';
        const op =
            opciones.operadores.find((o) => o.value === m.formula_operator)
                ?.label ?? '';

        return op
            .replace('A', nombre(m.left_measure_id))
            .replace('B', nombre(m.right_measure_id));
    }

    const tipo = tipos.find((t) => t.id === m.sales_event_type_id);
    const campo = tipo?.fields.find((f) => f.key === m.field_key);
    const verbo =
        {
            conteo: 'Cuenta',
            suma: 'Suma',
            promedio: 'Promedia',
            minimo: 'Mínimo de',
            maximo: 'Máximo de',
        }[m.aggregation ?? 'conteo'] ?? '';
    const filtros = m.filters
        .map((f) => {
            const c = tipo?.fields.find((x) => x.key === f.field);
            const valor =
                c?.options.find((o) => o.value === String(f.value))?.label ??
                String(f.value);

            return `${c?.label ?? f.field} ${f.op === '=' ? 'es' : f.op === '!=' ? 'no es' : f.op} ${valor}`;
        })
        .join(' y ');

    return `${verbo} ${campo ? `${campo.label} de ` : ''}${tipo?.name ?? '?'}${filtros ? `, donde ${filtros}` : ''}`;
}

function Equipos({ equipos, asignaciones, usuarios, opciones }: Props) {
    const [nombre, setNombre] = useState('');
    const [errorEquipo, setErrorEquipo] = useState<string | undefined>();
    const [nueva, setNueva] = useState({
        user_id: '',
        sales_team_id: '',
        role: 'asesor',
        starts_on: new Date().toLocaleDateString('en-CA'),
    });
    const [errores, setErrores] = useState<Record<string, string>>({});

    const crearEquipo = () =>
        router.post(
            kpis.teams.store.url(),
            { name: nombre },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNombre('');
                    setErrorEquipo(undefined);
                },
                onError: (e) => setErrorEquipo(e.name),
            },
        );

    const asignar = () =>
        router.post(
            kpis.assignments.store.url(),
            { ...nueva, sales_team_id: nueva.sales_team_id || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNueva({ ...nueva, user_id: '' });
                    setErrores({});
                },
                onError: setErrores,
            },
        );

    const vigentes = asignaciones.filter((a) => a.ends_on === null);
    const pasadas = asignaciones.filter((a) => a.ends_on !== null);

    return (
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
            <section>
                <h3 className="mb-1 text-lg font-semibold">Equipos</h3>
                <p className="text-muted-foreground mb-3 text-[14px]">
                    Un equipo no se borra: se desactiva, y su historia sigue
                    explicando los meses pasados.
                </p>
                <div className="mb-1 flex gap-2">
                    <Input
                        value={nombre}
                        onChange={(e) => setNombre(e.target.value)}
                        placeholder="Nombre del equipo"
                        aria-label="Nombre del equipo nuevo"
                    />
                    <Button
                        onClick={crearEquipo}
                        disabled={nombre.trim() === ''}
                    >
                        Crear
                    </Button>
                </div>
                <InputError message={errorEquipo} />
                <ul className="mt-3 flex flex-col">
                    {equipos.map((e) => (
                        <li
                            key={e.id}
                            className="border-border flex items-center justify-between border-t py-2"
                        >
                            <span
                                className={cn(
                                    !e.is_active &&
                                        'text-muted-foreground line-through',
                                )}
                            >
                                {e.name}
                            </span>
                            <Button
                                variant="link"
                                size="sm"
                                onClick={() =>
                                    router.put(
                                        kpis.teams.update.url(e.id),
                                        {
                                            name: e.name,
                                            is_active: !e.is_active,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {e.is_active ? 'Desactivar' : 'Reactivar'}
                            </Button>
                        </li>
                    ))}
                </ul>
            </section>

            <section>
                <h3 className="mb-1 text-lg font-semibold">
                    Quién está en qué equipo
                </h3>
                <p className="text-muted-foreground mb-3 text-[14px]">
                    Mover a alguien es darle un tramo nuevo desde una fecha: el
                    anterior se cierra solo el día antes, y sus ventas pasadas
                    siguen contando para su equipo de entonces.
                </p>
                <div className="bg-card border-border mb-4 grid gap-2 rounded-xl border p-3 shadow-(--shadow-card) sm:grid-cols-2">
                    <Elegir
                        etiqueta="Persona"
                        valor={nueva.user_id}
                        opciones={usuarios}
                        onCambio={(v) => setNueva({ ...nueva, user_id: v })}
                    />
                    <Elegir
                        etiqueta="Equipo"
                        valor={nueva.sales_team_id || 'ninguno'}
                        opciones={[
                            { value: 'ninguno', label: 'Sin equipo' },
                            ...equipos
                                .filter((e) => e.is_active)
                                .map((e) => ({
                                    value: String(e.id),
                                    label: e.name,
                                })),
                        ]}
                        onCambio={(v) =>
                            setNueva({
                                ...nueva,
                                sales_team_id: v === 'ninguno' ? '' : v,
                            })
                        }
                    />
                    <Elegir
                        etiqueta="Papel"
                        valor={nueva.role}
                        opciones={opciones.roles}
                        onCambio={(v) => setNueva({ ...nueva, role: v })}
                    />
                    <Input
                        type="date"
                        value={nueva.starts_on}
                        onChange={(e) =>
                            setNueva({ ...nueva, starts_on: e.target.value })
                        }
                        aria-label="Desde"
                    />
                    <div className="sm:col-span-2">
                        <InputError message={Object.values(errores)[0]} />
                        <Button
                            className="w-full sm:w-auto"
                            onClick={asignar}
                            disabled={nueva.user_id === ''}
                        >
                            Asignar desde esa fecha
                        </Button>
                    </div>
                </div>

                <ul className="flex flex-col text-[14px]">
                    {vigentes.map((a) => (
                        <li
                            key={a.id}
                            className="border-border flex flex-wrap items-center justify-between gap-2 border-t py-2"
                        >
                            <span>
                                <span className="font-medium">{a.user}</span>
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {a.role_label}
                                    {a.team && ` en ${a.team}`} · desde{' '}
                                    {fechaCorta(a.starts_on)}
                                </span>
                            </span>
                            <Button
                                variant="link"
                                size="sm"
                                onClick={() =>
                                    router.put(
                                        kpis.assignments.update.url(a.id),
                                        {
                                            ends_on:
                                                new Date().toLocaleDateString(
                                                    'en-CA',
                                                ),
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Terminar hoy
                            </Button>
                        </li>
                    ))}
                </ul>
                {pasadas.length > 0 && (
                    <details className="mt-3 text-[14px]">
                        <summary className="text-muted-foreground cursor-pointer">
                            Tramos terminados ({pasadas.length})
                        </summary>
                        <ul className="mt-2 flex flex-col gap-1">
                            {pasadas.map((a) => (
                                <li
                                    key={a.id}
                                    className="text-muted-foreground"
                                >
                                    {a.user} · {a.role_label}
                                    {a.team && ` en ${a.team}`} ·{' '}
                                    {fechaCorta(a.starts_on)} –{' '}
                                    {fechaCorta(a.ends_on as string)}
                                </li>
                            ))}
                        </ul>
                    </details>
                )}
            </section>
        </div>
    );
}

function Elegir({
    etiqueta,
    valor,
    opciones,
    onCambio,
}: {
    etiqueta: string;
    valor: string;
    opciones: OpcionSimple[];
    onCambio: (v: string) => void;
}) {
    return (
        <Select value={valor} onValueChange={onCambio}>
            <SelectTrigger className="w-full" aria-label={etiqueta}>
                <SelectValue placeholder={etiqueta} />
            </SelectTrigger>
            <SelectContent>
                {opciones.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
