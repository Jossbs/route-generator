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
import { Textarea } from '@/components/ui/textarea';
import kpis from '@/routes/kpis';
import type { CampoEvento, OpcionSimple, TipoEvento } from '@/types/kpis';

type Props = {
    tipo: TipoEvento | null;
    abierto: boolean;
    onCerrar: () => void;
    tiposDeDato: OpcionSimple[];
};

type CampoEditable = CampoEvento & { opcionesTexto: string };

/** Minúsculas, sin acentos ni espacios: lo que el servidor acepta como valor. */
function valorDe(etiqueta: string): string {
    return etiqueta
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_|_$/g, '');
}

/**
 * Crear o corregir un tipo de evento con sus campos.
 *
 * La clave y el tipo de dato de un campo ya guardado no se editan: los eventos
 * capturados y las medidas dependen de ellos. Las opciones de una lista se
 * escriben una por renglón; las que ya existían conservan su valor.
 */
export function EditorTipo({ tipo, abierto, onCerrar, tiposDeDato }: Props) {
    const [nombre, setNombre] = useState('');
    const [descripcion, setDescripcion] = useState('');
    const [activo, setActivo] = useState(true);
    const [campos, setCampos] = useState<CampoEditable[]>([]);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        if (!abierto) {
            return;
        }

        setNombre(tipo?.name ?? '');
        setDescripcion(tipo?.description ?? '');
        setActivo(tipo?.is_active ?? true);
        setCampos(
            (tipo?.fields ?? []).map((c) => ({
                ...c,
                opcionesTexto: c.options.map((o) => o.label).join('\n'),
            })),
        );
        setErrores({});
    }, [abierto, tipo?.id]);

    const cambiar = (i: number, cambios: Partial<CampoEditable>) =>
        setCampos((cs) =>
            cs.map((c, j) => (j === i ? { ...c, ...cambios } : c)),
        );

    const guardar = () => {
        const datos = {
            name: nombre,
            description: descripcion || null,
            is_active: activo,
            fields: campos.map((c) => {
                const anteriores = Object.fromEntries(
                    c.options.map((o) => [o.label, o.value]),
                );

                return {
                    id: c.id,
                    key: c.id ? c.key : undefined,
                    label: c.label,
                    data_type: c.data_type,
                    is_required: c.is_required,
                    options:
                        c.data_type === 'opcion'
                            ? c.opcionesTexto
                                  .split('\n')
                                  .map((l) => l.trim())
                                  .filter(Boolean)
                                  .map((label) => ({
                                      label,
                                      value:
                                          anteriores[label] ?? valorDe(label),
                                  }))
                            : [],
                };
            }),
        };

        const opciones = {
            preserveScroll: true,
            onStart: () => setEnviando(true),
            onFinish: () => setEnviando(false),
            onSuccess: onCerrar,
            onError: (e: Record<string, string>) => setErrores(e),
        };

        if (tipo) {
            router.put(kpis.eventTypes.update.url(tipo.id), datos, opciones);
        } else {
            router.post(kpis.eventTypes.store.url(), datos, opciones);
        }
    };

    return (
        <Dialog open={abierto} onOpenChange={(o) => !o && onCerrar()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {tipo
                            ? `Editar «${tipo.name}»`
                            : 'Nuevo tipo de evento'}
                    </DialogTitle>
                    <DialogDescription>
                        Algo que ventas registra —una venta, un cobro, un lead
                        atendido— y los datos que se capturan de cada uno.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-2">
                            <EtiquetaCampo htmlFor="tipo-nombre" requerido>
                                Nombre
                            </EtiquetaCampo>
                            <Input
                                id="tipo-nombre"
                                value={nombre}
                                onChange={(e) => setNombre(e.target.value)}
                                placeholder="Cobro"
                            />
                            <InputError message={errores.name} />
                        </div>
                        <Label className="flex items-center gap-2 self-end pb-3">
                            <Checkbox
                                checked={activo}
                                onCheckedChange={(v) => setActivo(v === true)}
                            />
                            Activo (se puede capturar)
                        </Label>
                    </div>
                    <div className="flex flex-col gap-2">
                        <EtiquetaCampo htmlFor="tipo-descripcion" opcional>
                            Descripción
                        </EtiquetaCampo>
                        <Input
                            id="tipo-descripcion"
                            value={descripcion}
                            onChange={(e) => setDescripcion(e.target.value)}
                        />
                    </div>

                    <div>
                        <p className="mb-2 font-semibold">Campos</p>
                        {campos.length === 0 && (
                            <p className="text-muted-foreground mb-2 text-[14px]">
                                Sin campos, cada evento solo cuenta como uno.
                                Agrega importe, plan o lo que se necesite medir.
                            </p>
                        )}
                        <ul className="flex flex-col gap-3">
                            {campos.map((c, i) => (
                                <li
                                    key={c.id ?? `nuevo-${i}`}
                                    className="border-border rounded-lg border p-3"
                                >
                                    <div className="grid gap-3 sm:grid-cols-[1fr_12rem_auto]">
                                        <Input
                                            value={c.label}
                                            onChange={(e) =>
                                                cambiar(i, {
                                                    label: e.target.value,
                                                })
                                            }
                                            placeholder="Etiqueta (ej. Importe cobrado)"
                                            aria-label="Etiqueta del campo"
                                        />
                                        <Select
                                            value={c.data_type}
                                            onValueChange={(v) =>
                                                cambiar(i, {
                                                    data_type:
                                                        v as CampoEvento['data_type'],
                                                })
                                            }
                                            disabled={Boolean(c.id)}
                                        >
                                            <SelectTrigger
                                                className="w-full"
                                                aria-label="Tipo de dato"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {tiposDeDato.map((t) => (
                                                    <SelectItem
                                                        key={t.value}
                                                        value={t.value}
                                                    >
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                setCampos((cs) =>
                                                    cs.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                )
                                            }
                                            aria-label={`Quitar ${c.label || 'campo'}`}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                    <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px]">
                                        <Label className="flex items-center gap-2 font-normal">
                                            <Checkbox
                                                checked={c.is_required}
                                                onCheckedChange={(v) =>
                                                    cambiar(i, {
                                                        is_required: v === true,
                                                    })
                                                }
                                            />
                                            Obligatorio
                                        </Label>
                                        {c.id && (
                                            <span className="text-muted-foreground">
                                                Clave: <code>{c.key}</code>
                                            </span>
                                        )}
                                    </div>
                                    {c.data_type === 'opcion' && (
                                        <Textarea
                                            className="mt-2"
                                            rows={3}
                                            value={c.opcionesTexto}
                                            onChange={(e) =>
                                                cambiar(i, {
                                                    opcionesTexto:
                                                        e.target.value,
                                                })
                                            }
                                            placeholder={
                                                'Una opción por renglón\nEfectivo\nTransferencia'
                                            }
                                            aria-label="Opciones, una por renglón"
                                        />
                                    )}
                                    <InputError
                                        message={
                                            errores[`fields.${i}.label`] ??
                                            errores[`fields.${i}.key`]
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                        <Button
                            variant="outline"
                            size="sm"
                            className="mt-3"
                            onClick={() =>
                                setCampos((cs) => [
                                    ...cs,
                                    {
                                        key: '',
                                        label: '',
                                        data_type: 'numero',
                                        options: [],
                                        is_required: false,
                                        opcionesTexto: '',
                                    },
                                ])
                            }
                        >
                            <Plus /> Agregar campo
                        </Button>
                        <InputError message={errores.fields} />
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
