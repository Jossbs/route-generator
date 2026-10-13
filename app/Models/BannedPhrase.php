<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RuleSeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * `pattern` es una expresión regular PCRE completa, con delimitadores y banderas.
 *
 * @property int $id
 * @property string $pattern
 * @property string $scope
 * @property string $reason
 * @property string|null $suggested_replacement
 * @property RuleSeverity $severity
 * @property bool $is_active
 */
class BannedPhrase extends Model
{
    protected $guarded = [];

    /** @param Builder<BannedPhrase> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'severity' => RuleSeverity::class,
            'is_active' => 'boolean',
        ];
    }
}
