import { Form, Head, Link } from '@inertiajs/react';
import { FileDown, Rocket } from 'lucide-react';
import { useState } from 'react';
import AnnualRouteController from '@/actions/App/Http/Controllers/Routes/AnnualRouteController';
import LearningRouteController from '@/actions/App/Http/Controllers/Routes/LearningRouteController';
import { EtiquetaCampo } from '@/components/etiqueta-campo';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Pasos } from '@/components/rutas/pasos';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { avisarErrores } from '@/lib/avisar-errores';
import { cn } from '@/lib/utils';
import { show } from '@/routes/routes';
import type {
    AreaOpcion,
    EmbudoOpcion,
    PlanOpcion,
    Precargado,
    RutaEditandose,
} from '@/types/rutas';

type Tipo = 'inicial' | 'final';

type PageProps = {
    tipo: Tipo;
    embudos: EmbudoOpcion[];
    areas: AreaOpcion[];
    planes: PlanOpcion[];
    precargado: Precargado;
    /** Presente = se está corrigiendo un documento que ya salió. */
    editando: RutaEditandose | null;
};

/** Lo que abre el campo abierto. El backend lo reconoce con el mismo valor. */
const OTRA_AREA = 'otro';

/** Igual que el área: el plan negociado fuera de la lista se escribe. */
const OTRO_PLAN = 'otro';

/**
 * Lo que distingue a los dos documentos, en el orden en que el asesor lo decide:
 * cuándo se manda, qué lleva y cómo se entrega.
 */
const documentos = {
    inicial: {
        etiqueta: 'Ruta inicial',
        momento: 'Antes de la venta',
        resumen:
            'El gancho. Los cursos de la ruta por la que preguntó, tal como los armó Eje Cursos.',
        icono: Rocket,
        pasos: ['Cursos propuestos', 'PDF para mandarle'],
        accion: 'Armar la ruta',
        nota: 'Nadie ve nada todavía. Tú revisas antes de que salga.',
    },
    final: {
        etiqueta: 'Ruta final',
        momento: 'Ya cerró la membresía',
        resumen:
            'La cortesía. Los doce meses sobre el catálogo completo, con lo que su membresía incluye.',
        icono: FileDown,
        pasos: ['Los doce meses', 'PDF para mandarle'],
        accion: 'Armar los doce meses',
        nota: 'La vigencia va impresa y ordena los meses.',
    },
} as const;

const hoy = fechaLocal(new Date());

function fechaLocal(fecha: Date): string {
    return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
}

function unAnioDespues(desde: string): string {
    const fecha = new Date(`${desde}T12:00:00`);
    fecha.setFullYear(fecha.getFullYear() + 1);
    fecha.setDate(fecha.getDate() - 1);

    return fechaLocal(fecha);
}

/**
 * La única pantalla de entrada del módulo.
 *
 * Los dos documentos comparten los mismos campos —quién es, cómo contactarlo, a
 * qué se dedica, en qué área y por cuál ruta preguntó—, así que son un solo
 * formulario y no dos pantallas gemelas. El tipo decide a qué endpoint se envía y si además
 * se piden la vigencia y el plan, que solo existen cuando ya hay membresía.
 */
/** De 5 en 5 hasta el tope de 40; cualquier otro, de 1 a 40, va en el campo de al lado. */
const HORAS_RAPIDAS = [5, 10, 15, 20, 25, 30, 35, 40];

/** Un número de México sin lada. La lada va fija junto al campo. */
const DIGITOS_TELEFONO = 10;

/**
 * Lo que se teclee o se pegue, reducido a los diez dígitos.
 *
 * Si se pega con la lada —«+52 442 118 5502», o «521…» como lo copia WhatsApp—
 * se le quita, para que el campo no se quede con los dos primeros dígitos de más
 * y corte los últimos.
 */
function soloDiezDigitos(valor: string): string {
    const digitos = valor.replace(/\D+/g, '');
    const sinLada =
        digitos.length === 13 && digitos.startsWith('521')
            ? digitos.slice(3)
            : digitos.length === 12 && digitos.startsWith('52')
              ? digitos.slice(2)
              : digitos;

    return sinLada.slice(0, DIGITOS_TELEFONO);
}

export default function GenerarRuta({
    tipo: tipoInicial,
    embudos,
    areas,
    planes,
    precargado,
    editando,
}: PageProps) {
    const [tipo, setTipo] = useState<Tipo>(tipoInicial);
    const [embudo, setEmbudo] = useState<string>(
        precargado.funnel_id ? String(precargado.funnel_id) : '',
    );
    const [area, setArea] = useState<string>(precargado.area);
    const [plan, setPlan] = useState<string>(precargado.plan);
    const [telefono, setTelefono] = useState<string>(
        soloDiezDigitos(precargado.phone ?? ''),
    );
    const [horas, setHoras] = useState<string>(
        precargado.weekly_hours ? String(precargado.weekly_hours) : '',
    );
    const [desde, setDesde] = useState(precargado.desde ?? hoy);
    const [hasta, setHasta] = useState(
        precargado.hasta ?? unAnioDespues(precargado.desde ?? hoy),
    );

    // Corregir es rehacer el documento encima del mismo renglón. Ni el tipo ni el
    // embudo se vuelven a elegir: cambiar cualquiera de los dos sería otro
    // documento, no este corregido.
    const corrigiendo = editando !== null;

    const esFinal = tipo === 'final';
    const documento = documentos[tipo];

    const elegido = embudos.find((opcion) => String(opcion.value) === embudo);
    const sinEmbudos = embudos.length === 0;

    return (
        <AppLayout>
            <Head title={documento.etiqueta} />

            <div className="mx-auto w-full max-w-3xl">
                <Pasos
                    actual="Datos del lead"
                    siguientes={[...documento.pasos]}
                />

                <Heading
                    overline={
                        corrigiendo
                            ? `Corregir ${documento.etiqueta.toLowerCase()}`
                            : 'Generar documento'
                    }
                    title={
                        corrigiendo
                            ? 'Corrige lo que salió mal'
                            : '¿Para quién es la ruta?'
                    }
                    description={
                        corrigiendo
                            ? 'Se rehace el documento sobre el mismo renglón: el PDF anterior se reemplaza y el enlace que ya le mandaste abre el corregido.'
                            : 'Con esto armo la propuesta. La revisas completa antes de que se genere nada.'
                    }
                />

                {/*
                 * Al corregir, lo ya emitido se dice en una línea: cuántas veces se
                 * rehízo —para que nadie descubra a la cuarta que este documento se
                 * ha rehecho tres— y por dónde volver sin tocar nada.
                 */}
                {editando && (
                    <div className="bg-muted/60 text-muted-foreground mb-8 flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm">
                        <span>
                            {editando.revisiones === 0
                                ? 'Es la primera corrección de este documento.'
                                : `Este documento ya se corrigió ${editando.revisiones} ${editando.revisiones === 1 ? 'vez' : 'veces'}.`}
                            {editando.embudo &&
                                ` Sigue siendo la ruta «${editando.embudo}».`}
                        </span>
                        <Link
                            href={show(editando.id)}
                            className="underline underline-offset-4"
                        >
                            Dejarlo como está
                        </Link>
                    </div>
                )}

                {/*
                 * El tipo va primero y se elige a la vista, no en un desplegable:
                 * es la decisión que cambia el documento entero, y el asesor la
                 * toma según dónde va la conversación.
                 *
                 * Al corregir no se ofrece: un gancho no se vuelve anual editándolo
                 * —el anual se emite aparte, desde la ficha del gancho— y el
                 * endpoint que recibe la corrección es el del documento que ya es.
                 */}
                <fieldset className="mb-8" hidden={corrigiendo}>
                    <legend className="sr-only">Tipo de documento</legend>

                    <div className="grid gap-3 sm:grid-cols-2">
                        {(Object.keys(documentos) as Tipo[]).map((clave) => {
                            const opcion = documentos[clave];
                            const Icono = opcion.icono;
                            const activo = tipo === clave;

                            return (
                                <button
                                    key={clave}
                                    type="button"
                                    onClick={() => setTipo(clave)}
                                    aria-pressed={activo}
                                    className={cn(
                                        'group rounded-xl border p-4 text-left transition-all',
                                        'focus-visible:ring-ring focus-visible:ring-offset-background focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
                                        activo
                                            ? 'border-primary bg-accent shadow-card'
                                            : 'border-border bg-card hover:border-primary/40 hover:bg-accent/40',
                                    )}
                                >
                                    <span className="flex items-center gap-2.5">
                                        <span
                                            className={cn(
                                                'flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors',
                                                activo
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'bg-muted text-muted-foreground group-hover:text-accent-foreground',
                                            )}
                                        >
                                            <Icono className="size-4.5" />
                                        </span>
                                        <span>
                                            <span
                                                className={cn(
                                                    'font-subtitle block text-lg leading-none font-bold',
                                                    activo
                                                        ? 'text-accent-foreground'
                                                        : 'text-foreground',
                                                )}
                                            >
                                                {opcion.etiqueta}
                                            </span>
                                            <span className="text-muted-foreground mt-1 block text-xs">
                                                {opcion.momento}
                                            </span>
                                        </span>
                                    </span>

                                    <span className="text-muted-foreground mt-3 block text-sm leading-relaxed">
                                        {opcion.resumen}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </fieldset>

                {/*
                 * `key` fuerza a React a rehacer el formulario al cambiar de
                 * documento: los dos mandan a endpoints distintos y con nombres
                 * de campo distintos, y arrastrar el estado del anterior deja
                 * errores de validación que ya no corresponden.
                 */}
                <Form
                    key={tipo}
                    {...(editando
                        ? esFinal
                            ? AnnualRouteController.repropose.form(editando.id)
                            : LearningRouteController.repropose.form(
                                  editando.id,
                              )
                        : esFinal
                          ? AnnualRouteController.propose.form()
                          : LearningRouteController.propose.form())}
                    // La validación es la del servidor, con sus mensajes en español
                    // pegados a cada campo; la del navegador sale en una burbuja
                    // que desaparece y en el idioma del sistema operativo.
                    noValidate
                    onError={avisarErrores}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="external_id"
                                defaultValue={precargado.external_id}
                            />
                            {esFinal && precargado.lead_id !== null && (
                                <input
                                    type="hidden"
                                    name="lead_id"
                                    defaultValue={precargado.lead_id}
                                />
                            )}

                            <div className="bg-card shadow-card space-y-6 rounded-xl border p-4 sm:p-6">
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid content-start gap-2">
                                        <EtiquetaCampo
                                            requerido
                                            htmlFor={
                                                esFinal ? 'full_name' : 'name'
                                            }
                                        >
                                            {esFinal
                                                ? '¿Cómo se llama? (completo)'
                                                : '¿Cómo se llama?'}
                                        </EtiquetaCampo>
                                        <Input
                                            id={esFinal ? 'full_name' : 'name'}
                                            name={
                                                esFinal ? 'full_name' : 'name'
                                            }
                                            required
                                            autoFocus
                                            autoComplete="off"
                                            aria-invalid={
                                                !!(esFinal
                                                    ? errors.full_name
                                                    : errors.name) || undefined
                                            }
                                            defaultValue={precargado.nombre}
                                            placeholder="Ej. Mariana Cruz Aguilar"
                                        />
                                        <p className="sys-ayuda text-muted-foreground text-xs">
                                            Va impreso en la portada.
                                        </p>
                                        <InputError
                                            message={
                                                esFinal
                                                    ? errors.full_name
                                                    : errors.name
                                            }
                                        />
                                    </div>

                                    <div className="grid content-start gap-2">
                                        <EtiquetaCampo
                                            requerido
                                            htmlFor="occupation"
                                        >
                                            ¿A qué se dedica?
                                        </EtiquetaCampo>
                                        <Input
                                            id="occupation"
                                            name="occupation"
                                            required
                                            autoComplete="off"
                                            aria-invalid={
                                                !!errors.occupation || undefined
                                            }
                                            defaultValue={precargado.occupation}
                                            placeholder="Ej. Ingeniera de procesos en una automotriz"
                                        />
                                        <p className="sys-ayuda text-muted-foreground text-xs">
                                            De aquí salen los cursos que te voy
                                            a proponer.
                                        </p>
                                        <InputError
                                            message={errors.occupation}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid content-start gap-2">
                                        <EtiquetaCampo
                                            htmlFor="phone"
                                            requerido={esFinal}
                                            opcional={!esFinal}
                                        >
                                            ¿Cuál es su WhatsApp?
                                        </EtiquetaCampo>
                                        {/*
                                         * Los leads son de México: la lada va
                                         * fija y solo se piden los diez dígitos.
                                         * El campo no acepta otra cosa, así que
                                         * un número a medias o con un dígito de
                                         * más no llega a enviarse.
                                         */}
                                        <div className="flex">
                                            <span className="bg-muted text-muted-foreground inline-flex items-center rounded-l-md border border-r-0 px-3 text-sm select-none">
                                                +52
                                            </span>
                                            <Input
                                                id="phone"
                                                name="phone"
                                                required={esFinal}
                                                type="tel"
                                                inputMode="numeric"
                                                autoComplete="off"
                                                minLength={DIGITOS_TELEFONO}
                                                maxLength={DIGITOS_TELEFONO}
                                                pattern={`\\d{${DIGITOS_TELEFONO}}`}
                                                title="Los 10 dígitos del número, sin la lada"
                                                aria-invalid={
                                                    !!errors.phone || undefined
                                                }
                                                className="rounded-l-none tabular-nums"
                                                value={telefono}
                                                onChange={(event) =>
                                                    setTelefono(
                                                        soloDiezDigitos(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                                onPaste={(event) => {
                                                    event.preventDefault();
                                                    setTelefono(
                                                        soloDiezDigitos(
                                                            event.clipboardData.getData(
                                                                'text',
                                                            ),
                                                        ),
                                                    );
                                                }}
                                                placeholder="442 118 5502"
                                            />
                                        </div>
                                        <p className="sys-ayuda text-muted-foreground text-xs">
                                            {esFinal
                                                ? 'El mismo con el que lo capturaste antes: es con lo que lo reconozco y no lo doy de alta dos veces.'
                                                : 'Si ya lo tienes, con esto lo reconozco cuando vuelva por su ruta final. Si todavía no, sigue sin él.'}
                                        </p>
                                        <InputError message={errors.phone} />
                                    </div>

                                    {/*
                                     * El área es el único dato de la captura que
                                     * no se escribe: se elige. Por eso pesa en lo
                                     * que el motor recomienda —«Por el área en la
                                     * que trabaja»— mientras que la ocupación solo
                                     * desempata. «Otra» abre el campo abierto, y lo
                                     * que se escriba ahí se guarda estandarizado.
                                     */}
                                    <div className="grid content-start gap-2">
                                        <EtiquetaCampo htmlFor="area" opcional>
                                            ¿En qué área trabaja?
                                        </EtiquetaCampo>
                                        <Select
                                            name="area"
                                            value={area}
                                            onValueChange={setArea}
                                        >
                                            <SelectTrigger
                                                id="area"
                                                className="w-full"
                                                aria-invalid={
                                                    !!errors.area || undefined
                                                }
                                            >
                                                <SelectValue placeholder="— Elige el área —" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {areas.map((opcion) => (
                                                    <SelectItem
                                                        key={opcion.value}
                                                        value={opcion.value}
                                                    >
                                                        {opcion.label}
                                                    </SelectItem>
                                                ))}
                                                <SelectItem value={OTRA_AREA}>
                                                    Otra — la escribo
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {area === OTRA_AREA ? (
                                            <Input
                                                autoFocus
                                                id="area_other"
                                                name="area_other"
                                                aria-label="Escribe el área"
                                                aria-invalid={
                                                    !!errors.area_other ||
                                                    undefined
                                                }
                                                autoComplete="off"
                                                defaultValue={
                                                    precargado.area_other
                                                }
                                                placeholder="Ej. Control de gestión"
                                            />
                                        ) : (
                                            <p className="sys-ayuda text-muted-foreground text-xs">
                                                Con esto elijo qué le recomiendo
                                                además de lo que pidió.
                                            </p>
                                        )}
                                        <InputError message={errors.area} />
                                        <InputError
                                            message={errors.area_other}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid content-start gap-2">
                                        <EtiquetaCampo
                                            htmlFor="funnel_id"
                                            requerido={!corrigiendo}
                                        >
                                            ¿Por cuál ruta preguntó?
                                        </EtiquetaCampo>
                                        {/*
                                         * Al corregir se muestra, no se elige: el
                                         * embudo es de qué campaña salió este
                                         * documento, y cambiarlo sería otro
                                         * documento. Viaja en un campo oculto
                                         * porque el endpoint lo sigue exigiendo.
                                         */}
                                        {corrigiendo ? (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="funnel_id"
                                                    value={embudo}
                                                />
                                                <p
                                                    id="funnel_id"
                                                    className="bg-muted/50 rounded-md border px-3 py-2 text-sm break-words"
                                                >
                                                    {elegido?.label ??
                                                        editando?.embudo ??
                                                        '—'}
                                                </p>
                                            </>
                                        ) : (
                                            <Select
                                                name="funnel_id"
                                                value={embudo}
                                                onValueChange={setEmbudo}
                                            >
                                                <SelectTrigger
                                                    id="funnel_id"
                                                    className="w-full"
                                                    title={elegido?.label}
                                                    aria-invalid={
                                                        !!errors.funnel_id ||
                                                        undefined
                                                    }
                                                >
                                                    <SelectValue placeholder="— Elige la ruta —" />
                                                </SelectTrigger>
                                                <SelectContent className="max-w-(--radix-select-trigger-width)">
                                                    {embudos.map((opcion) => (
                                                        <SelectItem
                                                            key={opcion.value}
                                                            value={String(
                                                                opcion.value,
                                                            )}
                                                            className="whitespace-normal"
                                                        >
                                                            {/*
                                                             * El conteo va dentro del
                                                             * mismo texto: es lo que
                                                             * se ve en el campo ya
                                                             * elegido, y ahí se corta
                                                             * con puntos suspensivos
                                                             * si el nombre es largo.
                                                             */}
                                                            {
                                                                opcion.label
                                                            } ·{' '}
                                                            {opcion.cursos}{' '}
                                                            curso
                                                            {opcion.cursos === 1
                                                                ? ''
                                                                : 's'}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                        <p className="sys-ayuda text-muted-foreground text-xs">
                                            {elegido
                                                ? esFinal
                                                    ? `${elegido.especialidad ?? 'Su rama'} abre el mes 1, para que sienta continuidad.`
                                                    : `Sus ${elegido.cursos} cursos van completos y sin moverse. Encima te propongo 2 más —los que le vas a mencionar en el audio— y esos sí los ajustas en el siguiente paso.`
                                                : esFinal
                                                  ? 'Abre el mes 1, para que sienta continuidad.'
                                                  : 'Sus cursos van completos y sin moverse. Encima te propongo 2 más —los que le vas a mencionar en el audio— y esos sí los ajustas en el siguiente paso.'}
                                        </p>
                                        <InputError
                                            message={errors.funnel_id}
                                        />
                                    </div>

                                    <div className="grid content-start gap-2">
                                        <EtiquetaCampo
                                            htmlFor="context"
                                            opcional
                                        >
                                            ¿Te dijo algo más?
                                        </EtiquetaCampo>
                                        <Input
                                            id="context"
                                            name="context"
                                            autoComplete="off"
                                            aria-invalid={
                                                !!errors.context || undefined
                                            }
                                            defaultValue={precargado.context}
                                            placeholder="Ej. Quiere liderar los proyectos de reducción de scrap"
                                        />
                                        <p className="sys-ayuda text-muted-foreground text-xs">
                                            A dónde quiere llegar. Lo uso para
                                            escoger entre cursos parecidos; si
                                            no te dijo nada, déjalo vacío.
                                        </p>
                                        <InputError message={errors.context} />
                                    </div>
                                </div>
                            </div>

                            {/*
                             * Vigencia y plan solo existen cuando ya hay
                             * membresía: pedirlos antes de la venta sería pedir
                             * un dato que todavía no existe.
                             */}
                            {esFinal && (
                                <div className="bg-card shadow-card space-y-5 rounded-xl border p-4 sm:p-6">
                                    <Heading
                                        variant="small"
                                        title="La membresía"
                                        description="Sin la vigencia no se genera: va impresa y ordena los doce meses."
                                    />

                                    <div className="flex flex-wrap items-end gap-4 max-sm:gap-3">
                                        <div className="grid flex-1 gap-1.5 max-sm:basis-[45%]">
                                            <EtiquetaCampo
                                                htmlFor="starts_on"
                                                requerido
                                            >
                                                Desde
                                            </EtiquetaCampo>
                                            <Input
                                                id="starts_on"
                                                type="date"
                                                name="starts_on"
                                                className="sm:w-48"
                                                aria-invalid={
                                                    !!errors.starts_on ||
                                                    undefined
                                                }
                                                value={desde}
                                                onChange={(event) =>
                                                    setDesde(event.target.value)
                                                }
                                            />
                                        </div>

                                        <div className="grid flex-1 gap-1.5 max-sm:basis-[45%]">
                                            <EtiquetaCampo
                                                htmlFor="ends_on"
                                                requerido
                                            >
                                                Hasta
                                            </EtiquetaCampo>
                                            <Input
                                                id="ends_on"
                                                type="date"
                                                name="ends_on"
                                                className="sm:w-48"
                                                aria-invalid={
                                                    !!errors.ends_on ||
                                                    undefined
                                                }
                                                value={hasta}
                                                onChange={(event) =>
                                                    setHasta(event.target.value)
                                                }
                                            />
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="max-sm:w-full"
                                            onClick={() =>
                                                setHasta(unAnioDespues(desde))
                                            }
                                        >
                                            Un año exacto
                                        </Button>
                                    </div>

                                    <InputError message={errors.starts_on} />
                                    <InputError message={errors.ends_on} />

                                    {/*
                                     * El ritmo del lead. Decide cuántos cursos
                                     * caben en su año y dónde termina cada mes,
                                     * y va impreso. Sin valor por defecto: uno
                                     * precargado se queda sin que nadie se lo
                                     * pregunte al lead.
                                     */}
                                    <div className="grid content-start gap-2">
                                        <EtiquetaCampo
                                            htmlFor="weekly_hours"
                                            requerido
                                        >
                                            ¿Cuántas horas a la semana puede
                                            estudiar?
                                        </EtiquetaCampo>
                                        <input
                                            type="hidden"
                                            name="weekly_hours"
                                            value={horas}
                                        />
                                        <div className="flex flex-wrap items-center gap-2">
                                            {HORAS_RAPIDAS.map((opcion) => (
                                                <Button
                                                    key={opcion}
                                                    type="button"
                                                    variant={
                                                        horas === String(opcion)
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    className="min-w-12"
                                                    aria-pressed={
                                                        horas === String(opcion)
                                                    }
                                                    onClick={() =>
                                                        setHoras(String(opcion))
                                                    }
                                                >
                                                    {opcion} h
                                                </Button>
                                            ))}
                                            <div className="flex items-center gap-2">
                                                <span className="text-muted-foreground text-sm">
                                                    otro:
                                                </span>
                                                <Input
                                                    id="weekly_hours"
                                                    type="number"
                                                    min={1}
                                                    max={40}
                                                    step={1}
                                                    inputMode="numeric"
                                                    className="w-20"
                                                    aria-label="Otro número de horas por semana"
                                                    aria-invalid={
                                                        !!errors.weekly_hours ||
                                                        undefined
                                                    }
                                                    value={
                                                        HORAS_RAPIDAS.includes(
                                                            Number(horas),
                                                        )
                                                            ? ''
                                                            : horas
                                                    }
                                                    onChange={(event) =>
                                                        setHoras(
                                                            event.target.value
                                                                .replace(
                                                                    /\D+/g,
                                                                    '',
                                                                )
                                                                .slice(0, 2),
                                                        )
                                                    }
                                                    placeholder="—"
                                                />
                                                <span className="text-muted-foreground text-sm">
                                                    h
                                                </span>
                                            </div>
                                        </div>
                                        <p className="sys-ayuda text-muted-foreground text-xs">
                                            De aquí sale cuántos cursos caben en
                                            su año y cómo se reparten los meses.
                                        </p>
                                        <InputError
                                            message={errors.weekly_hours}
                                        />
                                    </div>

                                    {/*
                                     * El plan va impreso en la portada, así que se
                                     * elige de la lista publicada y no se teclea:
                                     * con campo abierto el mismo plan llegaba
                                     * escrito de tres formas a tres leads.
                                     *
                                     * El importe no se imprime en ningún lado del
                                     * documento. Se asienta aquí porque si no, lo
                                     * que se cobró solo existe en el chat.
                                     */}
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div className="grid content-start gap-2">
                                            <EtiquetaCampo
                                                htmlFor="plan"
                                                requerido
                                            >
                                                ¿Qué plan cerró?
                                            </EtiquetaCampo>
                                            <Select
                                                name="plan"
                                                value={plan}
                                                onValueChange={setPlan}
                                            >
                                                <SelectTrigger
                                                    id="plan"
                                                    className="w-full"
                                                    aria-invalid={
                                                        !!errors.plan ||
                                                        undefined
                                                    }
                                                >
                                                    <SelectValue placeholder="— Elige el plan —" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {planes.map((opcion) => (
                                                        <SelectItem
                                                            key={opcion.value}
                                                            value={opcion.value}
                                                        >
                                                            {opcion.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {plan === OTRO_PLAN ? (
                                                <Input
                                                    autoFocus
                                                    id="plan_other"
                                                    name="plan_other"
                                                    aria-label="Escribe el nombre del plan"
                                                    aria-invalid={
                                                        !!errors.plan_other ||
                                                        undefined
                                                    }
                                                    autoComplete="off"
                                                    defaultValue={
                                                        precargado.plan_other
                                                    }
                                                    placeholder="Ej. Corporativo 5 licencias"
                                                />
                                            ) : (
                                                <p className="sys-ayuda text-muted-foreground text-xs">
                                                    Va impreso en su portada.
                                                </p>
                                            )}
                                            <InputError message={errors.plan} />
                                            <InputError
                                                message={errors.plan_other}
                                            />
                                        </div>

                                        <div className="grid content-start gap-2">
                                            <EtiquetaCampo
                                                htmlFor="price"
                                                requerido
                                            >
                                                ¿Por cuánto se cerró?
                                            </EtiquetaCampo>
                                            <div className="relative">
                                                <span className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm">
                                                    $
                                                </span>
                                                <Input
                                                    id="price"
                                                    name="price"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    inputMode="decimal"
                                                    className="pl-7"
                                                    aria-invalid={
                                                        !!errors.price ||
                                                        undefined
                                                    }
                                                    defaultValue={
                                                        precargado.price
                                                    }
                                                    placeholder="0.00"
                                                />
                                            </div>
                                            <p className="sys-ayuda text-muted-foreground text-xs">
                                                Queda en su expediente. No sale
                                                en el documento.
                                            </p>
                                            <InputError
                                                message={errors.price}
                                            />
                                        </div>
                                    </div>
                                </div>
                            )}

                            {sinEmbudos && (
                                <Alert>
                                    <AlertTitle>
                                        Todavía no hay rutas armadas.
                                    </AlertTitle>
                                    <AlertDescription>
                                        El documento sale de una ruta curada:
                                        alguien tiene que armarla primero en
                                        Embudos, con los cursos de la rama. En
                                        cuanto exista una con cursos, aparece
                                        aquí.
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="flex flex-wrap items-center gap-4">
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="max-sm:w-full"
                                    disabled={
                                        processing ||
                                        (sinEmbudos && !corrigiendo)
                                    }
                                >
                                    {processing && <Spinner />}
                                    {corrigiendo
                                        ? 'Revisar los cursos'
                                        : documento.accion}
                                </Button>
                                <span className="text-muted-foreground text-sm">
                                    {corrigiendo
                                        ? 'Todavía no se reemplaza nada: revisas los cursos y hasta entonces se rehace.'
                                        : documento.nota}
                                </span>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
