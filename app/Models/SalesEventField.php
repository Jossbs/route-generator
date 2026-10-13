<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Kpis\SalesFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un dato que se captura de cada evento de un tipo.
 *
 * @property int $id
 * @property int $sales_event_type_id
 * @property string $key
 * @property string $label
 * @property SalesFieldType $data_type
 * @property list<array{value: string, label: string}> $options
 * @property bool $is_required
 * @property int $position
 */
class SalesEventField extends Model
{
    protected $guarded = [];

    /** Los mismos valores por omisión que la migración, para el modelo recién creado. */
    protected $attributes = ['options' => '[]', 'is_required' => false, 'position' => 0];

    /** @return BelongsTo<SalesEventType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(SalesEventType::class, 'sales_event_type_id');
    }

    /** La etiqueta de una opción, para mostrarla en vez de su valor. */
    public function optionLabel(mixed $value): string
    {
        foreach ($this->options as $opcion) {
            if ((string) $opcion['value'] === (string) $value) {
                return $opcion['label'];
            }
        }

        return (string) $value;
    }

    protected function casts(): array
    {
        return [
            'data_type' => SalesFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }
}
