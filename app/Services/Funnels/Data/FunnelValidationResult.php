<?php

declare(strict_types=1);

namespace App\Services\Funnels\Data;

use App\Services\Catalog\RouteTotals;
use App\Services\Funnels\FindingSuggester;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\Data\ValidationReport;

/**
 * El veredicto sobre una ruta curada, redactado para quien arma la promoción y no
 * para un desarrollador.
 *
 * Separa bloqueantes de advertencias porque las dos se resuelven distinto: un
 * bloqueante impide publicar por cualquier vía; una advertencia se publica
 * confirmándola, y esa confirmación queda asentada en el snapshot para que después
 * se pueda auditar quién sabía qué.
 *
 * Cada hallazgo trae `curso_ids` para que la interfaz pueda resaltar los renglones
 * involucrados en vez de dejar a la persona buscándolos, y `sugerencias` con la
 * lista ya corregida para aplicarla con un clic. `ignorable` dice si quien curó
 * el embudo puede dispensarlo en lugar de corregirlo.
 *
 * @phpstan-type Finding array{codigo: string, mensaje: string, curso_ids: list<int>, detalles: array<string, mixed>, ignorable: bool, sugerencias: list<array{etiqueta: string, curso_ids: list<int>}>}
 */
final readonly class FunnelValidationResult
{
    /**
     * @param  list<Finding>  $bloqueantes
     * @param  list<Finding>  $advertencias
     * @param  list<Finding>  $ignorados  Lo que el embudo dispensó: ya no bloquea ni pide confirmación.
     */
    private function __construct(
        public array $bloqueantes,
        public array $advertencias,
        public array $ignorados,
        public RouteTotals $totales,
        public ValidationReport $report,
    ) {}

    /** @param list<int> $courseIds La lista evaluada, en orden: de ella parten las sugerencias. */
    public static function from(
        ValidationReport $report,
        RouteTotals $totals,
        array $courseIds = [],
        ?FindingSuggester $suggester = null,
    ): self {
        $suggester ??= new FindingSuggester;
        $findings = fn (array $evaluations): array => array_map(
            fn (RuleEvaluation $e): array => self::finding($e, $courseIds, $suggester),
            array_values($evaluations),
        );

        return new self(
            bloqueantes: $findings($report->blockingFailures()->all()),
            advertencias: $findings($report->warnings()->all()),
            ignorados: $findings($report->waived()->all()),
            totales: $totals,
            report: $report,
        );
    }

    /** La única puerta hacia la publicación. */
    public function publicable(): bool
    {
        return $this->bloqueantes === [];
    }

    public function tieneAdvertencias(): bool
    {
        return $this->advertencias !== [];
    }

    /**
     * Los códigos de advertencia que quien publica tiene que confirmar uno por
     * uno. Se guardan en el snapshot, no se descartan.
     *
     * @return list<string>
     */
    public function codigosDeAdvertencia(): array
    {
        return array_column($this->advertencias, 'codigo');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'bloqueantes' => $this->bloqueantes,
            'advertencias' => $this->advertencias,
            'ignorados' => $this->ignorados,
            'totales' => $this->totales->toArray(),
        ];
    }

    /**
     * @param  list<int>  $courseIds
     * @return Finding
     */
    private static function finding(RuleEvaluation $e, array $courseIds, FindingSuggester $suggester): array
    {
        return [
            'codigo' => $e->ruleCode,
            'mensaje' => $e->message ?? $e->ruleCode,
            'curso_ids' => $e->courseIds(),
            'detalles' => $e->details,
            // Las ocho de §4 no se ignoran: el panel ni siquiera ofrece el botón.
            'ignorable' => $e->waivable,
            'sugerencias' => $courseIds === [] ? [] : $suggester->for($e, $courseIds),
        ];
    }
}
