<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\SalesFieldType;
use App\Models\SalesEventField;
use App\Models\SalesEventType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Valida y normaliza los valores capturados de un evento según los campos de su tipo.
 *
 * Los números se guardan como número y no como texto, para que el JSONB se pueda
 * sumar sin sorpresas; lo que no es un campo del tipo se descarta.
 */
final class EventValues
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function normalize(SalesEventType $type, array $input): array
    {
        $campos = $type->fields;
        $reglas = [];
        $nombres = [];

        foreach ($campos as $campo) {
            $regla = [$campo->is_required ? 'required' : 'nullable', $campo->data_type->rule()];

            if ($campo->data_type === SalesFieldType::Opcion && $campo->options !== []) {
                $regla[] = 'in:'.implode(',', array_map(fn (array $o): string => (string) $o['value'], $campo->options));
            }

            $reglas['values.'.$campo->key] = $regla;
            $nombres['values.'.$campo->key] = mb_strtolower($campo->label);
        }

        Validator::make(['values' => $input], $reglas, [], $nombres)->validate();

        return $campos
            ->mapWithKeys(fn (SalesEventField $campo): array => [$campo->key => $this->cast($campo, $input[$campo->key] ?? null)])
            ->reject(fn (mixed $valor): bool => $valor === null)
            ->all();
    }

    private function cast(SalesEventField $campo, mixed $valor): mixed
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return match ($campo->data_type) {
            SalesFieldType::Numero, SalesFieldType::Dinero => $valor + 0,
            SalesFieldType::SiNo => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
            default => (string) $valor,
        };
    }
}
