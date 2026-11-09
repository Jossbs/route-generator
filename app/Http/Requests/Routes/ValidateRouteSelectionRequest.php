<?php

declare(strict_types=1);

namespace App\Http\Requests\Routes;

class ValidateRouteSelectionRequest extends ProposeRouteRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Una lista vacía es explícita: nunca sustituirla por sugerencias nuevas.
            'course_ids' => ['present', 'array', 'max:64'],
        ];
    }
}
