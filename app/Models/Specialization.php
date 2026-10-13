<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $display_order
 * @property string $completeness_status
 * @property list<string>|null $missing_components
 * @property array<string, bool> $allowed_roles
 * @property string|null $notes
 */
class Specialization extends Model
{
    protected $guarded = [];

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** @return HasMany<ContentUnit, $this> */
    public function contentUnits(): HasMany
    {
        return $this->hasMany(ContentUnit::class);
    }

    public function isPartial(): bool
    {
        return $this->completeness_status === 'partial';
    }

    /**
     * ¿Puede esta especialización ocupar el rol de presentación indicado?
     * Claves posibles: ancla, complementaria, bloque_itinerario, inventario.
     * Un rol no declarado se considera permitido: `allowed_roles` enumera
     * excepciones, no una lista blanca cerrada.
     */
    public function allowsRole(string $role): bool
    {
        return (bool) ($this->allowed_roles[$role] ?? true);
    }

    protected function casts(): array
    {
        return [
            'missing_components' => 'array',
            'allowed_roles' => 'array',
        ];
    }
}
