<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\FormulaOperator;
use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\MeasureKind;
use App\Enums\Kpis\MeasureUnit;
use App\Enums\Kpis\SalesEventStatus;
use App\Enums\Kpis\TargetScope;
use App\Models\KpiMeasure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Calcula una medida sobre los eventos: el intérprete genérico del módulo.
 *
 * Es el equivalente de `RuleInterpreterRegistry` para los KPIs: la medida es un
 * dato y esta clase la interpreta. Un KPI nuevo no toca esta clase.
 *
 * Todo sale de un solo método, `grouped()`, que agrupa por las dimensiones que se
 * pidan —persona, día, mes— en una sola consulta. Las fórmulas se combinan
 * después, grupo por grupo, a partir de sus dos lados ya agrupados: así la
 * conversión de un asesor es sus ventas entre sus leads, y la del equipo es el
 * total entre el total, nunca el promedio de las conversiones.
 */
final class MeasureEvaluator
{
    /** Las dimensiones por las que se puede agrupar. */
    public const DIMENSIONS = ['user', 'day', 'month'];

    /** Los operadores de filtro que se aceptan. */
    public const FILTER_OPERATORS = ['=', '!=', 'in', '>', '>=', '<', '<='];

    /**
     * El valor total de la medida en el tramo, para el alcance.
     *
     * Las medidas sumables sin eventos valen cero —cero ventas es un dato—; un
     * promedio o una razón sin eventos no vale nada y regresa null.
     */
    public function value(KpiMeasure $measure, CarbonImmutable $from, CarbonImmutable $to, Scope $scope): ?float
    {
        return $this->grouped($measure, $from, $to, $scope)[''] ?? ($measure->isSummable() ? 0.0 : null);
    }

    /**
     * La medida agrupada. La llave es el valor de cada dimensión unido con `|`, en
     * el orden pedido: `12|2026-09-27` para persona y día. Sin dimensiones, la
     * llave es la cadena vacía.
     *
     * Solo aparecen los grupos que tienen eventos; quien lee decide si un grupo
     * ausente es cero o nada, con `isSummable()`.
     *
     * @param  list<string>  $dimensions
     * @return array<int|string, float|null>
     */
    public function grouped(
        KpiMeasure $measure,
        CarbonImmutable $from,
        CarbonImmutable $to,
        Scope $scope,
        array $dimensions = [],
    ): array {
        foreach ($dimensions as $dimension) {
            if (! in_array($dimension, self::DIMENSIONS, true)) {
                throw new InvalidArgumentException("No se puede agrupar por «{$dimension}».");
            }
        }

        $valores = $measure->kind === MeasureKind::Formula
            ? $this->formula($measure, $from, $to, $scope, $dimensions)
            : $this->aggregate($measure, $from, $to, $scope, $dimensions);

        if ($measure->kind === MeasureKind::Formula
            && $measure->formula_operator === FormulaOperator::Razon
            && $measure->unit === MeasureUnit::Porcentaje) {
            $valores = array_map(fn (?float $v): ?float => $v === null ? null : $v * 100, $valores);
        }

        return $valores;
    }

    /**
     * @param  list<string>  $dimensions
     * @return array<int|string, float|null>
     */
    private function formula(KpiMeasure $measure, CarbonImmutable $from, CarbonImmutable $to, Scope $scope, array $dimensions): array
    {
        $izquierda = $measure->left;
        $derecha = $measure->right;

        if ($izquierda === null || $derecha === null || $measure->formula_operator === null) {
            return [];
        }

        $a = $this->grouped($izquierda, $from, $to, $scope, $dimensions);
        $b = $this->grouped($derecha, $from, $to, $scope, $dimensions);
        $resultado = [];

        foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $llave) {
            $llave = (string) $llave;
            $resultado[$llave] = $measure->formula_operator->apply(
                $a[$llave] ?? ($izquierda->isSummable() ? 0.0 : null),
                $b[$llave] ?? ($derecha->isSummable() ? 0.0 : null),
            );
        }

        return $resultado;
    }

    /**
     * @param  list<string>  $dimensions
     * @return array<int|string, float|null>
     */
    private function aggregate(KpiMeasure $measure, CarbonImmutable $from, CarbonImmutable $to, Scope $scope, array $dimensions): array
    {
        $agregacion = $measure->aggregation;

        if ($agregacion === null || $measure->sales_event_type_id === null) {
            return [];
        }

        if ($agregacion->needsField() && blank($measure->field_key)) {
            return [];
        }

        $consulta = DB::table('sales_events as e')
            ->where('e.sales_event_type_id', $measure->sales_event_type_id)
            ->where('e.status', SalesEventStatus::Confirmado->value)
            ->whereNull('e.voided_at')
            ->whereBetween('e.occurred_on', [$from->toDateString(), $to->toDateString()]);

        $this->applyScope($consulta, $scope);
        $this->applyFilters($consulta, $measure->filters ?? []);

        $columnas = [];

        foreach ($dimensions as $i => $dimension) {
            $expresion = match ($dimension) {
                'user' => 'e.user_id',
                'day' => "to_char(e.occurred_on, 'YYYY-MM-DD')",
                'month' => "to_char(date_trunc('month', e.occurred_on), 'YYYY-MM-DD')",
                default => throw new InvalidArgumentException("No se puede agrupar por «{$dimension}»."),
            };
            // El alias deriva de la dimensión validada, no de una posición que
            // obligue a interpolar un identificador arbitrario en SQL.
            $alias = match ($dimension) {
                'user' => 'd_user',
                'day' => 'd_day',
                default => 'd_month',
            };
            $consulta->selectRaw($expresion.' as '.$alias)->groupByRaw($expresion);
            $columnas[] = $alias;
        }

        if ($agregacion === MeasureAggregation::Conteo) {
            $consulta->selectRaw('count(*) as v');
        } else {
            // Un campo vacío no es cero: se ignora, igual que en una hoja de cálculo.
            $consulta->selectRaw(
                $agregacion->sql()."(nullif(jsonb_extract_path_text(e.values, ?), '')::numeric) as v",
                [$measure->field_key],
            );
        }

        $resultado = [];

        foreach ($consulta->get() as $fila) {
            $llave = implode('|', array_map(fn (string $c): string => (string) $fila->{$c}, $columnas));
            $resultado[$llave] = $fila->v === null ? null : (float) $fila->v;
        }

        return $resultado;
    }

    private function applyScope(Builder $consulta, Scope $scope): void
    {
        match ($scope->type) {
            TargetScope::General => null,
            TargetScope::Persona => $consulta->where('e.user_id', $scope->id),
            // El equipo de la persona el día del evento, no el de hoy.
            TargetScope::Equipo => $consulta->whereExists(fn (Builder $q) => $q
                ->selectRaw('1')
                ->from('sales_assignments as a')
                ->whereColumn('a.user_id', 'e.user_id')
                ->where('a.sales_team_id', $scope->id)
                ->whereColumn('a.starts_on', '<=', 'e.occurred_on')
                ->where(fn (Builder $w) => $w->whereNull('a.ends_on')->orWhereColumn('a.ends_on', '>=', 'e.occurred_on'))),
        };
    }

    /**
     * Los filtros comparan el texto del campo; los de orden lo comparan como número.
     *
     * @param  list<array{field?: string, op?: string, value?: mixed}>  $filters
     */
    private function applyFilters(Builder $consulta, array $filters): void
    {
        foreach ($filters as $filtro) {
            $campo = (string) ($filtro['field'] ?? '');
            $operador = (string) ($filtro['op'] ?? '=');
            $valor = $filtro['value'] ?? null;

            if ($campo === '' || ! in_array($operador, self::FILTER_OPERATORS, true)) {
                continue;
            }

            $texto = 'jsonb_extract_path_text(e.values, ?)';

            match ($operador) {
                '=' => $consulta->whereRaw("{$texto} = ?", [$campo, self::text($valor)]),
                '!=' => $consulta->whereRaw("coalesce({$texto}, '') <> ?", [$campo, self::text($valor)]),
                'in' => $consulta->whereRaw(
                    "{$texto} in (".implode(',', array_fill(0, max(count((array) $valor), 1), '?')).')',
                    [$campo, ...(array_map(self::text(...), (array) $valor) ?: [''])],
                ),
                default => $consulta->whereRaw(
                    "nullif({$texto}, '')::numeric {$operador} ?",
                    [$campo, (float) $valor],
                ),
            };
        }
    }

    /** Como lo escribe `jsonb_extract_path_text`: los booleanos en minúsculas. */
    private static function text(mixed $valor): string
    {
        return is_bool($valor) ? ($valor ? 'true' : 'false') : (string) $valor;
    }
}
