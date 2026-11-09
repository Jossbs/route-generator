<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\RouteStatus;
use App\Models\LearningRoute;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * El PDF, hecho por Chromium headless dentro de Gotenberg.
 *
 * Es el producto: lo que el asesor adjunta en la conversación con el lead y lo que el lead
 * guarda. La plantilla ya declara `@page { size: Letter }`, así que aquí no se
 * redefine el papel —`preferCssPageSize` le dice a Chromium que respete lo que el
 * documento pide— y los márgenes van en cero porque cada hoja trae los suyos.
 *
 * El HTML viaja entero, con las fuentes y el logotipo incrustados: el contenedor
 * no tiene por qué alcanzar a la aplicación, y un PDF que depende de eso es un
 * PDF que algún día sale en Arial.
 *
 * Si Gotenberg no responde, esto revienta a propósito. La cola marca la ruta como
 * fallida y la pantalla lo dice: un documento a medias que nadie sabe que está a
 * medias es peor que uno que no salió.
 */
final class GotenbergRenderer implements DocumentRenderer
{
    public function __construct(
        private readonly DocumentHtml $html,
        private readonly Http $http,
    ) {}

    public function render(LearningRoute $route): void
    {
        $pdf = $this->convert($this->html->for($route));
        $ruta = $this->path($route);

        if (! Storage::disk($this->disk())->put($ruta, $pdf)) {
            throw new RuntimeException('No se pudo guardar el PDF de la ruta.');
        }

        $route->forceFill([
            'pdf_path' => $ruta,
            'status' => RouteStatus::Rendered,
            'rendered_at' => now(),
        ])->save();
    }

    /**
     * HTML entero adentro, PDF afuera. Público porque el catálogo también sale
     * por aquí sin ser una ruta, y dos clientes de Gotenberg con opciones
     * distintas acabarían imprimiendo hojas distintas.
     */
    public function convert(string $html): string
    {
        $respuesta = $this->http
            ->timeout((int) config('documents.gotenberg.timeout'))
            ->attach('files', $html, 'index.html')
            ->post(rtrim((string) config('documents.gotenberg.url'), '/').'/forms/chromium/convert/html', [
                'preferCssPageSize' => 'true',
                'printBackground' => 'true',
                'marginTop' => '0',
                'marginBottom' => '0',
                'marginLeft' => '0',
                'marginRight' => '0',
                // Las fuentes van incrustadas, pero el layout todavía se asienta:
                // sin esta espera el primer bloque a veces se corta a media hoja.
                'waitDelay' => '0.4s',
            ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException(
                'Gotenberg respondió '.$respuesta->status().' al convertir el documento.',
            );
        }

        $pdf = $respuesta->body();

        if (! str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('Gotenberg no devolvió un archivo PDF válido.');
        }

        return $pdf;
    }

    /**
     * Una carpeta por ruta y un nombre estable dentro.
     *
     * Volver a renderizar la misma ruta pisa su archivo en vez de dejar copias
     * numeradas: el snapshot es el mismo, así que el PDF también.
     */
    private function path(LearningRoute $route): string
    {
        return 'documentos/'.$route->id.'/'.$route->documentType->slug.'.pdf';
    }

    private function disk(): string
    {
        return (string) config('documents.disk');
    }
}
