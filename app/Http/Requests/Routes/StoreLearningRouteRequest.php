<?php

declare(strict_types=1);

namespace App\Http\Requests\Routes;

use App\Http\Requests\Routes\Concerns\NormalizesLeadCapture;
use App\Http\Requests\Routes\Concerns\ValidatesCopyAdjustments;
use App\Models\Funnel;
use App\Services\Leads\AreaNormalizer;
use App\Services\RouteComposition\Support\RecommendedTopUp;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La captura de la ruta inicial.
 *
 * Seis campos, y tres de ellos no son datos de contacto sino la materia prima de
 * la composición: el área dice de qué lado de la planta viene, la ocupación qué
 * hace ahí, y lo que dijo desempata entre cursos parecidos.
 *
 * El teléfono no se exige. El asesor arma el gancho mientras conversa y no
 * siempre lo tiene todavía; pedirlo como requisito solo conseguiría que
 * escribiera cualquier cosa, y un teléfono inventado es peor que ninguno porque
 * reencuentra al lead equivocado. La ruta final sí lo exige, ahí sí es la llave.
 *
 * `area` acepta cualquier texto, no solo los valores del catálogo: la captura puede
 * llegar por URL desde el CRM, donde nadie garantiza el vocabulario.
 * `AreaNormalizer` resuelve después qué era.
 *
 * La ruta por la que preguntó es un embudo, no una especialidad suelta: sus
 * cursos son los que el lead va a ver como su ruta principal.
 */
class StoreLearningRouteRequest extends FormRequest
{
    use NormalizesLeadCapture;
    use ValidatesCopyAdjustments;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', ...$this->phoneRules()],
            'occupation' => ['required', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:80'],
            'area_other' => [
                Rule::requiredIf(fn (): bool => $this->input('area') === AreaNormalizer::OTRO),
                'nullable',
                'string',
                'max:80',
            ],
            'funnel_id' => ['required', 'integer', 'exists:funnels,id'],
            'context' => ['nullable', 'string', 'max:500'],
            'external_id' => ['nullable', 'string', 'max:64'],
            // Lo que el asesor dejó en la propuesta. Ausente en la captura, que es
            // la misma petición un paso antes; presente al emitir.
            'course_ids' => ['sometimes', 'array', 'max:64'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            // Los párrafos que ajustó a mano en la revisión.
            ...$this->copyAdjustmentRules(DocumentTypeSeeder::ENFOCADA),
        ];
    }

    /**
     * El techo de recomendados también se cuida aquí, no solo en la pantalla.
     *
     * Son tres porque el guión del audio le presenta al lead «tres cursos más»: un
     * cuarto en el PDF sería un curso que el asesor no mencionó. Lo del embudo
     * no cuenta, va completo siempre.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ids = $this->input('course_ids');

                if (! is_array($ids) || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $curados = Funnel::query()->find($this->integer('funnel_id'))
                    ?->primaryRoute()->with('items')->first()
                    ?->orderedCourseIds() ?? [];

                $extra = array_diff(array_map(intval(...), $ids), $curados);

                if (count(array_unique($extra)) > RecommendedTopUp::OBJETIVO) {
                    $validator->errors()->add(
                        'course_ids',
                        'Son tres cursos recomendados como máximo: los mismos que le mencionas en el audio.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'phone' => 'WhatsApp',
            'occupation' => 'ocupación',
            'area' => 'área',
            'area_other' => 'área',
            'funnel_id' => 'ruta por la que preguntó',
            'context' => 'lo que te dijo',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Va impreso en la portada.',
            'phone.regex' => 'Son los 10 dígitos del número, sin la lada.',
            'occupation.required' => 'De aquí salen los cursos que se van a proponer.',
            'area_other.required' => 'Escribe el área, para que no quede sin clasificar.',
            'funnel_id.required' => 'Es la ruta que abre su documento.',
            ...$this->copyAdjustmentMessages(),
        ];
    }
}
