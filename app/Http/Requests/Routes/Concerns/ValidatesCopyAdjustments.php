<?php

declare(strict_types=1);

namespace App\Http\Requests\Routes\Concerns;

use App\Models\BannedPhrase;
use App\Services\Documents\EditableCopy;
use Closure;

/**
 * Los párrafos que el asesor ajusta a mano en la revisión (decidido el
 * 2026-09-24).
 *
 * Se revisan aquí contra `banned_phrases` para que el asesor vea el problema en
 * la misma pantalla, con la frase que lo sustituye, en lugar de encontrarse la
 * ruta rechazada después de generar. El validador los vuelve a escanear de todas
 * formas: esta revisión es por comodidad, no la barrera.
 */
trait ValidatesCopyAdjustments
{
    /** @return array<string, mixed> */
    protected function copyAdjustmentRules(string $documentType): array
    {
        $permitidos = app(EditableCopy::class)->slugs($documentType);

        return [
            'ajustes' => ['sometimes', 'array', function (string $attribute, mixed $value, Closure $fail) use ($permitidos): void {
                $sobrantes = array_diff(array_keys((array) $value), $permitidos);

                if ($sobrantes !== []) {
                    $fail('Ese párrafo no se puede ajustar a mano.');
                }
            }],
            'ajustes.*' => ['nullable', 'string', 'max:'.EditableCopy::MAX, $this->withoutBannedPhrases(...)],
        ];
    }

    /** @return array<string, string> */
    protected function copyAdjustmentMessages(): array
    {
        return [
            'ajustes.*.max' => 'Déjalo en '.EditableCopy::MAX.' caracteres como máximo: es un párrafo, no una carta.',
        ];
    }

    private function withoutBannedPhrases(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        foreach (BannedPhrase::query()->active()->get() as $frase) {
            if (@preg_match($frase->pattern, $value) === 1) {
                $fail(trim($frase->reason.' '.($frase->suggested_replacement ?? '')));

                return;
            }
        }
    }
}
