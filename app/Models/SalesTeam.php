<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un equipo de ventas. Quién pertenece y desde cuándo vive en `sales_assignments`.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class SalesTeam extends Model
{
    protected $guarded = [];

    /** Los mismos valores por omisión que la migración, para el modelo recién creado. */
    protected $attributes = ['is_active' => true];

    /** @return HasMany<SalesAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(SalesAssignment::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
