<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\CopyBlock;
use App\Models\DocumentType;
use Illuminate\Support\Collection;

/**
 * El copy del documento, resuelto desde el catálogo.
 *
 * El copy es catálogo, no plantilla: si viviera dentro de un Blade, las
 * restricciones de fraseo de docs/ARQUITECTURA.md §4.4 y §4.5 serían inverificables, porque
 * no habría nada que el validador pudiera escanear antes de renderizar.
 *
 * Un bloque en estado `pendiente` es un hueco que nadie ha escrito todavía, y
 * aquí simplemente no sale: el documento se lee entero, solo más corto. La
 * alternativa —rellenarlo con texto plausible— está prohibida por docs/ARQUITECTURA.md §6, y
 * la otra —imprimir un marcador— pondría lenguaje interno delante de un lead.
 *
 * Lo que sí se hace es contarlos, para que quien va a mandar el documento sepa
 * qué le falta antes de mandarlo.
 */
final class CopyResolver
{
    /** @var Collection<int, CopyBlock>|null */
    private ?Collection $blocks = null;

    /**
     * El texto de un bloque, o null si todavía no está escrito.
     *
     * `borrador` sí se imprime: es texto real de DP SyS que Contenido aún no
     * aprueba, no relleno. `pendiente` es el hueco.
     *
     * Los `$tokens` sustituyen marcadores `{clave}` del cuerpo. Es lo que deja que
     * una frase personalizada —«armada para tu rol de …»— siga siendo copy de
     * catálogo y no texto dentro de un Blade: el validador escanea el cuerpo con
     * sus marcadores antes de renderizar, y lo que se sustituye son datos que el
     * asesor capturó o cifras calculadas de la propia ruta. Nada inventado.
     *
     * Un marcador sin valor deja el bloque fuera. Imprimir «tu rol de {ocupacion}»
     * delante de un lead es peor que no imprimir la frase.
     *
     * @param  array<string, string|null>  $tokens
     */
    public function body(string $slug, array $tokens = []): ?string
    {
        $block = $this->all()->firstWhere('slug', $slug);

        if ($block === null || $block->status === 'pendiente') {
            return null;
        }

        if (blank($block->body)) {
            return null;
        }

        return $this->interpolate((string) $block->body, $tokens);
    }

    /**
     * El cuerpo tal como está en el catálogo, con sus marcadores sin sustituir.
     *
     * Es para quien necesita saber qué parte del texto salió de un dato: el guión
     * del audio resalta lo personalizado para que el asesor lo lea con cuidado, y
     * eso se pierde si recibe la frase ya interpolada. Mismo criterio que `body()`
     * con los huecos: `pendiente` no sale.
     */
    public function raw(string $slug): ?string
    {
        $block = $this->all()->firstWhere('slug', $slug);

        if ($block === null || $block->status === 'pendiente' || blank($block->body)) {
            return null;
        }

        return (string) $block->body;
    }

    /**
     * Los bloques escritos de un alcance, en el orden en que se sembraron.
     *
     * Es para la hoja «Quiénes somos», que no pide un bloque por su clave sino
     * todas las reseñas para escoger las que hablan de la ruta del lead.
     *
     * @return Collection<int, CopyBlock>
     */
    public function scoped(string $scope): Collection
    {
        return $this->all()
            ->filter(fn (CopyBlock $block): bool => $block->scope === $scope)
            ->filter(fn (CopyBlock $block): bool => $block->status !== 'pendiente' && filled($block->body))
            ->sortBy('id')
            ->values();
    }

    /**
     * Sustituye los marcadores, o devuelve null si alguno se quedó sin valor.
     *
     * @param  array<string, string|null>  $tokens
     */
    private function interpolate(string $body, array $tokens): ?string
    {
        foreach ($tokens as $clave => $valor) {
            if (blank($valor)) {
                return null;
            }

            $body = str_replace('{'.$clave.'}', (string) $valor, $body);
        }

        return preg_match('/\{[a-z_]+\}/', $body) === 1 ? null : $body;
    }

    /**
     * Todo el texto de catálogo que este documento va a imprimir, para que el
     * validador lo escanee antes de renderizar.
     *
     * Sin esto la regla de fraseo escaneaba el vacío en el flujo del formulario:
     * los composidores mandan `copyText: []` y el copy solo aparecía al pintar la
     * plantilla, ya pasado el validador. El copy es catálogo justamente para que
     * sea escaneable, así que el escaneo tiene que verlo.
     *
     * @return list<string>
     */
    public function bodiesFor(DocumentType $documentType): array
    {
        return array_values($this->all()
            ->filter(fn (CopyBlock $block): bool => $block->status !== 'pendiente')
            ->filter(fn (CopyBlock $block): bool => $this->appliesTo($block, $documentType))
            ->map(fn (CopyBlock $block): string => (string) $block->body)
            ->filter(fn (string $body): bool => filled($body))
            ->all());
    }

    /**
     * Los bloques que le faltan a este tipo de documento, por su clave.
     *
     * Es lo que la vista del asesor muestra antes de entregar: no impide generar
     * —el documento funciona sin ellos— pero deja de ser una sorpresa.
     *
     * @return list<string>
     */
    public function missingFor(DocumentType $documentType): array
    {
        return array_values($this->all()
            ->filter(fn (CopyBlock $block): bool => $block->status === 'pendiente')
            ->filter(fn (CopyBlock $block): bool => $this->appliesTo($block, $documentType))
            ->map(fn (CopyBlock $block): string => $block->slug)
            ->all());
    }

    /**
     * ¿Este bloque es de este documento? Un bloque sin `applies_when` es genérico
     * y aplica a todos.
     */
    private function appliesTo(CopyBlock $block, DocumentType $documentType): bool
    {
        $condition = $block->applies_when['document_type'] ?? null;

        return $condition === null || $condition === $documentType->slug;
    }

    /** @return Collection<int, CopyBlock> */
    private function all(): Collection
    {
        return $this->blocks ??= CopyBlock::query()->where('locale', 'es_MX')->get();
    }
}
