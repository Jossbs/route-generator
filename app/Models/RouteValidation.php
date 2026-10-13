<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValidationResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property string $rule_code
 * @property ValidationResult $result
 * @property array<string, mixed>|null $details
 * @property CarbonImmutable $evaluated_at
 */
class RouteValidation extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<LearningRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(LearningRoute::class, 'route_id');
    }

    protected function casts(): array
    {
        return [
            'result' => ValidationResult::class,
            'details' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }
}
