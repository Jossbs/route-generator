<?php

declare(strict_types=1);

namespace App\Http\Controllers\Routes;

use App\Enums\UsageEventType;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Models\Specialization;
use App\Models\User;
use App\Services\Documents\RouteCompanions;
use App\Services\Leads\MexicanPhone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Las rutas que ya salieron, un renglón por lead.
 *
 * El módulo nació sin bandeja a propósito: el asesor viene a generar el documento
 * de alguien que tiene enfrente y la conversación vive en WhatsApp. Pero un
 * documento que se manda y no se puede volver a encontrar obliga a generarlo otra
 * vez, y entonces el mismo lead recibe dos rutas distintas del mismo asesor.
 *
 * Desde el 2026-09-23 el renglón es el lead y no el documento. La ruta inicial y
 * la final son dos momentos de la misma persona —el gancho y la cortesía al
 * cerrar—, y listarlas por separado duplicaba al lead justo cuando más importa:
 * el día que compró. Ahora las dos van lado a lado, y lo que falta se ve: una
 * final pendiente es un lead que todavía no cierra. Lo que agrupa es `lead_id`,
 * que es también lo que usa `parent_route_id` para ligar la final a su gancho.
 *
 * El estado del render no va, ni como columna ni como filtro: una ruta se emite y
 * se renderiza en el mismo minuto, así que decía «Lista» en todos los renglones.
 * Sigue en la base —la cola y el enlace público lo necesitan— y la ficha lo dice.
 *
 * Los filtros son los que responden a lo que el asesor viene a buscar: cuándo
 * salió (periodo), a quién le falta la final (etapa), qué armó cada quien
 * (asesor) y de qué tema (especialidad). Todos viven en la URL, así que un
 * renglón abierto y cerrado devuelve la lista como estaba.
 */
class RouteInboxController extends Controller
{
    public function __construct(private readonly RouteCompanions $companions) {}

    /** Quince leads caben en una pantalla de laptop sin que la página se vuelva un scroll. */
    private const POR_PAGINA = 15;

    /** @var array<string, string> */
    private const PERIODOS = [
        'hoy' => 'Hoy',
        'ayer' => 'Ayer',
        'semana' => 'Esta semana',
        'semana_pasada' => 'Semana pasada',
        'mes' => 'Este mes',
        'mes_pasado' => 'Mes pasado',
        'personalizado' => 'Personalizado',
    ];

    /** @var array<string, string> */
    private const ETAPAS = [
        'esperando_final' => 'Esperando ruta final',
        'con_membresia' => 'Con membresía',
    ];

    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);

        return Inertia::render('rutas/index', [
            'rutas' => $this->rows($this->query($filtros)->paginate(self::POR_PAGINA)->withQueryString()),
            'filtros' => $filtros,
            'periodos' => $this->opciones(self::PERIODOS),
            'etapas' => $this->opciones(self::ETAPAS),
            'asesores' => $this->asesores($request->user()),
            'especialidades' => Specialization::query()
                ->whereIn('id', LearningRoute::query()->select('anchor_specialization_id'))
                ->orderBy('display_order')
                ->get(['id', 'name'])
                ->map(fn (Specialization $especialidad): array => [
                    'value' => (string) $especialidad->id,
                    'label' => $especialidad->name,
                ])
                ->all(),
        ]);
    }

    /**
     * Lo que venga en la URL que no sea una opción conocida se descarta en
     * silencio: un enlace viejo o mal copiado abre la bandeja sin filtro, no un error.
     *
     * @return array{q: string, periodo: string, desde: string, hasta: string, etapa: string, asesor: string, especialidad: string}
     */
    private function filtros(Request $request): array
    {
        $periodo = $request->string('periodo')->toString();
        $etapa = $request->string('etapa')->toString();

        return [
            'q' => $request->string('q')->trim()->toString(),
            'periodo' => array_key_exists($periodo, self::PERIODOS) ? $periodo : '',
            'desde' => $this->fecha($request->string('desde')->toString()) ?? '',
            'hasta' => $this->fecha($request->string('hasta')->toString()) ?? '',
            'etapa' => array_key_exists($etapa, self::ETAPAS) ? $etapa : '',
            'asesor' => ctype_digit($request->string('asesor')->toString()) ? $request->string('asesor')->toString() : '',
            'especialidad' => ctype_digit($request->string('especialidad')->toString()) ? $request->string('especialidad')->toString() : '',
        ];
    }

    private function fecha(string $valor): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $valor)?->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * El intervalo que cubre el periodo, en la zona de la aplicación. Las semanas
     * empiezan en lunes, que es como se cuentan en la oficina.
     *
     * @param  array{periodo: string, desde: string, hasta: string}  $filtros
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}|null
     */
    private function intervalo(array $filtros): ?array
    {
        $hoy = CarbonImmutable::now();

        return match ($filtros['periodo']) {
            'hoy' => [$hoy->startOfDay(), $hoy->endOfDay()],
            'ayer' => [$hoy->subDay()->startOfDay(), $hoy->subDay()->endOfDay()],
            'semana' => [$hoy->startOfWeek(), $hoy->endOfWeek()],
            'semana_pasada' => [$hoy->subWeek()->startOfWeek(), $hoy->subWeek()->endOfWeek()],
            'mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'mes_pasado' => [$hoy->startOfMonth()->subMonth(), $hoy->startOfMonth()->subMonth()->endOfMonth()],
            // Un rango a medias también filtra: «desde el lunes» no necesita fin.
            'personalizado' => $filtros['desde'] === '' && $filtros['hasta'] === '' ? null : [
                $filtros['desde'] === '' ? null : CarbonImmutable::parse($filtros['desde'])->startOfDay(),
                $filtros['hasta'] === '' ? null : CarbonImmutable::parse($filtros['hasta'])->endOfDay(),
            ],
            default => null,
        };
    }

    /**
     * Los leads que tienen al menos una ruta viva, ordenados por su última
     * actividad: se generó o se corrigió alguno de sus documentos. El asesor
     * abre la bandeja buscando lo que acaba de mandar, y un lead que compró hoy
     * sube aunque su gancho sea del mes pasado.
     *
     * Periodo, asesor y especialidad se evalúan sobre la misma ruta: «lo que armé
     * esta semana» es una ruta que armé yo y que salió esta semana, no un lead al
     * que alguien le armó algo alguna vez y otro algo esta semana.
     *
     * @param  array{q: string, periodo: string, desde: string, hasta: string, etapa: string, asesor: string, especialidad: string}  $filtros
     * @return Builder<Lead>
     */
    private function query(array $filtros): Builder
    {
        $intervalo = $this->intervalo($filtros);
        $digitos = preg_replace('/\D+/', '', $filtros['q']) ?? '';

        return Lead::query()
            ->with([
                'learningRoutes' => fn ($q) => $q
                    ->with(['documentType', 'advisor', 'anchorSpecialization', 'membership', 'funnel'])
                    ->withCount([
                        'usageEvents as aperturas' => fn (Builder $q) => $q->where('event', UsageEventType::AbiertaPorLead),
                    ])
                    ->orderBy('id'),
            ])
            ->addSelect([
                'leads.*',
                'ultima_actividad' => LearningRoute::query()
                    ->selectRaw('max(greatest(created_at, coalesce(revised_at, created_at)))')
                    ->whereColumn('lead_id', 'leads.id'),
            ])
            ->whereHas('learningRoutes', fn (Builder $ruta) => $ruta
                ->when($intervalo !== null, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q) => $this->entre($q, 'created_at', $intervalo))
                    ->orWhere(fn (Builder $q) => $this->entre($q->whereNotNull('revised_at'), 'revised_at', $intervalo))))
                ->when($filtros['asesor'] !== '', fn (Builder $q) => $q->where('advisor_id', (int) $filtros['asesor']))
                ->when($filtros['especialidad'] !== '', fn (Builder $q) => $q->where('anchor_specialization_id', (int) $filtros['especialidad'])))
            ->when($filtros['q'] !== '', fn (Builder $q) => $q->where(fn (Builder $lead) => $lead
                ->where('name', 'ilike', '%'.$filtros['q'].'%')
                ->orWhere('phone', 'ilike', '%'.$filtros['q'].'%')
                // El teléfono se guarda en dígitos corridos: «442 118» lo
                // encuentra aunque el asesor lo escriba con espacios.
                ->when(strlen($digitos) >= 3, fn (Builder $q) => $q->orWhere('phone', 'like', '%'.$digitos.'%'))))
            // La etapa se lee del tipo de documento y no de su slug: un tercer
            // documento que pida membresía cuenta como cierre sin tocar esto.
            ->when($filtros['etapa'] === 'esperando_final', fn (Builder $q) => $q
                ->whereHas('learningRoutes', fn (Builder $r) => $r->whereHas('documentType', fn (Builder $t) => $t->where('requires_membership', false)))
                ->whereDoesntHave('learningRoutes', fn (Builder $r) => $r->whereHas('documentType', fn (Builder $t) => $t->where('requires_membership', true))))
            ->when($filtros['etapa'] === 'con_membresia', fn (Builder $q) => $q
                ->whereHas('learningRoutes', fn (Builder $r) => $r->whereHas('documentType', fn (Builder $t) => $t->where('requires_membership', true))))
            ->orderByDesc('ultima_actividad')
            ->orderByDesc('leads.id');
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $q
     * @param  array{0: CarbonImmutable|null, 1: CarbonImmutable|null}  $intervalo
     */
    private function entre(Builder $q, string $columna, array $intervalo): void
    {
        [$desde, $hasta] = $intervalo;

        $q->when($desde !== null, fn (Builder $q) => $q->where($columna, '>=', $desde))
            ->when($hasta !== null, fn (Builder $q) => $q->where($columna, '<=', $hasta));
    }

    /**
     * @param  LengthAwarePaginator<int, Lead>  $leads
     * @return array<string, mixed>
     */
    private function rows(LengthAwarePaginator $leads): array
    {
        return [
            'data' => array_map(fn (Lead $lead): array => $this->row($lead), $leads->items()),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total' => $leads->total(),
                'from' => $leads->firstItem(),
                'to' => $leads->lastItem(),
            ],
        ];
    }

    /**
     * Un lead con sus dos documentos. Si alguno se generó más de una vez —un
     * gancho repetido en lugar de corregido—, el renglón muestra el último y los
     * anteriores quedan a un clic en el menú, para no perderlos ni ensuciar la fila.
     *
     * @return array<string, mixed>
     */
    private function row(Lead $lead): array
    {
        /** @var Collection<int, LearningRoute> $rutas */
        $rutas = $lead->learningRoutes;

        $iniciales = $rutas->filter(fn (LearningRoute $r): bool => ! $r->documentType->requires_membership)->values();
        $finales = $rutas->filter(fn (LearningRoute $r): bool => (bool) $r->documentType->requires_membership)->values();

        $inicial = $iniciales->last();
        $final = $finales->last();
        $reciente = $rutas->sortBy(fn (LearningRoute $r) => $r->revised_at ?? $r->created_at)->last();

        return [
            'id' => (int) $lead->id,
            'lead' => $lead->name,
            'telefono' => MexicanPhone::display($lead->phone),
            'especialidad' => ($inicial ?? $final)?->anchorSpecialization?->name,
            'asesor' => $reciente?->advisor?->name,
            'ultima_actividad' => $lead->getAttribute('ultima_actividad') === null
                ? null
                : CarbonImmutable::parse($lead->getAttribute('ultima_actividad'))->toIso8601String(),
            'inicial' => $inicial ? $this->documento($inicial) : null,
            'final' => $final ? $this->documento($final) : null,
            'vigencia' => $final?->membership ? [
                'desde' => $final->membership->starts_on->toDateString(),
                'hasta' => $final->membership->ends_on->toDateString(),
            ] : null,
            'anteriores' => $iniciales->slice(0, -1)->concat($finales->slice(0, -1))
                ->sortByDesc('id')
                ->map(fn (LearningRoute $r): array => [
                    'id' => (int) $r->id,
                    'documento' => $r->documentType->advisorLabel(),
                    'creada_en' => $r->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            // El mensaje escrito se pega en WhatsApp, así que se copia desde aquí.
            // El guión del audio no viaja: se lee en la ficha, no se copia.
            'mensaje' => $inicial === null ? null : $this->companions->for($inicial, $lead)['mensaje']['texto'] ?? null,
            // Lo que el formulario de la final recibe por URL, para que el asesor
            // no vuelva a escribir lo que el lead ya dio en el gancho.
            'para_final' => $final === null ? array_filter([
                'tipo' => 'final',
                'lead' => (string) $lead->id,
                'nombre' => $lead->name,
                'telefono' => $lead->phone,
                'ocupacion' => $lead->occupation,
                'area' => $lead->area->value ?? $lead->area_other,
                'embudo' => $inicial?->funnel?->slug,
            ], fn ($valor): bool => $valor !== null && $valor !== '') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function documento(LearningRoute $ruta): array
    {
        return [
            'id' => (int) $ruta->id,
            'documento' => $ruta->documentType->advisorLabel(),
            'asesor' => $ruta->advisor?->name,
            'creada_en' => $ruta->created_at?->toIso8601String(),
            'revisiones' => (int) $ruta->revisions,
            'corregida_en' => $ruta->revised_at?->toIso8601String(),
            'aperturas' => (int) $ruta->getAttribute('aperturas'),
            'pdf' => $ruta->hasPdf(),
        ];
    }

    /**
     * Quienes han armado rutas, con quien consulta primero: es a quien más se busca.
     *
     * @return list<array{value: string, label: string}>
     */
    private function asesores(?User $yo): array
    {
        return array_values(User::query()
            ->whereIn('id', LearningRoute::query()->select('advisor_id')->whereNotNull('advisor_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->sortBy(fn (User $u): int => $u->id === $yo?->id ? 0 : 1)
            ->map(fn (User $u): array => [
                'value' => (string) $u->id,
                'label' => $u->id === $yo?->id ? "Mías ({$u->name})" : $u->name,
            ])
            ->values()
            ->all());
    }

    /**
     * @param  array<string, string>  $opciones
     * @return list<array{value: string, label: string}>
     */
    private function opciones(array $opciones): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys($opciones),
            array_values($opciones),
        );
    }
}
