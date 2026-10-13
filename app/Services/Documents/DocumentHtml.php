<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\LearningRoute;
use Illuminate\Support\Facades\File;

/**
 * El documento como un solo archivo HTML, sin nada que ir a buscar afuera.
 *
 * Chromium headless corre dentro de un contenedor que no tiene por qué alcanzar
 * a la aplicación: si el HTML pidiera sus fuentes y su logotipo por URL, el PDF
 * saldría en Arial y sin marca, y nadie se enteraría hasta verlo en el teléfono
 * del lead. Por eso todo lo que vive en `public/` viaja incrustado.
 *
 * Solo se incrusta para el archivo. La página que el lead abre por enlace sigue
 * sirviendo sus recursos como siempre: ahí el navegador los cachea entre visitas
 * y meterlos en el HTML solo la haría pesar más.
 */
final class DocumentHtml
{
    /** Lo que se incrusta. Todo lo demás se deja tal como está. */
    private const INCRUSTABLES = [
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public function __construct(private readonly DocumentView $documents) {}

    public function for(LearningRoute $route): string
    {
        return $this->inline($this->documents->for($route)->render());
    }

    /**
     * Cambia cada referencia a un archivo de `public/` por su contenido.
     *
     * Se buscan rutas absolutas y relativas porque `asset()` produce las primeras
     * y la plantilla podría traer las segundas; lo que no exista en el disco se
     * deja como está, para que un recurso ajeno —o uno mal escrito— no reviente
     * el render entero.
     *
     * Es público porque el catálogo en PDF no sale de una ruta pero viaja igual
     * a Gotenberg, con las mismas fuentes y el mismo logotipo.
     */
    public function inline(string $html): string
    {
        $base = preg_quote(rtrim((string) config('app.url'), '/'), '#');
        $extensiones = implode('|', array_keys(self::INCRUSTABLES));

        return (string) preg_replace_callback(
            '#(?<=["\'(])(?:'.$base.')?/([\w\-/.]+\.('.$extensiones.'))(?=["\')])#i',
            fn (array $coincidencia): string => $this->dataUri($coincidencia[1], $coincidencia[2])
                ?? $coincidencia[0],
            $html,
        );
    }

    /** El archivo en base64, o null si no está donde dice estar. */
    private function dataUri(string $ruta, string $extension): ?string
    {
        $archivo = public_path($ruta);

        if (! File::isFile($archivo)) {
            return null;
        }

        $tipo = self::INCRUSTABLES[strtolower($extension)];

        return 'data:'.$tipo.';base64,'.base64_encode((string) File::get($archivo));
    }
}
