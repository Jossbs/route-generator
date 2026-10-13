<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RouteLayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $delivery
 * @property bool $requires_membership
 * @property list<string> $layers
 * @property array<string, mixed> $composition_policy
 * @property bool $requires_tracking
 */
class DocumentType extends Model
{
    protected $guarded = [];

    /** @return HasMany<LearningRoute, $this> */
    public function learningRoutes(): HasMany
    {
        return $this->hasMany(LearningRoute::class);
    }

    /**
     * Cómo lo nombra el asesor.
     *
     * En el catálogo se llaman «Ruta enfocada» y «Ruta anual completa», que es lo
     * que describen. El asesor los conoce como inicial y final —así los elige al
     * capturar— y ver dos nombres para lo mismo lo obliga a traducir cada vez que
     * abre la bandeja. Lo decide `requires_membership` y no el slug: es el dato
     * que ya distingue a los dos documentos en la tabla, así que un tercero que se
     * inserte mañana cae solo del lado que le toca.
     */
    public function advisorLabel(): string
    {
        return $this->requires_membership ? 'Ruta final' : 'Ruta inicial';
    }

    public function hasLayer(RouteLayer $layer): bool
    {
        return in_array($layer->value, $this->layers ?? [], true);
    }

    protected function casts(): array
    {
        return [
            'requires_membership' => 'boolean',
            'requires_tracking' => 'boolean',
            'layers' => 'array',
            'composition_policy' => 'array',
        ];
    }
}
