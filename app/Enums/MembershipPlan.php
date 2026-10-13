<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * El plan que el miembro contrató.
 *
 * Era texto libre y va impreso en la portada del documento anual: dos asesores
 * escribían «Expert Anual», «expert anual» y «anual» para lo mismo, y el lead
 * recibía su cortesía con el nombre de su plan escrito de tres formas. Un
 * vocabulario cerrado lo resuelve donde se captura, no donde se imprime.
 *
 * La lista son los planes que DP SyS publica (docs/ARQUITECTURA.md §1). Lo negociado fuera de
 * ellos entra por `Otro`, con el nombre en `memberships.plan_other`.
 *
 * ### Sin precios aquí
 *
 * El enum nombra planes, no los tarifa. El precio de cada uno cambia con las
 * promociones y lo que importa asentar es lo que este miembro pagó, no lo que
 * costaba el día que alguien escribió este archivo: por eso lo teclea el asesor en
 * `memberships.price` y por eso no hay tabla de anclas de precio en la v1
 * (docs/ARQUITECTURA.md §8). Nada de esto se imprime en el documento (docs/ARQUITECTURA.md §4.5).
 *
 * ### Por qué es enum y no tabla
 *
 * Mismo criterio que `LeadArea`: es un vocabulario cerrado de captura, no una
 * regla del catálogo que el validador interprete. Una tabla obligaría a una
 * pantalla de administración que la v1 no tiene.
 */
enum MembershipPlan: string
{
    case ExpertMensual = 'expert_mensual';
    case ExpertAnual = 'expert_anual';
    case Duo = 'duo';
    case Business = 'business';
    case Otro = 'otro';

    /** Como se lee en la pantalla y como se imprime en la portada. */
    public function label(): string
    {
        return match ($this) {
            self::ExpertMensual => 'Expert Mensual',
            self::ExpertAnual => 'Expert Anual',
            self::Duo => 'Duo',
            self::Business => 'Business',
            self::Otro => 'Otro',
        };
    }

    /**
     * Las opciones del select del asesor, en el orden en que se venden: el anual es
     * el que se cierra casi siempre, así que va primero.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $plan): array => ['value' => $plan->value, 'label' => $plan->label()],
            [self::ExpertAnual, self::ExpertMensual, self::Duo, self::Business, self::Otro],
        );
    }
}
