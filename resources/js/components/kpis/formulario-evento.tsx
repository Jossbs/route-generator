import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { EtiquetaCampo } from '@/components/etiqueta-campo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import kpis from '@/routes/kpis';
import type {
    CampoEvento,
    Evento,
    OpcionSimple,
    TipoEvento,
} from '@/types/kpis';

type Props = {
    abierto: boolean;
    onCerrar: () => void;
    tipos: TipoEvento[];
    asesores: OpcionSimple[];
    mesesCerrados: string[];
    hoy: string;
    /** El evento a corregir; sin él, se registra uno nuevo. */
    evento?: Evento | null;
};

type Datos = {
    sales_event_type_id: string;
    user_id: string;
    occurred_on: string;
    values: Record<string, string | boolean>;
    notes: string;
    adjustment_reason: string;
};

/**
 * Registrar o corregir un evento. Los campos salen del tipo elegido: un tipo nuevo
 * con campos nuevos aparece aquí sin tocar esta pantalla.
 *
 * Si la fecha cae en un mes cerrado, aparece el motivo del ajuste, obligatorio:
 * nada cambia en silencio sobre lo que ya se reportó.
 */
export function FormularioEvento({
    abierto,
    onCerrar,
    tipos,
    asesores,
    mesesCerrados,
    hoy,
    evento,
}: Props) {
    const activos = tipos.filter(
        (t) => t.is_active || t.id === evento?.type_id,
    );

    const inicial = (): Datos => ({
        sales_event_type_id: String(evento?.type_id ?? activos[0]?.id ?? ''),
        user_id: evento ? String(evento.user_id) : '',
        occurred_on: evento?.occurred_on ?? hoy,
        values: Object.fromEntries(
            Object.entries(evento?.values ?? {}).map(([k, v]) => [
                k,
                typeof v === 'boolean' ? v : String(v),
            ]),
        ),
        notes: evento?.notes ?? '',
        adjustment_reason: '',
    });

    const form = useForm<Datos>(inicial());

    useEffect(() => {
        if (abierto) {
            form.setData(inicial());
            form.clearErrors();
        }
    }, [abierto, evento?.id]);

    const tipo = tipos.find(
        (t) => String(t.id) === form.data.sales_event_type_id,
    );
    const cerrado =
        mesesCerrados.includes(form.data.occurred_on.slice(0, 7)) ||
        (evento !== null &&
            evento !== undefined &&
            mesesCerrados.includes(evento.occurred_on.slice(0, 7)));

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: () => onCerrar() };

        if (evento) {
            form.put(kpis.events.update.url(evento.id), opciones);
        } else {
            form.post(kpis.events.store.url(), opciones);
        }
    };

    const valor = (campo: CampoEvento) => form.data.values[campo.key] ?? '';
    const cambiar = (clave: string, nuevo: string | boolean) =>
        form.setData('values', { ...form.data.values, [clave]: nuevo });

    return (
        <Dialog open={abierto} onOpenChange={(o) => !o && onCerrar()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {evento ? 'Corregir evento' : 'Registrar evento'}
                    </DialogTitle>
                    <DialogDescription>
                        El crédito es completo del asesor que cerró.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="formulario-evento"
                    onSubmit={enviar}
                    className="flex flex-col gap-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-2 sm:col-span-2">
                            <EtiquetaCampo htmlFor="tipo" requerido>
                                Qué se registra
                            </EtiquetaCampo>
                            <Select
                                value={form.data.sales_event_type_id}
                                onValueChange={(v) =>
                                    form.setData({
                                        ...form.data,
                                        sales_event_type_id: v,
                                        values: {},
                                    })
                                }
                                disabled={Boolean(evento)}
                            >
                                <SelectTrigger id="tipo" className="w-full">
                                    <SelectValue placeholder="Elige el tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    {activos.map((t) => (
                                        <SelectItem
                                            key={t.id}
                                            value={String(t.id)}
                                        >
                                            {t.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={form.errors.sales_event_type_id}
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <EtiquetaCampo htmlFor="asesor" requerido>
                                Asesor
                            </EtiquetaCampo>
                            <Select
                                value={form.data.user_id}
                                onValueChange={(v) =>
                                    form.setData('user_id', v)
                                }
                            >
                                <SelectTrigger id="asesor" className="w-full">
                                    <SelectValue placeholder="¿Quién cerró?" />
                                </SelectTrigger>
                                <SelectContent>
                                    {asesores.map((a) => (
                                        <SelectItem
                                            key={a.value}
                                            value={a.value}
                                        >
                                            {a.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.user_id} />
                        </div>

                        <div className="flex flex-col gap-2">
                            <EtiquetaCampo htmlFor="fecha" requerido>
                                Fecha
                            </EtiquetaCampo>
                            <Input
                                id="fecha"
                                type="date"
                                value={form.data.occurred_on}
                                max={hoy}
                                onChange={(e) =>
                                    form.setData('occurred_on', e.target.value)
                                }
                            />
                            <InputError message={form.errors.occurred_on} />
                        </div>
                    </div>

                    {tipo?.fields.map((campo) => {
                        const id = `campo-${campo.key}`;
                        const error =
                            form.errors[`values.${campo.key}` as keyof Datos];

                        return (
                            <div
                                key={campo.key}
                                className="flex flex-col gap-2"
                            >
                                <EtiquetaCampo
                                    htmlFor={id}
                                    requerido={campo.is_required}
                                    opcional={!campo.is_required}
                                >
                                    {campo.label}
                                </EtiquetaCampo>

                                {campo.data_type === 'opcion' ? (
                                    <Select
                                        value={String(valor(campo))}
                                        onValueChange={(v) =>
                                            cambiar(campo.key, v)
                                        }
                                    >
                                        <SelectTrigger
                                            id={id}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Elige una opción" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {campo.options.map((o) => (
                                                <SelectItem
                                                    key={o.value}
                                                    value={o.value}
                                                >
                                                    {o.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : campo.data_type === 'si_no' ? (
                                    <Select
                                        value={
                                            valor(campo) === ''
                                                ? ''
                                                : String(valor(campo))
                                        }
                                        onValueChange={(v) =>
                                            cambiar(campo.key, v === 'true')
                                        }
                                    >
                                        <SelectTrigger
                                            id={id}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Elige" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">
                                                Sí
                                            </SelectItem>
                                            <SelectItem value="false">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <div className="relative">
                                        {campo.data_type === 'dinero' && (
                                            <span className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 -translate-y-1/2">
                                                $
                                            </span>
                                        )}
                                        <Input
                                            id={id}
                                            type={
                                                campo.data_type === 'fecha'
                                                    ? 'date'
                                                    : campo.data_type ===
                                                        'texto'
                                                      ? 'text'
                                                      : 'number'
                                            }
                                            inputMode={
                                                campo.data_type === 'dinero'
                                                    ? 'decimal'
                                                    : undefined
                                            }
                                            step={
                                                campo.data_type === 'dinero'
                                                    ? '0.01'
                                                    : 'any'
                                            }
                                            min={
                                                campo.data_type === 'dinero'
                                                    ? 0
                                                    : undefined
                                            }
                                            className={
                                                campo.data_type === 'dinero'
                                                    ? 'pl-7'
                                                    : undefined
                                            }
                                            value={String(valor(campo))}
                                            onChange={(e) =>
                                                cambiar(
                                                    campo.key,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                )}
                                <InputError message={error} />
                            </div>
                        );
                    })}

                    <div className="flex flex-col gap-2">
                        <EtiquetaCampo htmlFor="notas" opcional>
                            Notas
                        </EtiquetaCampo>
                        <Textarea
                            id="notas"
                            rows={2}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </div>

                    {cerrado && (
                        <div className="bg-muted flex flex-col gap-2 rounded-lg p-3">
                            <EtiquetaCampo htmlFor="motivo" requerido>
                                Motivo del ajuste
                            </EtiquetaCampo>
                            <p className="text-muted-foreground text-[13px]">
                                Ese mes ya está cerrado. El cambio queda marcado
                                como ajuste, con este motivo.
                            </p>
                            <Textarea
                                id="motivo"
                                rows={2}
                                value={form.data.adjustment_reason}
                                onChange={(e) =>
                                    form.setData(
                                        'adjustment_reason',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.adjustment_reason}
                            />
                        </div>
                    )}
                </form>

                <DialogFooter>
                    <Button variant="outline" onClick={onCerrar} type="button">
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        form="formulario-evento"
                        disabled={form.processing}
                    >
                        {evento ? 'Guardar corrección' : 'Registrar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
