export type EstadoRuta =
    | 'draft'
    | 'validated'
    | 'rejected'
    | 'rendering'
    | 'rendered'
    | 'failed';

export type RutaResumen = {
    id: number;
    lead: string;
    telefono: string | null;
    documento: string;
    documento_slug: string;
    especialidad?: string | null;
    estado: EstadoRuta;
    estado_etiqueta: string;
    cursos: number;
    programas: number;
    generada_en: string | null;
    /** Cuántas veces se rehízo el documento después de emitirse. */
    revisiones: number;
    corregida_en: string | null;
    aperturas: number;
    enlace: string | null;
};

export type CursoDelDocumento = {
    orden: number;
    mes_sugerido: number | null;
    nombre: string;
    especialidad: string | null;
    horas: number | null;
    frase_resultado: string | null;
    capa: string;
    rol: string;
    horas_absorbidas: boolean;
};

export type TotalesRuta = {
    cursos: number;
    programas: number;
    horas_netas: number;
};

export type Rechazo = {
    codigo: string;
    mensaje: string;
};

export type LeadDeRuta = {
    id: number;
    nombre: string;
    telefono: string | null;
    email: string | null;
    ocupacion: string | null;
    /** El valor de la lista, o el área nueva ya estandarizada. */
    area: string | null;
    contexto: string | null;
    estado: string;
    especialidad: string | null;
    /** Con qué se precarga su ruta final sin volver a capturarlo. */
    embudo: string | null;
    external_id: string | null;
};

/** Una ruta curada, tal como la ve el asesor en su selector. */
export type EmbudoOpcion = {
    value: number;
    label: string;
    cursos: number;
    especialidad: string | null;
};

/** Lo que la URL trae ya resuelto: Kommo mañana, el detalle de una ruta hoy. */
export type Precargado = {
    nombre: string;
    phone: string;
    occupation: string;
    context: string;
    /** Una de las catorce, o «otro» cuando lo que venía no estaba en la lista. */
    area: string;
    area_other: string;
    /** Uno de los planes publicados, o «otro» cuando se negoció fuera de la lista. */
    plan: string;
    plan_other: string;
    /** Lo que se cobró. Se asienta en la membresía; no se imprime en el documento. */
    price: string;
    /** La vigencia asentada, al corregir. Nula en una captura nueva: la pone la pantalla. */
    desde: string | null;
    hasta: string | null;
    /** Las horas a la semana que el lead dijo que puede estudiar. Sin valor por defecto. */
    weekly_hours: number | null;
    external_id: string;
    /** El lead del gancho, cuando la final se arma desde él: la emite sobre el mismo lead. */
    lead_id: number | null;
    funnel_id: number | null;
};

/** El área del lead, tal como se despliega en la captura. */
export type AreaOpcion = {
    value: string;
    label: string;
};

/** La membresía asentada, tal como la comprueba el asesor después de emitir. */
export type MembresiaFicha = {
    plan: string | null;
    /** Lo que se cobró. No sale en el documento; vive en el expediente. */
    importe: string | null;
    desde: string | null;
    hasta: string | null;
};

/** Un plan publicado, tal como se despliega al cerrar la membresía. */
export type PlanOpcion = {
    value: string;
    label: string;
};

/** Un bloque de la propuesta: una especialidad con su porqué y sus cursos. */
export type BloquePropuesto = {
    especialidad_id: number;
    especialidad: string | null;
    /**
     * El mes sugerido, solo en la ruta final. Dos especialidades pueden caer en el
     * mismo mes y cada una conserva su bloque: comparten mes, jamás encabezado.
     * `null` en la ruta inicial, que no tiene meses, y en lo que el asesor agrega.
     */
    mes: number | null;
    /**
     * ¿Es parte de la ruta principal?
     *
     * Lo son todos los bloques que traen cursos del embudo, sin importar de qué
     * especialidad sean: la campaña los eligió y el documento los lleva tal cual.
     * Un bloque principal no se poda ni se mueve.
     */
    principal: boolean;
    /** El bloque que abre el documento: la especialidad por la que preguntó. */
    abre?: boolean;
    justificacion: string | null;
    cursos_en_la_especialidad: number;
    cursos: CursoPropuesto[];
};

/** Un renglón de la propuesta. `fijo` = vino del embudo y no se toca. */
export type CursoPropuesto = {
    id: number;
    nombre: string;
    horas: number | null;
    orden: number;
    fijo?: boolean;
    /** En los doce meses: el mes en que termina, si a su ritmo abarca más de uno. */
    mes_fin?: number | null;
};

export type CursoDelCatalogo = {
    id: number;
    nombre: string;
    especialidad: string | null;
    especialidad_id: number;
    horas: number | null;
};

/** Llevar un curso justo antes de otro: el arreglo de un orden que no se sostiene. */
export type ArregloDeSecuencia = { mover: number; antes_de: number };

export type Bloqueo = {
    codigo: string;
    mensaje: string;
    /** Solo en los bloqueos de orden, y solo si hay algo que mover. */
    arreglo?: ArregloDeSecuencia[];
};

/** El archivo que el asesor adjunta en el chat. */
export type PdfDeRuta = {
    listo: boolean;
    url: string;
    nombre: string;
};

/** Uno de los dos documentos de un lead, tal como aparece en su renglón de la bandeja. */
export type DocumentoEnBandeja = {
    id: number;
    documento: string;
    asesor: string | null;
    creada_en: string | null;
    /**
     * Cuántas veces se corrigió, donde antes iba el estado.
     *
     * El estado decía «Lista» en todos los renglones —se emite y se renderiza en
     * el mismo minuto— y esto sí distingue un documento de otro.
     */
    revisiones: number;
    corregida_en: string | null;
    aperturas: number;
    pdf: boolean;
};

/**
 * Un renglón de la bandeja: un lead con sus dos documentos (2026-09-23).
 *
 * `final` en nulo es un lead que todavía no cierra; `para_final` trae lo que el
 * formulario de la final recibe por URL para no volver a capturarlo.
 */
export type RutaEnBandeja = {
    id: number;
    lead: string;
    telefono: string | null;
    especialidad: string | null;
    asesor: string | null;
    ultima_actividad: string | null;
    inicial: DocumentoEnBandeja | null;
    final: DocumentoEnBandeja | null;
    vigencia: { desde: string; hasta: string } | null;
    /** Documentos repetidos del mismo lead: el renglón muestra el último de cada tipo. */
    anteriores: { id: number; documento: string; creada_en: string | null }[];
    /** El mensaje escrito que sustituye al audio, listo para pegar en WhatsApp. */
    mensaje: string | null;
    para_final: Record<string, string> | null;
};

/**
 * El guión del audio que acompaña a la ruta inicial, congelado en su snapshot.
 * Ver App\Services\Documents\AudioScript.
 */
export type GuionDeAudio = {
    bloques: {
        clave: string;
        titulo: string;
        segmentos: { texto: string; dato: string | null; enfasis: boolean }[];
        palabras: number;
        segundos: number;
    }[];
    palabras: number;
    segundos: number;
    menciones_nombre: number;
    texto: string;
};

/**
 * El mensaje escrito que sustituye al audio cuando el asesor no puede grabar.
 * `texto` ya trae las negritas de WhatsApp. Ver App\Services\Documents\WrittenMessage.
 */
export type MensajeEscrito = {
    bloques: {
        clave: string;
        titulo: string;
        segmentos: { texto: string; dato: string | null; enfasis: boolean }[];
    }[];
    palabras: number;
    texto: string;
};

/**
 * La ruta que se está corrigiendo, cuando la captura y la revisión no vienen de
 * una emisión nueva.
 *
 * Presente significa «esto pisa un documento que ya salió»: el formulario manda a
 * otro endpoint, el embudo deja de elegirse y el botón dice corregir en vez de
 * generar.
 */
export type RutaEditandose = {
    id: number;
    revisiones: number;
    /** El embudo que la sembró. Se muestra, no se elige: corregir no lo cambia. */
    embudo: string | null;
};

export type OpcionSimple = { value: string; label: string };

/**
 * Un párrafo del documento que el asesor puede ajustar a mano en la revisión.
 * `texto` es el que imprime el catálogo. Ver App\Services\Documents\EditableCopy.
 */
export type ParrafoEditable = {
    slug: string;
    etiqueta: string;
    texto: string;
};

/** Lo que el asesor ajustó, por la clave del párrafo. */
export type AjustesDeTexto = Record<string, string>;
