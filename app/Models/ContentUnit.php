<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La unidad que se cuenta. Varios cursos pueden apuntar a la misma:
 * los cuatro de Excel comparten una sola.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $specialization_id
 */
class ContentUnit extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Specialization, $this> */
    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
