<?php

declare(strict_types=1);

namespace App\Http\Requests\Funnels;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La ruta curada completa: la ficha y su composición.
 *
 * `items` llega en el orden en que se presentará, y ese orden es el que se guarda:
 * la posición se deriva del arreglo, no se captura. `suggested_month` es sugerencia
 * y nunca fecha —los cursos son autogestivos con acceso 24/7— así que se admite el
 * número de mes y nada más.
 *
 * `choose_n`, `kind` y `outcome_phrase_override` están dormidos y no se aceptan.
 */
class UpdateFunnelRouteRequest extends FormRequest
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
            'archetype_id' => ['nullable', 'integer', 'exists:archetypes,id'],
            'main_specialization_id' => ['nullable', 'integer', 'exists:specializations,id'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:24'],
            'intro_text' => ['nullable', 'string', 'max:2000'],
            'closing_text' => ['nullable', 'string', 'max:2000'],
            'items' => ['present', 'array', 'max:64'],
            'items.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'items.*.suggested_month' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre de la ruta',
            'archetype_id' => 'perfil de lead',
            'main_specialization_id' => 'especialidad principal',
            'duration_months' => 'duración en meses',
            'intro_text' => 'texto de introducción',
            'closing_text' => 'texto de cierre',
            'items' => 'cursos',
        ];
    }
}
