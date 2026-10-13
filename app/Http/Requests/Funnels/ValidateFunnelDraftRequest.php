<?php

declare(strict_types=1);

namespace App\Http\Requests\Funnels;

use App\Models\Funnel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * El borrador que el editor manda para que el validador lo evalúe sin guardarlo.
 *
 * Es una lectura: no escribe nada y no exige que la ruta exista todavía. El
 * `funnel_route_id` viaja solo para que el panel sepa a qué acordeón pertenece el
 * veredicto que vuelve.
 *
 * La especialidad que abre la ruta no se acepta de aquí. Se deriva de los cursos,
 * igual que al guardar: si el cliente mandara la guardada mientras mueve cursos,
 * el panel evaluaría con un ancla que la lista en pantalla ya cambió, y un panel
 * que aprueba lo que la emisión rechaza es peor que no tener panel.
 */
class ValidateFunnelDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $funnel = $this->route('funnel');

        // Sin embudo es el armador de uno nuevo: basta con poder crearlo.
        return $funnel === null
            ? $this->user()?->can('create', Funnel::class) ?? false
            : $this->user()?->can('update', $funnel) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'funnel_route_id' => ['nullable', 'integer'],
            'items' => ['present', 'array', 'max:64'],
            'items.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'items.*.suggested_month' => ['nullable', 'integer', 'min:1', 'max:24'],
            'copy_text' => ['present', 'array', 'max:20'],
            'copy_text.*' => ['nullable', 'string', 'max:2000'],
            'waived_rules' => ['sometimes', 'array', 'max:32'],
            'waived_rules.*' => ['string'],
        ];
    }
}
