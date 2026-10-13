<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\RuleSeverity;
use App\Models\BannedPhrase;
use App\Models\Course;
use App\Models\Specialization;
use App\Models\User;
use App\Services\Catalog\RouteTotals;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * El catálogo completo en PDF, para que el asesor se lo mande a cualquier lead.
 *
 * No es una ruta: no recomienda nada, no tiene lead ni snapshot, y por eso no pasa
 * por `learning_routes`. Se arma del catálogo vigente cada vez, con los mismos
 * totales netos que una ruta —64 cursos, 55 programas— y la misma plantilla del
 * inventario del anual, así que cada especialización sale bajo su propio título y
 * cada nivel absorbido cuelga de su paquete.
 *
 * El archivo se guarda con el hash de su HTML. Mientras no cambien el catálogo, el
 * copy ni el WhatsApp del asesor, la descarga sirve el PDF ya hecho sin volver a
 * Chromium; el primero de cada combinación tarda lo que tarda Gotenberg, un par de
 * segundos. Por eso no va en cola como las rutas: no hay nada que el asesor
 * espere después de que el archivo baja.
 */
final class CatalogDocument
{
    public function __construct(
        private readonly CopyResolver $copy,
        private readonly WhatsAppLink $whatsapp,
        private readonly InventoryLayout $inventario,
        private readonly DocumentHtml $html,
        private readonly GotenbergRenderer $gotenberg,
    ) {}

    /** La ruta del PDF en el disco de documentos, hecho si todavía no existía. */
    public function pdf(?User $asesor): string
    {
        $html = $this->html->inline($this->view($asesor)->render());
        $carpeta = 'catalogo/'.($asesor->id ?? 'sin-asesor');
        $ruta = $carpeta.'/'.sha1($html).'.pdf';
        $disco = Storage::disk((string) config('documents.disk'));

        if ($disco->exists($ruta)) {
            return $ruta;
        }

        $this->scan($html);

        // La portada lleva la fecha, así que el archivo cambia a diario. El de
        // ayer ya no se vuelve a servir: se va para no acumular uno por día.
        if (! $disco->put($ruta, $this->gotenberg->convert($html))) {
            throw new RuntimeException('No se pudo guardar el PDF del catálogo.');
        }

        $disco->delete(array_values(array_diff($disco->files($carpeta), [$ruta])));

        return $ruta;
    }

    public function view(?User $asesor): View
    {
        $grupos = $this->groups();
        $hojas = $this->inventario->paginate($this->inventario->nest($grupos));
        $cursos = Course::query()->where('is_active', true)->get();
        $totales = RouteTotals::for($cursos);
        $numero = $asesor?->whatsapp;

        return view('documentos.catalogo', [
            'copy' => $this->copy,
            'titulo' => 'Catálogo de cursos · DP SyS',
            'fecha' => now()->settings(['locale' => 'es'])->translatedFormat('j \d\e F \d\e Y'),
            // Solo el número: el botón lleva a quien lo descargó, pero el
            // documento firma como empresa, sin nombre de asesor.
            'asesor' => blank($numero) ? null : ['whatsapp' => $numero],
            'enlace' => blank($numero) ? null : $this->whatsapp->for(
                $numero,
                $this->copy->body('catalogo.mensaje-whatsapp'),
            ),
            'catalogo' => [
                'especialidades' => count($grupos),
                'cursos' => $totales->courseCount,
                'programas' => $totales->contentUnitCount,
                'horas' => $totales->netHours,
            ],
            'especialidades' => $grupos,
            'membresia' => $this->membership($totales->courseCount),
            'hojas' => $hojas,
            // Portada + el inventario + el cierre.
            'paginas' => count($hojas) + 2,
        ]);
    }

    /**
     * Una especialización por grupo, en el orden de presentación del catálogo y
     * con los cursos por nombre, igual que el inventario del anual.
     *
     * @return list<array<string, mixed>>
     */
    private function groups(): array
    {
        $cursos = Course::query()->where('is_active', true)->get();
        $absorbidos = array_flip(RouteTotals::for($cursos)->absorbedCourseIds);
        $grupos = [];

        foreach (Specialization::query()->orderBy('display_order')->get() as $especialidad) {
            $propios = $cursos
                ->where('specialization_id', $especialidad->id)
                ->sortBy('name')
                ->values();

            if ($propios->isEmpty()) {
                continue;
            }

            $filas = $propios->map(fn (Course $curso): array => [
                'nombre' => $curso->name,
                'slug' => $curso->slug,
                'horas' => $curso->duration_hours,
                'nivel' => $curso->level->value,
                'unidad' => $curso->content_unit_id,
                'horas_absorbidas' => isset($absorbidos[(int) $curso->id]),
            ])->all();

            // Lo absorbido no suma: sus horas ya están dentro de las del paquete
            // (MODULE_DOUBLE_COUNT).
            $contables = array_filter($filas, fn (array $fila): bool => ! $fila['horas_absorbidas']);

            $grupos[] = [
                'especialidad' => $especialidad->name,
                'cursos' => $filas,
                'total_cursos' => count($filas),
                'programas' => count($contables),
                'horas' => array_sum(array_map(fn (array $fila): int => (int) $fila['horas'], $contables)),
            ];
        }

        return $grupos;
    }

    /**
     * El fraseo, escaneado sobre el texto que de verdad se va a imprimir.
     *
     * Las rutas pasan por el validador; el catálogo no tiene borrador que
     * validar, pero imprime copy y nombres de curso delante de un lead, y las
     * frases prohibidas (equivalencias de certificación, precios, «lecciones»)
     * lo son igual aquí. Una frase bloqueante detiene el render.
     */
    private function scan(string $html): void
    {
        // Sin la hoja de estilos ni las fuentes incrustadas: ahí no hay nada que
        // el lead lea y sí mucho texto que podría coincidir por azar.
        $visible = (string) preg_replace('#<(style|script)\b.*?</\1>#is', ' ', $html);
        $texto = html_entity_decode(strip_tags($visible), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $frases = BannedPhrase::query()
            ->active()
            ->get()
            ->filter(fn (BannedPhrase $frase): bool => $frase->severity === RuleSeverity::Blocking);

        foreach ($frases as $frase) {
            if (preg_match($frase->pattern, $texto, $coincidencia) === 1) {
                throw new RuntimeException(
                    'El catálogo imprime «'.$coincidencia[0].'», que es una frase prohibida: '.$frase->reason,
                );
            }
        }
    }

    /**
     * Las tarjetas de la membresía en la portada: título y texto, partidos por
     * el primer salto de renglón. Una tarjeta sin escribir no sale.
     *
     * @return list<array{titulo: string, texto: string}>
     */
    private function membership(int $cursos): array
    {
        $tarjetas = [];

        foreach (['acceso', 'modalidad', 'dc3', 'diploma'] as $clave) {
            $cuerpo = $this->copy->body('catalogo.membresia.'.$clave, ['cursos' => (string) $cursos]);

            if ($cuerpo === null) {
                continue;
            }

            [$titulo, $texto] = array_pad(explode("\n", $cuerpo, 2), 2, '');
            $tarjetas[] = ['titulo' => trim($titulo), 'texto' => trim($texto)];
        }

        return $tarjetas;
    }
}
