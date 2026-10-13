<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FunnelAngle;
use App\Enums\FunnelState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * El cúmulo de cursos de una rama con el que se engancha al lead.
 *
 * Se captura con dos cosas: un nombre y los cursos que lo componen. La estrategia
 * de venta es esa —juntar lo que pertenece a una rama para que el lead la vea como
 * su ruta principal— y todo lo demás que una campaña puede llevar (ángulo
 * comercial, vigencia, nombre aparte para el lead) sigue existiendo como columna
 * pero ya no se pide: se queda nulo hasta que alguien lo administre.
 *
 * `internal_name` es el nombre que se captura. `public_name` es lo único que
 * podría llegar al documento; mientras nadie lo capture, el documento usa el copy
 * del catálogo. La palabra «embudo» no aparece en nada que vea el lead.
 *
 * @property int $id
 * @property string $slug
 * @property string $internal_name
 * @property string|null $public_name
 * @property FunnelAngle|null $angle
 * @property string|null $headline
 * @property string|null $subheadline
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property FunnelState $state
 * @property int|null $list_price_cents
 * @property int|null $promo_price_cents
 * @property string|null $discount_label
 * @property int|null $price_anchor_course_id
 * @property string|null $utm_campaign
 * @property string|null $meta_campaign_ref
 * @property int $created_by
 * @property int|null $published_by
 * @property CarbonImmutable|null $published_at
 * @property string|null $internal_notes
 * @property list<array{code: string, by: int|null, at: string}> $waived_rules
 */
class Funnel extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /** @return HasMany<FunnelRoute, $this> */
    public function routes(): HasMany
    {
        return $this->hasMany(FunnelRoute::class)->orderBy('position');
    }

    /**
     * Las rutas ya emitidas que salieron de este embudo.
     *
     * @return HasMany<LearningRoute, $this>
     */
    public function issuedRoutes(): HasMany
    {
        return $this->hasMany(LearningRoute::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Bloqueado por §11.2: la relación existe para no migrar después, pero el
     * ancla de precio no se administra ni se imprime en la v1.
     *
     * @return BelongsTo<Course, $this>
     */
    public function priceAnchorCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'price_anchor_course_id');
    }

    /**
     * La ruta curada del embudo.
     *
     * El embudo se captura como una sola lista de cursos, así que en la práctica
     * tiene una. La tabla admite varias porque el modelo no le cierra la puerta a
     * una campaña con una ruta por perfil, pero la interfaz opera sobre esta.
     *
     * @return HasOne<FunnelRoute, $this>
     */
    public function primaryRoute(): HasOne
    {
        return $this->hasOne(FunnelRoute::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Los cursos del embudo, para contarlos sin recorrer sus rutas.
     *
     * @return HasManyThrough<FunnelRouteCourse, FunnelRoute, $this>
     */
    public function courseLinks(): HasManyThrough
    {
        return $this->hasManyThrough(FunnelRouteCourse::class, FunnelRoute::class);
    }

    /**
     * El identificador con el que el snapshot de cada documento emitido se refiere
     * a este embudo.
     *
     * Se deriva del nombre en vez de capturarse: quien arma el embudo escribe
     * «Calidad» y no tiene por qué inventar una convención. El sufijo numérico
     * resuelve los homónimos, que con la rotación de campañas son cuestión de
     * tiempo.
     */
    public static function uniqueSlugFor(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'embudo';
        $slug = $base;
        $n = 1;

        while (self::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.++$n;
        }

        return $slug;
    }

    /**
     * ¿Hoy cae dentro de la vigencia?
     *
     * Sin `starts_on` el embudo está disponible desde que existe; sin `ends_on` es
     * permanente. Las dos nulas es el caso normal ahora que la vigencia no se
     * captura.
     */
    public function isInEffect(?CarbonImmutable $on = null): bool
    {
        $day = ($on ?? now())->startOfDay();

        return ($this->starts_on === null || $this->starts_on->startOfDay()->lessThanOrEqualTo($day))
            && ($this->ends_on === null || $this->ends_on->startOfDay()->greaterThanOrEqualTo($day));
    }

    /**
     * Lo que el asesor puede elegir: publicado y vigente.
     *
     * Un embudo vencido sigue `activo` a propósito —no se archiva solo— para que
     * el listado interno muestre por qué desapareció del selector.
     */
    public function isSelectable(?CarbonImmutable $on = null): bool
    {
        return $this->state->isSelectable() && $this->isInEffect($on);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** @param Builder<Funnel> $query */
    public function scopeSelectable(Builder $query, ?CarbonImmutable $on = null): void
    {
        $day = ($on ?? now())->toDateString();

        $query->where('state', FunnelState::Activo)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $day))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day));
    }

    /**
     * Los códigos de regla que quien curó el embudo decidió ignorar.
     *
     * @return list<string>
     */
    public function waivedRuleCodes(): array
    {
        return array_column($this->waived_rules ?? [], 'code');
    }

    protected function casts(): array
    {
        return [
            'angle' => FunnelAngle::class,
            'state' => FunnelState::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'published_at' => 'immutable_datetime',
            'waived_rules' => 'array',
        ];
    }
}
