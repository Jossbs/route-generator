<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\TargetScope;

/**
 * De quién son los números: todo ventas, un equipo o una persona.
 */
final class Scope
{
    private function __construct(
        public readonly TargetScope $type,
        public readonly ?int $id = null,
    ) {}

    public static function general(): self
    {
        return new self(TargetScope::General);
    }

    public static function team(int $teamId): self
    {
        return new self(TargetScope::Equipo, $teamId);
    }

    public static function person(int $userId): self
    {
        return new self(TargetScope::Persona, $userId);
    }

    /** Lo que llega por URL: `general`, `equipo:3` o `persona:12`. */
    public static function fromInput(?string $value): self
    {
        [$tipo, $id] = array_pad(explode(':', (string) $value, 2), 2, null);

        return match ($tipo) {
            'equipo' => ctype_digit((string) $id) ? self::team((int) $id) : self::general(),
            'persona' => ctype_digit((string) $id) ? self::person((int) $id) : self::general(),
            default => self::general(),
        };
    }

    public function key(): string
    {
        return $this->id === null ? $this->type->value : $this->type->value.':'.$this->id;
    }
}
