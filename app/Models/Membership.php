<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property string $full_name
 * @property string $occupation
 * @property MembershipPlan|null $plan
 * @property string|null $plan_other
 * @property string|null $price
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int|null $created_by
 */
class Membership extends Model
{
    protected $guarded = [];

    /**
     * El nombre del plan tal como se imprime y como se lee en la bandeja.
     *
     * Lo negociado fuera de la lista publicada se guarda aparte, así que resolverlo
     * es de una sola pieza y no de cada sitio que lo muestre.
     */
    public function planLabel(): ?string
    {
        if ($this->plan === null) {
            return null;
        }

        return $this->plan === MembershipPlan::Otro
            ? ($this->plan_other ?: null)
            : $this->plan->label();
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'plan' => MembershipPlan::class,
            'price' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'weekly_hours' => 'integer',
        ];
    }
}
