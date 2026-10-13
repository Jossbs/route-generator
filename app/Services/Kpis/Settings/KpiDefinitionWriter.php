<?php

declare(strict_types=1);

namespace App\Services\Kpis\Settings;

use App\Enums\Kpis\FormulaOperator;
use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\MeasureKind;
use App\Enums\Kpis\MeasureUnit;
use App\Models\Kpi;
use App\Models\KpiMeasure;
use App\Models\SalesEventType;
use App\Services\Kpis\MeasureEvaluator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crea y corrige un KPI con sus medidas.
 *
 * Aquí se garantiza que toda medida que llegue al evaluador se pueda calcular:
 * que el campo que suma exista y sea numérico, que los filtros apunten a campos
 * del tipo, que una fórmula no se apunte a sí misma ni forme un ciclo.
 *
 * Una fórmula combina medidas ya guardadas —de este KPI o de otro—: para armar
 * una conversión se guardan primero ventas y leads, y después la razón.
 *
 * Una medida con metas o que otra fórmula usa no se borra; se desactiva el KPI.
 */
final class KpiDefinitionWriter
{
    /** @param  array<string, mixed>  $input */
    public function save(?Kpi $kpi, array $input): Kpi
    {
        $datos = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
            'measures' => ['required', 'array', 'min:1'],
            'measures.*.id' => ['nullable', 'integer', 'distinct'],
            'measures.*.name' => ['required', 'string', 'max:120'],
            'measures.*.unit' => ['required', Rule::enum(MeasureUnit::class)],
            'measures.*.kind' => ['required', Rule::enum(MeasureKind::class)],
            'measures.*.sales_event_type_id' => ['nullable', 'integer'],
            'measures.*.aggregation' => ['nullable', Rule::enum(MeasureAggregation::class)],
            'measures.*.field_key' => ['nullable', 'string'],
            'measures.*.filters' => ['array'],
            'measures.*.filters.*.field' => ['required', 'string'],
            'measures.*.filters.*.op' => ['required', Rule::in(MeasureEvaluator::FILTER_OPERATORS)],
            'measures.*.filters.*.value' => ['present'],
            'measures.*.formula_operator' => ['nullable', Rule::enum(FormulaOperator::class)],
            'measures.*.left_measure_id' => ['nullable', 'integer'],
            'measures.*.right_measure_id' => ['nullable', 'integer'],
            'measures.*.has_target' => ['boolean'],
            'measures.*.higher_is_better' => ['boolean'],
        ], [
            'measures.required' => 'Un KPI necesita al menos una medida.',
        ], [
            'name' => 'nombre',
            'measures.*.name' => 'nombre de la medida',
            'measures.*.unit' => 'unidad',
        ])->validate();

        return DB::transaction(function () use ($kpi, $datos): Kpi {
            $kpi ??= new Kpi(['slug' => $this->uniqueSlug(Kpi::class, $datos['name'])]);
            $kpi->fill([
                'name' => $datos['name'],
                'description' => $datos['description'] ?? null,
                'display_order' => $datos['display_order'] ?? 0,
                'is_active' => $datos['is_active'] ?? true,
            ])->save();

            $existentes = $kpi->measures()->get()->keyBy('id');
            $conservadas = [];
            $slugs = [];

            foreach (array_values($datos['measures']) as $i => $m) {
                $medida = isset($m['id']) ? $existentes->get($m['id']) : null;

                if (isset($m['id']) && $medida === null) {
                    throw ValidationException::withMessages(["measures.{$i}.id" => 'La medida no pertenece a este KPI.']);
                }

                $medida ??= new KpiMeasure(['kpi_id' => $kpi->id]);
                $slug = $medida->slug ?? Str::slug($m['name']) ?: 'medida';

                while (isset($slugs[$slug]) || ($medida->exists === false && $existentes->contains('slug', $slug))) {
                    $slug .= '-'.($i + 1);
                }

                $slugs[$slug] = true;
                $atributos = $this->attributes($m, $i, $medida);
                $medida->fill(['slug' => $slug, 'name' => $m['name'], 'position' => $i, ...$atributos])->save();

                $conservadas[] = $medida->id;
            }

            foreach ($existentes->except($conservadas) as $quitada) {
                $this->assertRemovable($quitada);
                $quitada->delete();
            }

            foreach ($kpi->measures()->get() as $medida) {
                $this->assertAcyclic($medida);
            }

            return $kpi->load('measures');
        });
    }

    /**
     * @param  array<string, mixed>  $m
     * @return array<string, mixed>
     */
    private function attributes(array $m, int $i, KpiMeasure $medida): array
    {
        $base = [
            'unit' => MeasureUnit::from($m['unit']),
            'kind' => MeasureKind::from($m['kind']),
            'has_target' => $m['has_target'] ?? true,
            'higher_is_better' => $m['higher_is_better'] ?? true,
        ];

        if ($base['kind'] === MeasureKind::Formula) {
            $operador = FormulaOperator::tryFrom((string) ($m['formula_operator'] ?? ''));
            $izquierda = KpiMeasure::query()->whereKey($m['left_measure_id'] ?? 0)->first();
            $derecha = KpiMeasure::query()->whereKey($m['right_measure_id'] ?? 0)->first();

            if ($operador === null || $izquierda === null || $derecha === null) {
                throw ValidationException::withMessages(["measures.{$i}.formula_operator" => 'Una fórmula necesita su operación y las dos medidas que combina.']);
            }

            if ($medida->exists && ($izquierda->id === $medida->id || $derecha->id === $medida->id)) {
                throw ValidationException::withMessages(["measures.{$i}.left_measure_id" => 'Una fórmula no puede usarse a sí misma.']);
            }

            return [...$base, 'formula_operator' => $operador, 'left_measure_id' => $izquierda->id, 'right_measure_id' => $derecha->id,
                'sales_event_type_id' => null, 'aggregation' => null, 'field_key' => null, 'filters' => []];
        }

        $tipo = SalesEventType::query()->with('fields')->whereKey($m['sales_event_type_id'] ?? 0)->first();
        $agregacion = MeasureAggregation::tryFrom((string) ($m['aggregation'] ?? ''));

        if ($tipo === null || $agregacion === null) {
            throw ValidationException::withMessages(["measures.{$i}.sales_event_type_id" => 'Elige qué eventos se miden y cómo se resumen.']);
        }

        $campos = $tipo->fields->keyBy('key');
        $campo = null;

        if ($agregacion->needsField()) {
            $campo = $campos->get((string) ($m['field_key'] ?? ''));

            if ($campo === null || ! $campo->data_type->isNumeric()) {
                throw ValidationException::withMessages(["measures.{$i}.field_key" => 'Para '.mb_strtolower($agregacion->label()).' elige un campo de número o dinero de «'.$tipo->name.'».']);
            }
        }

        $filtros = [];

        foreach ($m['filters'] ?? [] as $f) {
            $campoFiltro = $campos->get($f['field']);

            if ($campoFiltro === null) {
                throw ValidationException::withMessages(["measures.{$i}.filters" => "El filtro usa un campo que «{$tipo->name}» no tiene."]);
            }

            if (in_array($f['op'], ['>', '>=', '<', '<='], true)
                && (! $campoFiltro->data_type->isNumeric() || ! is_numeric($f['value']))) {
                throw ValidationException::withMessages(["measures.{$i}.filters" => 'Los filtros de mayor o menor requieren un campo numérico y un valor numérico.']);
            }

            $valores = $f['op'] === 'in' ? (array) $f['value'] : [$f['value']];

            foreach ($valores as $valor) {
                if ($valor !== null && ! is_scalar($valor)) {
                    throw ValidationException::withMessages(["measures.{$i}.filters" => 'Cada valor del filtro debe ser texto, número o sí/no.']);
                }
            }

            $filtros[] = ['field' => $f['field'], 'op' => $f['op'], 'value' => $f['value']];
        }

        return [...$base, 'sales_event_type_id' => $tipo->id, 'aggregation' => $agregacion, 'field_key' => $campo?->key,
            'filters' => $filtros, 'formula_operator' => null, 'left_measure_id' => null, 'right_measure_id' => null];
    }

    private function assertRemovable(KpiMeasure $medida): void
    {
        $enFormula = KpiMeasure::query()
            ->where('left_measure_id', $medida->id)
            ->orWhere('right_measure_id', $medida->id)
            ->exists();

        if ($enFormula || $medida->targets()->exists()) {
            throw ValidationException::withMessages([
                'measures' => "La medida «{$medida->name}» ya tiene metas o la usa una fórmula: no se puede quitar. Desactiva el KPI si ya no se usa.",
            ]);
        }
    }

    private function assertAcyclic(KpiMeasure $medida): void
    {
        $visitadas = [];
        $pendientes = [$medida->left_measure_id, $medida->right_measure_id];

        while ($pendientes !== []) {
            $id = array_pop($pendientes);

            if ($id === null || isset($visitadas[$id])) {
                continue;
            }

            if ($id === $medida->id) {
                throw ValidationException::withMessages(['measures' => "La fórmula «{$medida->name}» termina usándose a sí misma."]);
            }

            $visitadas[$id] = true;
            $otra = KpiMeasure::query()->find($id, ['id', 'left_measure_id', 'right_measure_id']);

            if ($otra !== null) {
                $pendientes[] = $otra->left_measure_id;
                $pendientes[] = $otra->right_measure_id;
            }
        }
    }

    /** @param  class-string<Model>  $model */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'kpi';
        $slug = $base;

        for ($i = 2; $model::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
