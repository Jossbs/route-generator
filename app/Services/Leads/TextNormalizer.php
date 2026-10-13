<?php

declare(strict_types=1);

namespace App\Services\Leads;

use Illuminate\Support\Str;

/**
 * Deja lo que el asesor tecleó escrito como se escribe en español.
 *
 * El asesor captura con el lead enfrente en WhatsApp: con el bloqueo de
 * mayúsculas puesto, todo en minúscula desde el celular o en Mayúscula Inicial
 * por costumbre del inglés. Lo que se guarda —y lo que va impreso en la portada—
 * no puede depender de cómo venía el teclado.
 *
 * Dos formas, porque en español no se escriben igual:
 *
 *  - Un nombre de persona lleva mayúscula en cada nombre y apellido, y las
 *    partículas en minúscula: «María José de la Cruz», «Pérez-Gómez».
 *  - Todo lo demás —ocupación, lo que dijo, el plan— es una frase: mayúscula
 *    inicial y lo demás en minúscula, salvo siglas («Gerente de TI en GM»).
 *
 * En una frase no se puede adivinar un nombre propio que llegó en minúscula, así
 * que cuando el asesor mezcló mayúsculas a propósito («Ingeniero en Nissan») se
 * le respeta y solo se corrige lo que es evidentemente un accidente del teclado.
 */
final class TextNormalizer
{
    /** Partículas que en un nombre propio van en minúscula, salvo al inicio. */
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'e'];

    /** Palabras que en español no se capitalizan dentro de un título. */
    private const CONECTORES = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'en', 'a', 'al', 'por', 'para', 'con'];

    /** Hasta este largo, una palabra toda en mayúsculas dentro de una frase es sigla. */
    private const LARGO_SIGLA = 4;

    /** «mARIANA cruz» → «Mariana Cruz»; «maria jose de la o» → «Maria Jose de la O». */
    public function personName(?string $texto): ?string
    {
        $limpio = $this->clean($texto);

        if ($limpio === null) {
            return null;
        }

        $palabras = explode(' ', mb_strtolower($limpio));

        return implode(' ', array_map(
            fn (string $palabra, int $i): string => $i > 0 && in_array($palabra, self::PARTICULAS, true)
                ? $palabra
                : $this->capitalizeSegments($palabra),
            $palabras,
            array_keys($palabras),
        ));
    }

    /** «INGENIERA DE PROCESOS» → «Ingeniera de procesos»; «gerente de TI» → «Gerente de TI». */
    public function sentence(?string $texto): ?string
    {
        $limpio = $this->clean($texto);

        if ($limpio === null) {
            return null;
        }

        $palabras = explode(' ', $limpio);

        // Todo en mayúsculas o todo en minúsculas no dice nada del texto, solo del
        // teclado: se reescribe entera. En Mayúscula Inicial tampoco hay intención,
        // es costumbre del inglés. Solo la mezcla deliberada se respeta.
        $reescribir = $limpio === mb_strtoupper($limpio)
            || $limpio === mb_strtolower($limpio)
            || $this->isTitleCased($palabras);

        $palabras = array_map(fn (string $palabra): string => match (true) {
            $this->isAcronym($palabra) && $limpio !== mb_strtoupper($limpio) => $palabra,
            $reescribir, $this->isKeyboardAccident($palabra) => mb_strtolower($palabra),
            default => $palabra,
        }, $palabras);

        $frase = implode(' ', $palabras);

        return mb_strtoupper(mb_substr($frase, 0, 1)).mb_substr($frase, 1);
    }

    /** «control DE gestión» → «Control de Gestión». */
    public function title(?string $texto): ?string
    {
        $limpio = $this->clean($texto);

        if ($limpio === null) {
            return null;
        }

        $palabras = explode(' ', mb_strtolower($limpio));

        return implode(' ', array_map(
            fn (string $palabra, int $i): string => $i > 0 && in_array($palabra, self::CONECTORES, true)
                ? $palabra
                : Str::ucfirst($palabra),
            $palabras,
            array_keys($palabras),
        ));
    }

    /** Sin espacios de sobra ni puntuación colgando en los extremos; vacío es null. */
    private function clean(?string $texto): ?string
    {
        $limpio = preg_replace('/\s+/u', ' ', (string) $texto) ?? '';
        $limpio = trim($limpio, " \t\n\r\0\x0B,;:-");

        return $limpio === '' ? null : $limpio;
    }

    /** «pérez-gómez» → «Pérez-Gómez», «o'connor» → «O'Connor». */
    private function capitalizeSegments(string $palabra): string
    {
        return preg_replace_callback(
            "/(^|[-'’])(\p{L})/u",
            fn (array $m): string => $m[1].mb_strtoupper($m[2]),
            $palabra,
        ) ?? $palabra;
    }

    /** «TI», «GM», «CNC»: corta, toda en mayúsculas y con al menos dos letras. */
    private function isAcronym(string $palabra): bool
    {
        $letras = preg_replace('/[^\p{L}]/u', '', $palabra) ?? '';

        return mb_strlen($letras) >= 2
            && mb_strlen($letras) <= self::LARGO_SIGLA
            && $letras === mb_strtoupper($letras);
    }

    /** «pROCESOS» o «INGENIERO»: minúscula seguida de mayúsculas, o una palabra larga gritada. */
    private function isKeyboardAccident(string $palabra): bool
    {
        return preg_match('/^\p{Ll}\p{Lu}/u', $palabra) === 1
            || (mb_strlen($palabra) > self::LARGO_SIGLA && $palabra === mb_strtoupper($palabra) && preg_match('/\p{L}/u', $palabra) === 1);
    }

    /**
     * «Ingeniero De Procesos En Una Automotriz»: cada palabra con mayúscula inicial.
     *
     * @param  list<string>  $palabras
     */
    private function isTitleCased(array $palabras): bool
    {
        $significativas = array_filter(
            $palabras,
            fn (string $p): bool => preg_match('/^\p{L}/u', $p) === 1 && ! $this->isAcronym($p),
        );

        if (count($significativas) < 2) {
            return false;
        }

        foreach ($significativas as $palabra) {
            if (preg_match('/^\p{Lu}/u', $palabra) !== 1) {
                return false;
            }
        }

        return true;
    }
}
