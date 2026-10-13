<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Kpis\DigestKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un resumen diario o semanal, tal como se mandó al grupo de Telegram.
 *
 * @property int $id
 * @property DigestKind $kind
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property string $body
 * @property CarbonImmutable|null $sent_at
 * @property string|null $error
 */
class KpiDigest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => DigestKind::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}
