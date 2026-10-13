<?php

declare(strict_types=1);

namespace App\Http\Requests\Routes\Concerns;

use App\Services\Leads\MexicanPhone;
use App\Services\Leads\TextNormalizer;

/**
 * Lo que el asesor tecleó, escrito como se escribe, antes de validar nada.
 *
 * Se hace en la entrada y no al imprimir: lo que se guarda es lo que va a la
 * portada, a la bandeja y a la búsqueda, y dos capturas del mismo dato deben
 * producir la misma fila.
 *
 * El teléfono llega reducido a sus diez dígitos para que la regla los cuente; la
 * lada la pone el modelo al guardarlo. El área abierta no pasa por aquí: la
 * resuelve `AreaNormalizer`, que además la reconoce contra la lista.
 */
trait NormalizesLeadCapture
{
    protected function prepareForValidation(): void
    {
        $texto = new TextNormalizer;
        $datos = [];

        foreach (['name', 'full_name'] as $campo) {
            if ($this->has($campo)) {
                $datos[$campo] = $texto->personName($this->input($campo));
            }
        }

        foreach (['occupation', 'context', 'plan_other'] as $campo) {
            if ($this->has($campo)) {
                $datos[$campo] = $texto->sentence($this->input($campo));
            }
        }

        if ($this->has('phone')) {
            $crudo = trim((string) $this->input('phone'));

            // Lo que no se deja reducir a diez dígitos se deja pasar como vino,
            // para que la regla lo rechace en vez de que desaparezca en silencio.
            $datos['phone'] = $crudo === '' ? null : (MexicanPhone::national($crudo) ?? $crudo);
        }

        $this->merge($datos);
    }

    /** @return list<string> */
    protected function phoneRules(): array
    {
        return ['string', 'regex:/^\d{'.MexicanPhone::DIGITOS.'}$/'];
    }
}
