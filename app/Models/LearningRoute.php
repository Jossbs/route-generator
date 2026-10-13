<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RouteLayer;
use App\Enums\RouteStatus;
use App\Services\RouteGeneration\PayloadHash;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $membership_id
 * @property int|null $parent_route_id
 * @property int $document_type_id
 * @property int|null $advisor_id
 * @property int|null $anchor_specialization_id
 * @property int|null $archetype_id
 * @property int|null $funnel_id
 * @property int|null $funnel_route_id
 * @property RouteStatus $status
 * @property int $catalog_version_id
 * @property array<string, mixed>|null $snapshot
 * @property string|null $snapshot_hash
 * @property int $course_count
 * @property int $content_unit_count
 * @property int $revisions
 * @property CarbonImmutable|null $revised_at
 * @property string|null $access_token
 * @property string|null $pdf_path
 * @property CarbonImmutable|null $rendered_at
 * @property CarbonImmutable|null $deleted_at
 */
class LearningRoute extends Model
{
    /**
     * Borrado suave: una ruta borrada sale de la bandeja, deja de abrir por su
     * enlace y pierde su archivo, pero conserva sus validaciones y su bitácora.
     * Son la prueba de que las restricciones se evaluaron, y esa prueba no es del
     * asesor que borra el renglón.
     */
    use SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * El gancho que originó este documento anual.
     *
     * @return BelongsTo<LearningRoute, $this>
     */
    public function parentRoute(): BelongsTo
    {
        return $this->belongsTo(LearningRoute::class, 'parent_route_id');
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /** @return BelongsTo<CatalogVersion, $this> */
    public function catalogVersion(): BelongsTo
    {
        return $this->belongsTo(CatalogVersion::class);
    }

    /** @return BelongsTo<Specialization, $this> */
    public function anchorSpecialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class, 'anchor_specialization_id');
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /**
     * El embudo que precargó esta ruta. Nulo significa ruta libre: el asesor la
     * armó desde el formulario y el motor la compuso desde el catálogo.
     *
     * @return BelongsTo<Funnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /** @return BelongsTo<FunnelRoute, $this> */
    public function funnelRoute(): BelongsTo
    {
        return $this->belongsTo(FunnelRoute::class);
    }

    /** ¿Salió de un preset o de una composición libre? */
    public function cameFromFunnel(): bool
    {
        return $this->funnel_id !== null;
    }

    /**
     * El sello del snapshot. Si deja de cuadrar, el payload se tocó después de
     * emitirse y el documento guardado ya no es el que vio el lead.
     */
    public function snapshotIsIntact(): bool
    {
        return $this->snapshot_hash !== null
            && $this->snapshot !== null
            && hash_equals($this->snapshot_hash, PayloadHash::of($this->snapshot));
    }

    /** @return HasMany<RouteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RouteItem::class, 'route_id');
    }

    /**
     * Los cursos del itinerario, en el orden en que el documento los lee.
     *
     * Es lo que la pantalla de revisión vuelve a mostrar cuando la ruta se
     * corrige: los cursos que este documento ya prometía. El inventario de la
     * membresía queda fuera porque no es una elección del asesor —el miembro tiene
     * el catálogo completo lo lea o no— y ofrecérselo para podar daría a entender
     * que quitarle un renglón se lo quita.
     *
     * @return list<int>
     */
    public function itineraryCourseIds(): array
    {
        return array_values(array_unique(
            $this->items()
                ->where('layer', RouteLayer::Itinerario)
                ->orderBy('position')
                ->pluck('course_id')
                ->map(intval(...))
                ->all(),
        ));
    }

    /** @return HasMany<RouteValidation, $this> */
    public function validations(): HasMany
    {
        return $this->hasMany(RouteValidation::class, 'route_id');
    }

    /** @return HasMany<UsageEvent, $this> */
    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class, 'route_id');
    }

    /**
     * Cómo se llama el archivo cuando sale del sistema.
     *
     * Viaja por WhatsApp entre docenas de archivos, así que lleva el nombre del
     * lead y el del documento. Un «documento.pdf» no lo abre nadie, y el asesor no
     * lo encuentra después en su propio teléfono.
     */
    public function downloadName(): string
    {
        $lead = Str::of((string) $this->lead?->name)->squish()->limit(60, '');

        // El nombre del catálogo —«Ruta enfocada»— es lenguaje interno y el lead
        // lo va a leer en su teléfono. Lo que recibe es su ruta formativa.
        $documento = $this->documentType?->requires_membership === true
            ? 'Ruta formativa anual'
            : 'Ruta formativa';

        return Str::of($documento.' - '.$lead)
            ->trim(' -')
            ->append('.pdf')
            ->toString();
    }

    /** ¿Ya hay archivo que mandar? */
    public function hasPdf(): bool
    {
        return $this->pdf_path !== null;
    }

    /**
     * ¿Se corrigió después de emitirse?
     *
     * Se pregunta donde antes se preguntaba el estado: el estado de una ruta es
     * casi siempre el mismo —se emite y se renderiza en el mismo minuto— y en
     * cambio que un documento se haya rehecho tres veces sí dice algo de él.
     */
    public function wasRevised(): bool
    {
        return $this->revisions > 0;
    }

    protected function casts(): array
    {
        return [
            'status' => RouteStatus::class,
            'snapshot' => 'array',
            'rendered_at' => 'datetime',
            'revised_at' => 'datetime',
        ];
    }
}
