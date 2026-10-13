<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $motivation
 * @property int|null $bridge_phrase_block_id
 * @property bool $is_placeholder
 * @property array<string, mixed>|null $match_rules
 */
class Archetype extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<CopyBlock, $this> */
    public function bridgePhraseBlock(): BelongsTo
    {
        return $this->belongsTo(CopyBlock::class, 'bridge_phrase_block_id');
    }

    /** @return BelongsToMany<Specialization, $this> */
    public function specializationAffinities(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'archetype_specialization_affinity')
            ->withPivot(['weight', 'suggested_role']);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tagAffinities(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'archetype_tag_affinity')
            ->withPivot('weight');
    }

    protected function casts(): array
    {
        return [
            'is_placeholder' => 'boolean',
            // Dormida: la detección automática de arquetipo sigue fuera de la v1.
            // El asesor lo elige de una lista y la columna se queda vacía.
            'match_rules' => 'array',
        ];
    }
}
