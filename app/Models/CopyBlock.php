<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $scope
 * @property string $locale
 * @property string|null $body
 * @property array<string, mixed>|null $claims
 * @property array<string, mixed>|null $applies_when
 * @property string $status
 */
class CopyBlock extends Model
{
    protected $guarded = [];

    /** @param Builder<CopyBlock> $query */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'aprobado');
    }

    protected function casts(): array
    {
        return [
            'claims' => 'array',
            'applies_when' => 'array',
        ];
    }
}
