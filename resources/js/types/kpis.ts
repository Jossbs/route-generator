/** Lo que el backend manda al tablero y a las pantallas del módulo de KPIs. */

export type Unidad = 'piezas' | 'dinero' | 'porcentaje' | 'numero';

export type Estado =
    | 'logrado'
    | 'en_ritmo'
    | 'cerca'
    | 'por_alcanzar'
    | 'sin_meta';

export type OpcionSimple = { value: string; label: string };

export type Periodo = {
    type: 'dia' | 'semana' | 'mes' | 'trimestre';
    start: string;
    end: string;
    label: string;
};

export type Avance = {
    value: number | null;
    target: number | null;
    expected: number | null;
    percent: number | null;
    per_day_needed: number | null;
    remaining_days: number;
    status: Estado;
    milestone: number | null;
    value_label: string;
    target_label: string;
    expected_label: string;
    message: string;
};

export type PuntoSerie = {
    date: string;
    value: number | null;
    cumulative: number | null;
    expected: number | null;
};

export type FilaRanking = {
    id: number;
    name: string;
    team: string | null;
    value: number | null;
    value_label: string;
    target: number | null;
    target_label: string;
    percent: number | null;
    status: Estado;
    milestone: number | null;
    streak: number;
    best_month: { month: string; value: number } | null;
    best_month_label: string | null;
    is_personal_best: boolean;
    position: number;
};

export type MedidaTablero = {
    id: number;
    slug: string;
    name: string;
    unit: Unidad;
    summable: boolean;
    higher_is_better: boolean;
    has_target: boolean;
    progress: Avance;
    series: PuntoSerie[];
    ranking: FilaRanking[];
};

export type KpiTablero = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    measures: MedidaTablero[];
};

export type CampoEvento = {
    id?: number;
    key: string;
    label: string;
    data_type: 'numero' | 'dinero' | 'texto' | 'opcion' | 'si_no' | 'fecha';
    options: OpcionSimple[];
    is_required: boolean;
};

export type TipoEvento = {
    id: number;
    slug?: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    events_count?: number;
    fields: CampoEvento[];
};

export type Evento = {
    id: number;
    type_id: number;
    type: string;
    user_id: number;
    advisor: string;
    occurred_on: string;
    values: Record<string, string | number | boolean>;
    summary: string;
    notes: string | null;
    source: string;
    status: 'sugerido' | 'confirmado' | 'descartado';
    is_adjustment: boolean;
    adjustment_reason: string | null;
    voided: boolean;
    void_reason: string | null;
    creator: string | null;
};

export type Filtro = {
    field: string;
    op: '=' | '!=' | 'in' | '>' | '>=' | '<' | '<=';
    value: string | number | boolean | string[];
};

export type MedidaDefinicion = {
    id?: number;
    name: string;
    unit: Unidad;
    kind: 'agregado' | 'formula';
    sales_event_type_id: number | null;
    aggregation: string | null;
    field_key: string | null;
    filters: Filtro[];
    formula_operator: string | null;
    left_measure_id: number | null;
    right_measure_id: number | null;
    has_target: boolean;
    higher_is_better: boolean;
};

export type KpiDefinicion = {
    id?: number;
    name: string;
    description: string | null;
    display_order: number;
    is_active: boolean;
    measures: MedidaDefinicion[];
};
