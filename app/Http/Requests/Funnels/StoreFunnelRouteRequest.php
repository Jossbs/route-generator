<?php

declare(strict_types=1);

namespace App\Http\Requests\Funnels;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Una ruta curada nace con nombre y nada más.
 *
 * La composición se arma después, en el editor, con el panel del validador
 * enfrente. Pedir los cursos aquí obligaría a elegirlos a ciegas.
 */
class StoreFunnelRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('funnel')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre de la ruta'];
    }
}
