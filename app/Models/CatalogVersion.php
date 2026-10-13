<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $label
 * @property string $source
 * @property CarbonImmutable|null $published_at
 * @property string|null $checksum
 * @property string|null $notes
 */
class CatalogVersion extends Model
{
    protected $guarded = [];

    /** @return HasMany<LearningRoute, $this> */
    public function learningRoutes(): HasMany
    {
        return $this->hasMany(LearningRoute::class);
    }

    /**
     * La versión con la que se genera hoy: la publicada más reciente.
     *
     * Cada ruta guarda cuál fue. Cuando llegue el Excel como v2, los documentos
     * emitidos contra v1 siguen sabiendo contra qué catálogo se armaron.
     */
    public static function current(): self
    {
        return static::query()
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
