import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EtiquetaCampo } from '@/components/etiqueta-campo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import kpis from '@/routes/kpis';
import type {
    Filtro,
    KpiDefinicion,
    MedidaDefinicion,
    OpcionSimple,
    TipoEvento,
} from '@/types/kpis';

export type OpcionesKpi = {
    agregaciones: OpcionSimple[];
    operadores: OpcionSimple[];
    unidades: OpcionSimple[];
};

type Props = {
    kpi: KpiDefinicion | null;
    abierto: boolean;
    onCerrar: () => void;
    tipos: TipoEvento[];
    medidas: OpcionSimple[];
    opciones: OpcionesKpi;
};

const OPERADORES_FILTRO: OpcionSimple[] = [
    { value: '=', label: 'es' },
    { value: '!=', label: 'no es' },
    { value: '>', label: 'mayor que' },
    { value: '>=', label: 'mayor o igual que' },
    { value: '<', label: 'menor que' },
    { value: '<=', label: 'menor o igual que' },
];

const nuevaMedida = (tipo?: TipoEvento): MedidaDefinicion => ({
    name: '',
    unit: 'piezas',
    kind: 'agregado',
    sales_event_type_id: tipo?.id ?? null,
    aggregation: 'conteo',
    field_key: null,
    filters: [],
    formula_operator: 'razon',
    left_measure_id: null,
    right_measure_id: null,
    has_target: true,
    higher_is_better: true,
});

/**
 * Crear o corregir un KPI con sus medidas.
 *
 * Una medida cuenta, suma, promedia o saca el mínimo o máximo de un campo de un
 * tipo de evento, con filtros; o es una fórmula que combina dos medidas ya
 * guardadas. Con eso se arma cualquier KPI sin tocar código (2026-09-26).
 */
export function EditorKpi({
    kpi,
    abierto,
    onCerrar,
    tipos,
    medidas,
    opciones,
}: Props) {
    const [datos, setDatos] = useState<KpiDefinicion>({
        name: '',
        description: null,
        display_order: 0,
        is_active: true,
        measures: [],
    });
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        if (abierto) {
            setDatos(
                kpi
                    ? structuredClone(kpi)
                    : {
                          name: '',
                          description: null,
                          display_order: 0,
                          is_active: true,
                          measures: [nuevaMedida(tipos[0])],
                      },
            );
            setErrores({});
        }
    }, [abierto, kpi?.id]);

    const cambiarMedida = (i: number, cambios: Partial<MedidaDefinicion>) =>
        setDatos((d) => ({
            ...d,
            measures: d.measures.map((m, j) =>
                j === i ? { ...m, ...cambios } : m,
            ),
        }));

    const guardar = () => {
        const opcionesVisita = {
            preserveScroll: true,
            onStart: () => setEnviando(true),
            onFinish: () => setEnviando(false),
            onSuccess: onCerrar,
            onError: (e: Record<string, string>) => setErrores(e),
        };

        if (kpi?.id) {
            router.put(
                kpis.definitions.update.url(kpi.id),
                datos,
                opcionesVisita,
            );
        } else {
            router.post(kpis.definitions.store.url(), datos, opcionesVisita);
        }
    };

    const errorDeMedida = (i: number) =>
        Object.entries(errores).find(([k]) =>
            k.startsWith(`measures.${i}.`),
        )?.[1];

    return (
        <Dialog open={abierto} onOpenChange={(o) => !o && onCerrar()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {kpi ? `Editar «${kpi.name}»` : 'Nuevo KPI'}
                    </DialogTitle>
                    <DialogDescription>
                        Un KPI puede tener varias medidas —piezas y dinero, por
                        ejemplo— y cada una lleva su propia meta.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-[1fr_7rem]">
                        <div className="flex flex-col gap-2">
                            <EtiquetaCampo htmlFor="kpi-nombre" requerido>
                                Nombre
                            </EtiquetaCampo>
                            <Input
                                id="kpi-nombre"
                                value={datos.name}
                                onChange={(e) =>
                                    setDatos({ ...datos, name: e.target.value })
                                }
                                placeholder="Promos vendidas"
                            />
                            <InputError message={errores.name} />
                        </div>
                        <div className="flex flex-col gap-2">
                            <EtiquetaCampo htmlFor="kpi-orden" opcional>
                                Orden
                            </EtiquetaCampo>
                            <Input
                                id="kpi-orden"
                                type="number"
                                min={0}
                                value={datos.display_order}
                                onChange={(e) =>
                                    setDatos({
                                        ...datos,
                                        display_order: Number(e.target.value),
                                    })
                                }
                            />
                        </div>
                    </div>
                    <div className="flex flex-col gap-2">
                        <EtiquetaCampo htmlFor="kpi-descripcion" opcional>
                            Descripción
                        </EtiquetaCampo>
                        <Input
                            id="kpi-descripcion"
                            value={datos.description ?? ''}
                            onChange={(e) =>
                                setDatos({
                                    ...datos,
                                    description: e.target.value || null,
                                })
                            }
                        />
                    </div>
                    <Label className="flex items-center gap-2">
                        <Checkbox
                            checked={datos.is_active}
                            onCheckedChange={(v) =>
                                setDatos({ ...datos, is_active: v === true })
                            }
                        />
                        Activo (aparece en el tablero)
                    </Label>

                    <div>
                        <p className="mb-2 font-semibold">Medidas</p>
                        <ul className="flex flex-col gap-3">
                            {datos.measures.map((m, i) => (
                                <EditorMedida
                                    key={m.id ?? `nueva-${i}`}
                                    medida={m}
                                    tipos={tipos}
                                    medidas={medidas.filter(
                                        (o) => o.value !== String(m.id),
                                    )}
                                    opciones={opciones}
                                    onCambio={(c) => cambiarMedida(i, c)}
                                    onQuitar={
                                        datos.measures.length > 1
                                            ? () =>
                                                  setDatos({
                                                      ...datos,
                                                      measures:
                                                          datos.measures.filter(
                                                              (_, j) => j !== i,
                                                          ),
                                                  })
                                            : undefined
                                    }
                                    error={errorDeMedida(i)}
                                />
                            ))}
                        </ul>
                        <Button
                            variant="outline"
                            size="sm"
                            className="mt-3"
                            onClick={() =>
                                setDatos({
                                    ...datos,
                                    measures: [
                                        ...datos.measures,
                                        nuevaMedida(tipos[0]),
                                    ],
                                })
                            }
                        >
                            <Plus /> Agregar medida
                        </Button>
                        <InputError message={errores.measures} />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onCerrar}>
                        Cancelar
                    </Button>
                    <Button onClick={guardar} disabled={enviando}>
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Campo({
    etiqueta,
    children,
}: {
    etiqueta: string;
    children: React.ReactNode;
}) {
    return (
        <label className="flex flex-col gap-1.5 text-[13px]">
            <span className="text-muted-foreground">{etiqueta}</span>
            {children}
        </label>
    );
}

function Selector({
    valor,
    onCambio,
    opciones,
    placeholder,
    etiqueta,
}: {
    valor: string;
    onCambio: (v: string) => void;
    opciones: OpcionSimple[];
    placeholder?: string;
    etiqueta: string;
}) {
    return (
        <Select value={valor} onValueChange={onCambio}>
            <SelectTrigger className="w-full" aria-label={etiqueta}>
                <SelectValue placeholder={placeholder} />
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

function EditorMedida({
    medida,
    tipos,
    medidas,
    opciones,
    onCambio,
    onQuitar,
    error,
}: {
    medida: MedidaDefinicion;
    tipos: TipoEvento[];
    medidas: OpcionSimple[];
    opciones: OpcionesKpi;
    onCambio: (c: Partial<MedidaDefinicion>) => void;
    onQuitar?: () => void;
    error?: string;
}) {
    const tipo = tipos.find((t) => t.id === medida.sales_event_type_id);
    const numericos = (tipo?.fields ?? []).filter(
        (f) => f.data_type === 'numero' || f.data_type === 'dinero',
    );
    const necesitaCampo =
        medida.aggregation !== null && medida.aggregation !== 'conteo';

    const cambiarFiltro = (i: number, cambios: Partial<Filtro>) =>
        onCambio({
            filters: medida.filters.map((f, j) =>
                j === i ? { ...f, ...cambios } : f,
            ),
        });

    return (
        <li className="border-border rounded-lg border p-3">
            <div className="grid gap-3 sm:grid-cols-[1fr_11rem_auto]">
                <Input
                    value={medida.name}
                    onChange={(e) => onCambio({ name: e.target.value })}
                    placeholder="Nombre (ej. Piezas)"
                    aria-label="Nombre de la medida"
                />
                <Selector
                    etiqueta="Unidad"
                    valor={medida.unit}
                    onCambio={(v) =>
                        onCambio({ unit: v as MedidaDefinicion['unit'] })
                    }
                    opciones={opciones.unidades}
                />
                {onQuitar && (
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={onQuitar}
                        aria-label={`Quitar ${medida.name || 'medida'}`}
                    >
                        <Trash2 />
                    </Button>
                )}
            </div>

            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                className="mt-3"
                value={medida.kind}
                onValueChange={(v) =>
                    v && onCambio({ kind: v as MedidaDefinicion['kind'] })
                }
                aria-label="Cómo se calcula"
            >
                <ToggleGroupItem value="agregado" className="px-3">
                    Sobre eventos
                </ToggleGroupItem>
                <ToggleGroupItem value="formula" className="px-3">
                    Fórmula
                </ToggleGroupItem>
            </ToggleGroup>

            {medida.kind === 'agregado' ? (
                <div className="mt-3 flex flex-col gap-3">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Campo etiqueta="Eventos">
                            <Selector
                                etiqueta="Tipo de evento"
                                valor={
                                    medida.sales_event_type_id
                                        ? String(medida.sales_event_type_id)
                                        : ''
                                }
                                onCambio={(v) =>
                                    onCambio({
                                        sales_event_type_id: Number(v),
                                        field_key: null,
                                        filters: [],
                                    })
                                }
                                opciones={tipos.map((t) => ({
                                    value: String(t.id),
                                    label: t.name,
                                }))}
                                placeholder="Elige"
                            />
                        </Campo>
                        <Campo etiqueta="Cómo">
                            <Selector
                                etiqueta="Resumen"
                                valor={medida.aggregation ?? ''}
                                onCambio={(v) => onCambio({ aggregation: v })}
                                opciones={opciones.agregaciones}
                            />
                        </Campo>
                        {necesitaCampo && (
                            <Campo etiqueta="Campo">
                                <Selector
                                    etiqueta="Campo numérico"
                                    valor={medida.field_key ?? ''}
                                    onCambio={(v) => onCambio({ field_key: v })}
                                    opciones={numericos.map((f) => ({
                                        value: f.key,
                                        label: f.label,
                                    }))}
                                    placeholder={
                                        numericos.length
                                            ? 'Elige'
                                            : 'Sin campos numéricos'
                                    }
                                />
                            </Campo>
                        )}
                    </div>

                    {medida.filters.map((f, i) => {
                        const campo = tipo?.fields.find(
                            (c) => c.key === f.field,
                        );

                        return (
                            <div
                                key={i}
                                className="grid items-end gap-2 sm:grid-cols-[1fr_10rem_1fr_auto]"
                            >
                                <Campo
                                    etiqueta={
                                        i === 0 ? 'Solo donde' : 'y donde'
                                    }
                                >
                                    <Selector
                                        etiqueta="Campo del filtro"
                                        valor={f.field}
                                        onCambio={(v) =>
                                            cambiarFiltro(i, {
                                                field: v,
                                                value: '',
                                            })
                                        }
                                        opciones={(tipo?.fields ?? []).map(
                                            (c) => ({
                                                value: c.key,
                                                label: c.label,
                                            }),
                                        )}
                                        placeholder="Campo"
                                    />
                                </Campo>
                                <Selector
                                    etiqueta="Comparación"
                                    valor={f.op}
                                    onCambio={(v) =>
                                        cambiarFiltro(i, {
                                            op: v as Filtro['op'],
                                        })
                                    }
                                    opciones={OPERADORES_FILTRO}
                                />
                                {campo?.data_type === 'opcion' ? (
                                    <Selector
                                        etiqueta="Valor"
                                        valor={String(f.value)}
                                        onCambio={(v) =>
                                            cambiarFiltro(i, { value: v })
                                        }
                                        opciones={campo.options}
                                        placeholder="Valor"
                                    />
                                ) : campo?.data_type === 'si_no' ? (
                                    <Selector
                                        etiqueta="Valor"
                                        valor={String(f.value)}
                                        onCambio={(v) =>
                                            cambiarFiltro(i, {
                                                value: v === 'true',
                                            })
                                        }
                                        opciones={[
                                            { value: 'true', label: 'Sí' },
                                            { value: 'false', label: 'No' },
                                        ]}
                                        placeholder="Valor"
                                    />
                                ) : (
                                    <Input
                                        value={String(f.value)}
                                        onChange={(e) =>
                                            cambiarFiltro(i, {
                                                value: e.target.value,
                                            })
                                        }
                                        placeholder="Valor"
                                        aria-label="Valor del filtro"
                                    />
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() =>
                                        onCambio({
                                            filters: medida.filters.filter(
                                                (_, j) => j !== i,
                                            ),
                                        })
                                    }
                                    aria-label="Quitar filtro"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        );
                    })}
                    {tipo && tipo.fields.length > 0 && (
                        <Button
                            variant="link"
                            size="sm"
                            className="self-start px-0"
                            onClick={() =>
                                onCambio({
                                    filters: [
                                        ...medida.filters,
                                        {
                                            field: tipo.fields[0].key,
                                            op: '=',
                                            value: '',
                                        },
                                    ],
                                })
                            }
                        >
                            <Plus /> Filtrar por un campo
                        </Button>
                    )}
                </div>
            ) : (
                <div className="mt-3 grid gap-3 sm:grid-cols-3">
                    <Campo etiqueta="A">
                        <Selector
                            etiqueta="Medida A"
                            valor={
                                medida.left_measure_id
                                    ? String(medida.left_measure_id)
                                    : ''
                            }
                            onCambio={(v) =>
                                onCambio({ left_measure_id: Number(v) })
                            }
                            opciones={medidas}
                            placeholder="Medida guardada"
                        />
                    </Campo>
                    <Campo etiqueta="Operación">
                        <Selector
                            etiqueta="Operación"
                            valor={medida.formula_operator ?? ''}
                            onCambio={(v) => onCambio({ formula_operator: v })}
                            opciones={opciones.operadores}
                        />
                    </Campo>
                    <Campo etiqueta="B">
                        <Selector
                            etiqueta="Medida B"
                            valor={
                                medida.right_measure_id
                                    ? String(medida.right_measure_id)
                                    : ''
                            }
                            onCambio={(v) =>
                                onCambio({ right_measure_id: Number(v) })
                            }
                            opciones={medidas}
                            placeholder="Medida guardada"
                        />
                    </Campo>
                    <p className="text-muted-foreground text-[12px] sm:col-span-3">
                        Una fórmula usa medidas ya guardadas. Para una
                        conversión, guarda primero ventas y leads, y después
                        agrega la razón con unidad «Porcentaje».
                    </p>
                </div>
            )}

            <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-[13px]">
                <Label className="flex items-center gap-2 font-normal">
                    <Checkbox
                        checked={medida.has_target}
                        onCheckedChange={(v) =>
                            onCambio({ has_target: v === true })
                        }
                    />
                    Lleva meta
                </Label>
                <Label className="flex items-center gap-2 font-normal">
                    <Checkbox
                        checked={!medida.higher_is_better}
                        onCheckedChange={(v) =>
                            onCambio({ higher_is_better: v !== true })
                        }
                    />
                    Conviene que baje (cancelaciones, reembolsos)
                </Label>
            </div>
            <InputError message={error} />
        </li>
    );
}
