<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RuleSeverity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Una restricción de dominio expresada como dato. El intérprete que la ejecuta se
 * resuelve por `type`; los miembros viven en las tablas pivote, no en el JSONB.
 *
 * @property int $id
 * @property string $code
 * @property string $type
 * @property RuleSeverity $severity
 * @property bool $waivable
 * @property list<string>|null $applies_to_document_types
 * @property list<string>|null $applies_to_roles
 * @property array<string, mixed>|null $parameters
 * @property string $message_es
 * @property string|null $source_note
 * @property bool $is_active
 * @property CarbonImmutable|null $effective_from
 */
class CatalogRule extends Model
{
    protected $guarded = [];

    /** @return BelongsToMany<Course, $this> */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'rule_courses', 'rule_id', 'course_id')
            ->withPivot('role');
    }

    /** @return BelongsToMany<Specialization, $this> */
    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'rule_specializations', 'rule_id', 'specialization_id')
            ->withPivot('role');
    }

    /**
     * Reglas vigentes hoy.
     *
     * @param  Builder<CatalogRule>  $query
     */
    public function scopeInEffect(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()));
    }

    /**
     * `applies_to_document_types` vacío o nulo significa "todos los documentos".
     */
    public function appliesToDocumentType(string $slug): bool
    {
        return blank($this->applies_to_document_types)
            || in_array($slug, $this->applies_to_document_types, true);
    }

    /**
     * `parameters` es JSONB: llega sin garantía de forma. Estos accesores
     * coaccionan en la frontera para que los intérpretes reciban tipos firmes.
     */
    public function parameter(string $key, mixed $default = null): mixed
    {
        return data_get($this->parameters, $key, $default);
    }

    /**
     * @param  list<string>|null  $default
     * @return list<string>|null
     */
    public function listParameter(string $key, ?array $default = null): ?array
    {
        $value = $this->parameter($key);

        if (! is_array($value)) {
            return $value === null ? $default : null;
        }

        return array_values(array_map(strval(...), $value));
    }

    /** @return array<string, string> */
    public function mapParameter(string $key): array
    {
        $value = $this->parameter($key);

        if (! is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $from => $to) {
            $map[(string) $from] = (string) $to;
        }

        return $map;
    }

    public function stringParameter(string $key, string $default): string
    {
        $value = $this->parameter($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function intParameter(string $key, int $default): int
    {
        $value = $this->parameter($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolParameter(string $key, bool $default): bool
    {
        $value = $this->parameter($key, $default);

        return is_bool($value) ? $value : $default;
    }

    public function isBlocking(): bool
    {
        return $this->severity === RuleSeverity::Blocking;
    }

    protected function casts(): array
    {
        return [
            'severity' => RuleSeverity::class,
            'applies_to_document_types' => 'array',
            'applies_to_roles' => 'array',
            'parameters' => 'array',
            'is_active' => 'boolean',
            'waivable' => 'boolean',
            'effective_from' => 'datetime',
        ];
    }
}
