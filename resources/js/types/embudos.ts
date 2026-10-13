/** El renglón del listado interno. */
export type EmbudoResumen = {
    id: number;
    nombre: string;
    slug: string;
    cursos: number;
    creado_en: string | null;
    autor?: string | null;
};

/** El embudo abierto: su nombre y los cursos que lo componen, en orden. */
export type Embudo = {
    id: number;
    nombre: string;
    slug: string;
    creado_en: string | null;
    autor: string | null;
    especialidad_principal_id: number | null;
    cursos: number[];
    /** Códigos de regla que quien curó el embudo decidió ignorar. */
    reglas_ignoradas: string[];
};

export type Curso = {
    id: number;
    nombre: string;
    slug: string;
    especialidad_id: number;
    unidad_contenido_id: number;
    nivel: string;
    horas: number | null;
    revisar_traslape: boolean;
    revisar_duracion: boolean;
};

export type Especialidad = {
    id: number;
    nombre: string;
    roles_permitidos: Record<string, boolean> | null;
};

/** En qué otros embudos vive ya cada curso. Avisa; nunca impide guardar. */
export type Traslapes = Record<number, { id: number; nombre: string }[]>;

/** Una corrección lista para aplicar: la lista de cursos tal como quedaría. */
export type Sugerencia = {
    etiqueta: string;
    curso_ids: number[];
};

/** Un hallazgo del validador, redactado para quien arma la ruta. */
export type Hallazgo = {
    codigo: string;
    mensaje: string;
    curso_ids: number[];
    detalles: Record<string, unknown>;
    /** Falso para las ocho restricciones críticas: esas no se ignoran. */
    ignorable: boolean;
    sugerencias: Sugerencia[];
};

export type Totales = {
    cursos: number;
    programas: number;
    horas_netas: number;
};

export type Validacion = {
    bloqueantes: Hallazgo[];
    advertencias: Hallazgo[];
    /** Lo que el embudo ignora: ya no frena el documento. */
    ignorados: Hallazgo[];
    totales: Totales;
};

export type PermisosEmbudo = {
    editar: boolean;
    eliminar: boolean;
};

/** El borrador que el armador manda a validar sin guardarlo. */
export type BorradorRuta = {
    items: { course_id: number }[];
    copy_text: string[];
    waived_rules: string[];
};
