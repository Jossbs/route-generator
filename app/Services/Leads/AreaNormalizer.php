<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Enums\LeadArea;
use Illuminate\Support\Str;

/**
 * Convierte lo que el asesor capturó como área en un valor único y comparable.
 *
 * El asesor está en WhatsApp con el lead enfrente y escribe rápido. Sin esto, la
 * misma área entra a la base como «Calidad», «calidad», «CALIDAD», «Control de
 * calidad», «QA» y «Caliad», y cualquier conteo por área deja de significar algo.
 * Aquí se decide una sola vez, en el camino de entrada, y no en cada consulta.
 *
 * Tres intentos, del más estricto al más tolerante:
 *
 *  1. El valor de la lista, si el asesor eligió uno.
 *  2. El texto reconocido: se normaliza —minúsculas, sin acentos, sin
 *     puntuación— y se compara contra los sinónimos de `LeadArea`, primero
 *     exacto y después como frase contenida, de modo que «jefe de almacén»
 *     caiga en Logística y Almacén.
 *  3. El texto con un error de dedo: distancia de Levenshtein contra los mismos
 *     sinónimos, con una tolerancia proporcional al largo. «mantenimeinto» entra
 *     a Mantenimiento; «ventas» nunca se confunde con «rentas» porque en cadenas
 *     cortas la tolerancia es cero.
 *
 * Lo que no se reconoce **no se fuerza**: se guarda como texto libre, pero
 * estandarizado —espacios colapsados, capitalización de título, conectores en
 * minúscula— para que dos personas que escriban lo mismo produzcan la misma fila.
 * Inventar una coincidencia sería peor que un área nueva: manda al lead una ruta
 * pensada para otra área.
 */
final class AreaNormalizer
{
    /** El valor con el que la pantalla pide el campo abierto. */
    public const OTRO = 'otro';

    /** @var array<string, LeadArea>|null Sinónimo normalizado => área. */
    private static ?array $index = null;

    /**
     * `$knownOthers` son las áreas nuevas que ya se guardaron. Se piden solo
     * cuando el texto no es de las catorce, para no consultar la base en cada
     * captura.
     *
     * @param  (\Closure(): iterable<string>)|null  $knownOthers
     */
    public function __construct(private readonly ?\Closure $knownOthers = null) {}

    /**
     * @param  string|null  $area  Lo que eligió en la lista, o `otro`.
     * @param  string|null  $other  Lo que escribió cuando eligió `otro`.
     */
    public function resolve(?string $area, ?string $other = null): CapturedArea
    {
        $elegida = LeadArea::tryFrom(trim((string) $area));

        if ($elegida instanceof LeadArea) {
            return new CapturedArea(area: $elegida);
        }

        // Sin lista, el texto puede venir por cualquiera de los dos campos: el
        // abierto cuando eligió «Otro», y `area` cuando la captura llegó por URL
        // desde Kommo, donde nadie garantiza que mande uno de los catorce valores.
        $texto = trim((string) ($other ?? ''));

        if ($texto === '' && trim((string) $area) !== self::OTRO) {
            $texto = trim((string) $area);
        }

        if ($texto === '') {
            return CapturedArea::empty();
        }

        $reconocida = $this->recognize($texto);

        return $reconocida instanceof LeadArea
            ? new CapturedArea(area: $reconocida)
            : new CapturedArea(other: $this->homologate($texto) ?? $this->standardize($texto));
    }

    /**
     * El área nueva que ya existe con otra escritura.
     *
     * Estandarizar no alcanza para homologar: «control de gestion» y «Control de
     * Gestión» quedan distintas porque el acento no se adivina. Si ya se guardó
     * una que se lee igual —o con un error de dedo—, se reutiliza su escritura y
     * el conteo por área no se parte en dos filas.
     */
    private function homologate(string $texto): ?string
    {
        if ($this->knownOthers === null) {
            return null;
        }

        $clave = $this->key($texto);
        $tolerancia = $this->tolerance($clave);
        $mejor = null;
        $distancia = PHP_INT_MAX;

        foreach (($this->knownOthers)() as $conocida) {
            $actual = levenshtein($clave, $this->key((string) $conocida));

            if ($actual <= $tolerancia && $actual < $distancia) {
                $mejor = (string) $conocida;
                $distancia = $actual;
            }
        }

        return $mejor;
    }

    /** ¿A cuál de las catorce corresponde este texto, si es que a alguna? */
    public function recognize(string $texto): ?LeadArea
    {
        $clave = $this->key($texto);

        if ($clave === '') {
            return null;
        }

        $indice = self::index();

        if (isset($indice[$clave])) {
            return $indice[$clave];
        }

        return $this->byPhrase($clave, $indice) ?? $this->byTypo($clave, $indice);
    }

    /**
     * El sinónimo más largo que aparezca dentro del texto.
     *
     * Se toma el más largo a propósito: «seguridad e higiene» y «seguridad» son
     * los dos sinónimos de la misma área hoy, pero el día que se separen, quien
     * escribió la frase completa quiso decir la frase completa.
     *
     * @param  array<string, LeadArea>  $indice
     */
    private function byPhrase(string $clave, array $indice): ?LeadArea
    {
        $mejor = null;
        $largo = 0;

        foreach ($indice as $sinonimo => $area) {
            // Menos de cuatro letras dentro de una frase es ruido: «ti» aparece
            // en «mantenimiento» y «qa» en cualquier parte.
            if (mb_strlen($sinonimo) < 4 || mb_strlen($sinonimo) <= $largo) {
                continue;
            }

            if (str_contains(" {$clave} ", " {$sinonimo} ")) {
                $mejor = $area;
                $largo = mb_strlen($sinonimo);
            }
        }

        return $mejor;
    }

    /**
     * El sinónimo más cercano, si el texto es uno con un error de dedo.
     *
     * @param  array<string, LeadArea>  $indice
     */
    private function byTypo(string $clave, array $indice): ?LeadArea
    {
        $tolerancia = $this->tolerance($clave);

        if ($tolerancia === 0) {
            return null;
        }

        $mejor = null;
        $distancia = PHP_INT_MAX;

        foreach ($indice as $sinonimo => $area) {
            // Comparar cadenas de largo muy distinto no detecta un error de dedo,
            // detecta otra palabra.
            if (abs(mb_strlen($sinonimo) - mb_strlen($clave)) > $tolerancia) {
                continue;
            }

            $actual = levenshtein($clave, $sinonimo);

            if ($actual <= $tolerancia && $actual < $distancia) {
                $mejor = $area;
                $distancia = $actual;
            }
        }

        return $mejor;
    }

    /**
     * Cuántos caracteres de diferencia se perdonan.
     *
     * Proporcional al largo, y cero hasta seis letras: en cadenas cortas un
     * carácter ya es otra palabra.
     */
    private function tolerance(string $clave): int
    {
        return match (true) {
            mb_strlen($clave) <= 6 => 0,
            mb_strlen($clave) <= 11 => 1,
            default => 2,
        };
    }

    /** El texto reducido a lo comparable: sin acentos, sin puntuación, en minúscula. */
    private function key(string $texto): string
    {
        $clave = Str::lower(Str::ascii(trim($texto)));
        $clave = preg_replace('/[^a-z0-9]+/', ' ', $clave) ?? '';

        return trim(preg_replace('/\s+/', ' ', $clave) ?? '');
    }

    /**
     * El área nueva, escrita de una sola manera.
     *
     * Conserva los acentos que el asesor escribió —no se le puede adivinar el
     * acento a una palabra que no está en ninguna lista— y estandariza lo que sí
     * se puede: espacios, capitalización y largo.
     */
    private function standardize(string $texto): string
    {
        return Str::limit((string) (new TextNormalizer)->title(trim($texto, " \t\n\r\0\x0B.")), 80, '');
    }

    /**
     * Sinónimo normalizado => área, armado una vez por proceso.
     *
     * @return array<string, LeadArea>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $normalizador = new self;
        $indice = [];

        foreach (LeadArea::cases() as $area) {
            foreach ([...$area->synonyms(), $area->label(), $area->value] as $sinonimo) {
                $clave = $normalizador->key($sinonimo);

                // El primero gana: si dos áreas comparten un sinónimo, lo
                // conserva la que lo declara antes en el enum.
                $indice[$clave] ??= $area;
            }
        }

        return self::$index = $indice;
    }
}
