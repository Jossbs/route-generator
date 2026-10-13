<?php

declare(strict_types=1);

namespace App\Services\Kpis\Settings;

use App\Enums\Kpis\SalesFieldType;
use App\Models\KpiMeasure;
use App\Models\SalesEventField;
use App\Models\SalesEventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crea y corrige tipos de evento con sus campos.
 *
 * La llave y el tipo de dato de un campo no cambian después de crearlo: los
 * eventos ya capturados y las medidas que lo leen dependen de los dos. Se puede
 * cambiar su etiqueta, sus opciones y si es obligatorio. Un campo que alguna
 * medida lee no se puede quitar.
 */
final class EventTypeWriter
{
    /** @param  array<string, mixed>  $input */
    public function save(?SalesEventType $type, array $input): SalesEventType
    {
        $datos = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'fields' => ['array'],
            'fields.*.id' => ['nullable', 'integer', 'distinct'],
            'fields.*.key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.data_type' => ['required', Rule::enum(SalesFieldType::class)],
            'fields.*.options' => ['array'],
            'fields.*.options.*.value' => ['required', 'string', 'max:120'],
            'fields.*.options.*.label' => ['required', 'string', 'max:120'],
            'fields.*.is_required' => ['boolean'],
        ], [
            'fields.*.key.regex' => 'La clave va en minúsculas, sin espacios ni acentos (ej. importe_neto).',
        ], [
            'name' => 'nombre',
            'fields.*.label' => 'etiqueta del campo',
            'fields.*.data_type' => 'tipo de dato',
        ])->validate();

        return DB::transaction(function () use ($type, $datos): SalesEventType {
            $type ??= new SalesEventType(['slug' => $this->uniqueSlug($datos['name'])]);
            $type->fill([
                'name' => $datos['name'],
                'description' => $datos['description'] ?? null,
                'is_active' => $datos['is_active'] ?? true,
            ])->save();

            $existentes = $type->fields()->get()->keyBy('id');
            $conservados = [];
            $claves = [];

            foreach (array_values($datos['fields'] ?? []) as $i => $f) {
                $campo = isset($f['id']) ? $existentes->get($f['id']) : null;

                if (isset($f['id']) && $campo === null) {
                    throw ValidationException::withMessages(["fields.{$i}.id" => 'El campo no pertenece a este tipo de evento.']);
                }

                $clave = $campo->key ?? ($f['key'] ?? null) ?: Str::slug($f['label'], '_');

                if (preg_match('/^[a-z][a-z0-9_]*$/', $clave) !== 1) {
                    throw ValidationException::withMessages(["fields.{$i}.key" => 'Escribe una clave en minúsculas que empiece con una letra (ej. importe_neto).']);
                }

                if (isset($claves[$clave])) {
                    throw ValidationException::withMessages(["fields.{$i}.key" => "Hay dos campos con la clave «{$clave}»."]);
                }

                $claves[$clave] = true;
                $tipo = $campo->data_type ?? SalesFieldType::from($f['data_type']);

                $campo ??= new SalesEventField(['sales_event_type_id' => $type->id, 'key' => $clave]);
                $campo->fill([
                    'label' => $f['label'],
                    'data_type' => $tipo,
                    'options' => $tipo === SalesFieldType::Opcion ? array_values($f['options'] ?? []) : [],
                    'is_required' => $f['is_required'] ?? false,
                    'position' => $i,
                ])->save();

                $conservados[] = $campo->id;
            }

            foreach ($existentes->except($conservados) as $quitado) {
                if ($this->isReferenced($type, $quitado->key)) {
                    throw ValidationException::withMessages([
                        'fields' => "El campo «{$quitado->label}» lo usa una medida: quítalo de la medida antes de borrarlo.",
                    ]);
                }

                $quitado->delete();
            }

            return $type->load('fields');
        });
    }

    private function isReferenced(SalesEventType $type, string $key): bool
    {
        return KpiMeasure::query()
            ->where('sales_event_type_id', $type->id)
            ->get(['field_key', 'filters'])
            ->contains(fn (KpiMeasure $m): bool => $m->field_key === $key
                || collect($m->filters)->contains(fn (array $f): bool => $f['field'] === $key));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tipo';
        $slug = $base;

        for ($i = 2; SalesEventType::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
