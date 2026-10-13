<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadArea;
use App\Services\Leads\MexicanPhone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property LeadArea|null $area
 * @property string|null $area_other
 * @property string $source
 * @property string|null $external_id
 * @property int|null $requested_specialization_id
 * @property int|null $archetype_id
 * @property int|null $advisor_id
 * @property string $status
 * @property array<string, mixed>|null $raw_payload
 */
class Lead extends Model
{
    protected $guarded = [];

    /**
     * El teléfono es la llave del reencuentro, así que se guarda de una sola
     * forma —`+52` y diez dígitos— venga de donde venga. Lo que no es un número
     * de diez dígitos se conserva como llegó: descartarlo borraría un dato que
     * alguien tendrá que revisar.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor): ?string => $valor,
            set: fn (?string $valor): ?string => blank($valor)
                ? null
                : (MexicanPhone::normalize($valor) ?? trim($valor)),
        );
    }

    /** @return BelongsTo<Specialization, $this> */
    public function requestedSpecialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class, 'requested_specialization_id');
    }

    /** @return BelongsTo<Archetype, $this> */
    public function archetype(): BelongsTo
    {
        return $this->belongsTo(Archetype::class);
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<LearningRoute, $this> */
    public function learningRoutes(): HasMany
    {
        return $this->hasMany(LearningRoute::class);
    }

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'area' => LeadArea::class,
        ];
    }
}
