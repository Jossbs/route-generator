<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un indicador. Lo que se mide son sus medidas; ver `KpiMeasure`.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $display_order
 * @property bool $is_active
 */
class Kpi extends Model
{
    protected $guarded = [];

    /** Los mismos valores por omisión que la migración, para el modelo recién creado. */
    protected $attributes = ['is_active' => true, 'display_order' => 0];

    /** @return HasMany<KpiMeasure, $this> */
    public function measures(): HasMany
    {
        return $this->hasMany(KpiMeasure::class)->orderBy('position')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
