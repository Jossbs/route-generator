<?php

declare(strict_types=1);

namespace App\Http\Requests\Routes;

use App\Enums\MembershipPlan;
use App\Http\Requests\Routes\Concerns\NormalizesLeadCapture;
use App\Http\Requests\Routes\Concerns\ValidatesCopyAdjustments;
use App\Services\Leads\AreaNormalizer;
use App\Services\RouteComposition\Support\StudyPace;
use Closure;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * La captura de la ruta final: los mismos campos de la inicial más la vigencia.
 *
 * El nombre va completo porque se imprime en la portada, y las dos fechas no son
 * un trámite: ordenan los doce meses y van impresas. Sin ellas no se genera, y
 * `ANNUAL_REQUIRES_MEMBERSHIP` lo comprueba después sobre el borrador.
 *
 * El plan va tipificado porque va impreso: era texto libre y el mismo plan llegaba
 * escrito de tres formas a la portada de tres leads. El importe no se imprime
 * —docs/ARQUITECTURA.md §4.5— pero sí se exige: una membresía asentada sin decir por cuánto se
 * cerró deja ese dato viviendo solo en la conversación de WhatsApp del asesor.
 *
 * El teléfono se exige igual que en la ruta inicial, y no porque haya que
 * llamarle: es la llave con la que se reconoce al lead que ya recibió su gancho.
 * Sin él, el mismo miembro entra dos veces a la base y la conversión gancho →
 * membresía deja de poder seguirse. Lo mismo hace `external_id` cuando el lead
 * vino de Kommo.
 */
class StoreAnnualRouteRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', ...$this->phoneRules()],
            'occupation' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:80'],
            'area_other' => [
                Rule::requiredIf(fn (): bool => $this->input('area') === AreaNormalizer::OTRO),
                'nullable',
                'string',
                'max:80',
            ],
            'funnel_id' => ['required', 'integer', 'exists:funnels,id'],
            'context' => ['nullable', 'string', 'max:500'],
            'external_id' => ['nullable', 'string', 'max:64'],
            // El lead del gancho, cuando la final se arma desde él.
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'plan' => ['required', Rule::enum(MembershipPlan::class)],
            'plan_other' => [
                Rule::requiredIf(fn (): bool => $this->input('plan') === MembershipPlan::Otro->value),
                'nullable',
                'string',
                'max:120',
            ],
            // Lo que se cobró. No se imprime en el documento —eso lo prohíbe
            // docs/ARQUITECTURA.md §4.5— pero sin él la membresía queda asentada sin decir por
            // cuánto se cerró, y ese dato solo existía en el chat del asesor.
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'starts_on' => ['required', 'date'],
            // Un año, no «cualquier fecha posterior»: el documento se titula
            // «Doce meses» y el cronograma tiene doce columnas. Una vigencia de
            // seis o trece meses se imprimía igual como doce (decidido el
            // 2026-09-24). Se acepta el año exacto con o sin el último día.
            'ends_on' => ['required', 'date', 'after:starts_on', $this->oneYearTerm(...)],
            // Lo que el lead dijo que puede estudiar. Decide cuántos cursos caben
            // en su año y dónde termina cada mes, y va impreso.
            'weekly_hours' => [
                'required',
                'integer',
                'min:'.StudyPace::MIN_WEEKLY_HOURS,
                'max:'.StudyPace::MAX_WEEKLY_HOURS,
            ],
            'course_ids' => ['sometimes', 'array', 'max:64'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            // Los párrafos que ajustó a mano en la revisión.
            ...$this->copyAdjustmentRules(DocumentTypeSeeder::ANUAL),
        ];
    }

    /**
     * La vigencia de un plan anual dura un año: termina el día anterior al
     * aniversario del inicio —lo que escribe el atajo «Un año exacto»— o el
     * aniversario mismo.
     */
    private function oneYearTerm(string $attribute, mixed $value, Closure $fail): void
    {
        $inicio = $this->input('starts_on');

        if (! is_string($inicio) || ! is_string($value) || strtotime($inicio) === false || strtotime($value) === false) {
            return;
        }

        $aniversario = Carbon::parse($inicio)->addYearNoOverflow()->startOfDay();
        $fin = Carbon::parse($value)->startOfDay();

        if (! $fin->equalTo($aniversario) && ! $fin->equalTo($aniversario->copy()->subDay())) {
            $fail('La membresía anual dura doce meses: usa «Un año exacto» o termina la vigencia el '
                .$aniversario->copy()->subDay()->format('d/m/Y').'.');
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'full_name' => 'nombre completo',
            'phone' => 'WhatsApp',
            'occupation' => 'ocupación',
            'area' => 'área',
            'area_other' => 'área',
            'funnel_id' => 'ruta por la que preguntó',
            'plan' => 'plan',
            'plan_other' => 'plan',
            'price' => 'importe',
            'starts_on' => 'inicio de vigencia',
            'ends_on' => 'fin de vigencia',
            'weekly_hours' => 'horas por semana',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Va impreso en la portada, completo.',
            'phone.required' => 'Es con lo que se reconoce al lead al que ya le mandaste su ruta inicial.',
            'phone.regex' => 'Son los 10 dígitos del número, sin la lada.',
            'occupation.required' => 'Va impresa en la portada y ordena lo que se le recomienda.',
            'funnel_id.required' => 'Es la ruta que abre el mes 1.',
            'area_other.required' => 'Escribe el área, para que no quede sin clasificar.',
            'plan.required' => 'Va impreso en la portada, así que no puede quedar en blanco.',
            'plan_other.required' => 'Escribe cómo se llama el plan que se cerró.',
            'price.required' => 'Es lo que se cobró. No se imprime en el documento; queda en el expediente del miembro.',
            'ends_on.after' => 'La vigencia no puede terminar antes de empezar.',
            'starts_on.required' => 'Van impresas y ordenan los doce meses.',
            'ends_on.required' => 'Van impresas y ordenan los doce meses.',
            'weekly_hours.required' => 'Pregúntale cuántas horas a la semana puede estudiar: de ahí sale cuántos cursos caben en su año.',
            'weekly_hours.max' => 'Más de 40 horas a la semana es un segundo empleo. Revisa el número con el lead.',
            ...$this->copyAdjustmentMessages(),
        ];
    }
}
