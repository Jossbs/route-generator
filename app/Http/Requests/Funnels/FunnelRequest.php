<?php

declare(strict_types=1);

namespace App\Http\Requests\Funnels;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que se captura de un embudo: su nombre y sus cursos.
 *
 * La lista de campos es tan importante por lo que deja fuera como por lo que
 * admite. El identificador se deriva del nombre; la especialidad que abre el
 * embudo se deriva de los cursos. Ángulo comercial, vigencia, nombre público y
 * encabezados siguen siendo columnas —y nulas— porque nadie los administra
 * todavía: un campo dormido con formulario deja de estar dormido.
 *
 * El estado tampoco se captura. `kind`, `suggested_month` y
 * `outcome_phrase_override` viven en el pivote y también duermen: las 64 frases
 * de resultado siguen sin aprobarse y no se estrenan por esta vía.
 */
abstract class FunnelRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'items' => ['present', 'array', 'max:64'],
            'items.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            // Solo reglas dispensables: las ocho de §4 no pasan de aquí aunque
            // alguien arme la petición a mano.
            'waived_rules' => ['sometimes', 'array', 'max:32'],
            'waived_rules.*' => ['string', 'distinct', Rule::exists('catalog_rules', 'code')->where('waivable', true)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Es el nombre con el que el asesor va a encontrar esta ruta.',
            'items.max' => 'Un embudo no lleva más de 64 cursos: el catálogo entero son 64.',
            'waived_rules.*.exists' => 'Esa observación no se puede ignorar: es una de las restricciones críticas del catálogo.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'items' => 'cursos',
        ];
    }

    /**
     * Los cursos elegidos, en el orden en que se presentarán.
     *
     * @return list<int>
     */
    public function courseIds(): array
    {
        /** @var list<array{course_id: int}> $items */
        $items = $this->validated('items');

        return array_values(array_unique(array_map(
            fn (array $item): int => (int) $item['course_id'],
            $items,
        )));
    }

    /**
     * Los códigos de regla que quien curó el embudo decidió ignorar.
     *
     * @return list<string>
     */
    public function waivedRuleCodes(): array
    {
        return array_values($this->validated('waived_rules', []));
    }
}
