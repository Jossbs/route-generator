<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Algo que ventas registra: una venta, un cobro, una cancelación, un lead.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
class SalesEventType extends Model
{
    protected $guarded = [];

    /** Los mismos valores por omisión que la migración, para el modelo recién creado. */
    protected $attributes = ['is_active' => true];

    /** @return HasMany<SalesEventField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(SalesEventField::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<SalesEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SalesEvent::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
