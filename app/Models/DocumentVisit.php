<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $route_id
 * @property int $revision
 * @property int $progress
 * @property int $active_ms
 * @property array<string, int> $sections
 * @property list<string> $clicks
 * @property string|null $furthest_section
 * @property string|null $last_section
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class DocumentVisit extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sections' => 'array', 'clicks' => 'array'];
    }
}
