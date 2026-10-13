<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Str;

/**
 * Los párrafos del documento que el asesor puede ajustar a mano, decidido el
 * 2026-09-24.
 *
 * Son los que se arman con datos de la captura y a veces quedan torpes —«para tu
 * puesto de estudiante», un contexto largo pegado tal cual—: el objetivo, «por qué
 * esta ruta» y la bajada de la portada. Todo lo demás (cifras, DC-3, la nota de
 * Core Tools, la contención) sale siempre del catálogo.
 *
 * El texto ajustado no se salta nada: la petición lo revisa contra
 * `banned_phrases` antes de aceptarlo, el validador lo vuelve a escanear junto con
 * el copy del catálogo y queda asentado en `route_validations`. Viaja congelado en
 * `snapshot.ajustes_copy`.
 *
 * El texto por defecto se arma aquí y en ningún otro lado, para que el que ve el
 * asesor en la revisión sea el mismo que imprime el documento.
 */
final class EditableCopy
{
    /** El largo máximo de un párrafo ajustado. */
    public const MAX = 220;

    private const SLOTS = [
        DocumentTypeSeeder::ENFOCADA => [
            'portada.ruta-enfocada' => 'Bajada de la portada',
            'porque.con-contexto' => 'Por qué esta ruta',
            'objetivo.ruta-enfocada' => 'Tu objetivo',
        ],
        DocumentTypeSeeder::ANUAL => [
            'portada.ruta-anual' => 'Bajada de la portada',
            'objetivo.ruta-anual' => 'Tu objetivo',
        ],
    ];

    public function __construct(private readonly CopyResolver $copy) {}

    /** @return list<string> */
    public function slugs(string $documentType): array
    {
        return array_keys(self::SLOTS[$documentType] ?? []);
    }

    /**
     * Los párrafos editables de un documento, con su texto de catálogo.
     *
     * Uno que no se puede armar —sin contexto capturado no hay «por qué esta
     * ruta»— no sale: no hay nada que ajustar.
     *
     * @param  array{ocupacion?: ?string, contexto?: ?string, ancla?: ?string, primer_curso?: ?string}  $datos
     * @return list<array{slug: string, etiqueta: string, texto: string}>
     */
    public function paragraphs(string $documentType, array $datos): array
    {
        $parrafos = [];

        foreach (self::SLOTS[$documentType] ?? [] as $slug => $etiqueta) {
            $texto = $this->default($slug, $datos);

            if ($texto !== null) {
                $parrafos[] = ['slug' => $slug, 'etiqueta' => $etiqueta, 'texto' => $texto];
            }
        }

        return $parrafos;
    }

    /**
     * El párrafo como sale del catálogo, con los datos de la captura.
     *
     * @param  array{ocupacion?: ?string, contexto?: ?string, ancla?: ?string, primer_curso?: ?string}  $datos
     */
    public function default(string $slug, array $datos): ?string
    {
        $ocupacion = trim((string) ($datos['ocupacion'] ?? ''));
        $contexto = rtrim(trim((string) ($datos['contexto'] ?? '')), ' .');

        return match (true) {
            str_starts_with($slug, 'objetivo.') => $this->copy->body($slug, [
                'ocupacion' => $ocupacion === '' ? null : self::inSentence($ocupacion),
                'ancla' => $datos['ancla'] ?? null,
            ]),
            $slug === 'porque.con-contexto' => $contexto === '' ? null : $this->copy->body($slug, [
                'contexto' => $contexto,
                'primer_curso' => $datos['primer_curso'] ?? null,
            ]),
            default => $this->copy->body($slug),
        };
    }

    /**
     * Lo que el asesor ajustó, limpio: solo párrafos de este documento, sin
     * espacios sobrantes y sin los que dejó vacíos, que vuelven al catálogo.
     *
     * @param  array<string, mixed>  $ajustes
     * @return array<string, string>
     */
    public function clean(array $ajustes, string $documentType): array
    {
        $limpios = [];

        foreach ($this->slugs($documentType) as $slug) {
            $texto = preg_replace('/\s+/u', ' ', trim((string) ($ajustes[$slug] ?? ''))) ?? '';

            if ($texto !== '') {
                $limpios[$slug] = $texto;
            }
        }

        return $limpios;
    }

    /**
     * La ocupación como va a media frase: «para tu puesto de gerente de TI».
     *
     * Antes se pasaba entera a minúsculas y las siglas salían rotas —«gerente de
     * ti», «ing. de calidad sqe»—. Ahora solo baja la inicial, y solo cuando la
     * segunda letra no es mayúscula: «SQE líder» se queda como está.
     */
    public static function inSentence(string $ocupacion): string
    {
        $ocupacion = trim($ocupacion);
        $segunda = mb_substr($ocupacion, 1, 1);

        if ($segunda !== '' && $segunda !== mb_strtolower($segunda)) {
            return $ocupacion;
        }

        return Str::lcfirst($ocupacion);
    }
}
