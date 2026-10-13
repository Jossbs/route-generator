<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Enums\LeadArea;
use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Lo que la ocupación y el contexto dicen del lead.
 *
 * La carcasa promete esto en la ayuda del campo: «¿A qué se dedica?» → *de aquí
 * salen los cursos que te voy a proponer*, y «¿Te dijo algo más?» → *lo uso para
 * escoger entre cursos parecidos*. Sin leer esos dos campos, el generador propone
 * lo mismo para un ingeniero de una automotriz que para una coordinadora de
 * calidad de un laboratorio, y el documento deja de demostrar trato personalizado,
 * que es de lo único que vive el gancho.
 *
 * Tres señales: el área, la industria y si el puesto manda gente.
 *
 * El área es la única que no se infiere —el asesor la elige de una lista de
 * catorce— y por eso manda sobre las otras dos cuando hay que elegir con qué se
 * acompaña la ruta principal. Las otras dos se leen del texto, que es tanto como
 * se puede inferir sin fabricar precisión: el catálogo tiene un solo arquetipo
 * sembrado.
 *
 * ### El área que no estaba en la lista
 *
 * Cuando el asesor escribe una en «Otra» y no se parece a ninguna de las catorce,
 * el área queda como texto y su preferencia no existe. Antes eso dejaba al motor
 * sin la señal más fuerte que tenía, y un lead de «Control de Gestión» recibía lo
 * mismo que uno sin área capturada. Ahora ese texto se lee junto con la ocupación
 * y el contexto contra los temas del catálogo: no reemplaza a la lista —una
 * coincidencia de palabras es más débil que una elección— pero es mejor que nada,
 * y el bloque dice de dónde salió para que el asesor pueda desecharlo.
 *
 * Esto **no es** la detección automática de arquetipo, que sigue fuera del alcance
 * de la v1: el asesor escribe la ocupación y ve la propuesta antes de que salga
 * nada. Cuando lleguen los 8 arquetipos del Excel con sus frases puente, esta
 * clase se reemplaza por `archetype_tag_affinity` sin tocar el compositor.
 */
final readonly class LeadSignals
{
    private const INDUSTRIA_AUTOMOTRIZ = '/automotriz|automotr|tier\s?[12]|arn[eé]s|ensamble|estampado/i';

    private const PUESTO_DE_MANDO = '/supervisor|jefe|jefa|coordinad|gerente|encargad|l[ií]der|superintendente/i';

    /**
     * Los temas del catálogo tal como los nombra la gente.
     *
     * Solo se consulta cuando el área capturada no es una de las catorce. El orden
     * importa: se recorre de arriba abajo y las primeras coincidencias mandan, así
     * que lo específico va antes que lo general.
     *
     * No pretende ser exhaustivo ni inventa afinidad: lo que no coincide no
     * produce candidata y decide el puesto, como antes de que el campo existiera.
     * Cuando lleguen los 8 arquetipos del Excel, esto se reemplaza por
     * `archetype_tag_affinity` sin tocar el compositor.
     *
     * @var array<string, string> Slug de especialización => lo que hay que leer para elegirla.
     */
    private const TEMAS = [
        'coretools' => '/amef|ppap|\bspc\b|plan de control|iatf|apqp/i',
        'iso' => '/\biso\b|9001|14001|45001|auditor|sistema de gesti[oó]n|certificaci[oó]n|normativ/i',
        'calidad' => '/calidad|inspecci[oó]n|metrolog|laboratorio|no conformidad|scrap|rechazo/i',
        'sixsigma' => '/six\s?sigma|seis sigma|dmaic|estad[ií]stic|variabilidad|capacidad de proceso/i',
        'datos' => '/\bdatos\b|power\s?bi|dashboard|tablero|indicador|kpi|anal[ií]tic|reporteo|\bsql\b|sistemas|inform[aá]tic|software/i',
        'excel' => '/excel|hoja de c[aá]lculo|tablas din[aá]micas|contab|finanz|factur|n[oó]mina|presupuest|costos|administrativ/i',
        'lean' => '/lean|desperdicio|\b5s\b|smed|kanban|\btpm\b|flujo|producci[oó]n|manufactura|planta|almac[eé]n|inventario|log[ií]stic/i',
        'problemas' => '/problema|causa ra[ií]z|\b8d\b|ishikawa|falla|paro|reclamaci[oó]n|proyecto/i',
        'soft' => '/liderazgo|comunicaci[oó]n|equipo|personal|talento|capacitaci[oó]n|recursos humanos|venta|cliente|negociaci[oó]n|empleo|entrevista/i',
        'kaizen' => '/kaizen|mejora continua|productividad|eficiencia|hoshin/i',
    ];

    private function __construct(
        public string $occupation,
        public string $context,
        public ?LeadArea $area = null,
        /** El área escrita a mano, cuando no era ninguna de las catorce. */
        public string $areaOther = '',
    ) {}

    public static function of(Lead $lead): self
    {
        return new self(
            occupation: (string) ($lead->occupation ?? ''),
            context: (string) ($lead->context ?? ''),
            area: $lead->area,
            areaOther: (string) ($lead->area_other ?? ''),
        );
    }

    public static function from(
        ?string $occupation,
        ?string $context,
        ?LeadArea $area = null,
        ?string $areaOther = null,
    ): self {
        return new self((string) $occupation, (string) $context, $area, (string) $areaOther);
    }

    /**
     * Las especializaciones que acompañan mejor a su área, en orden de preferencia.
     *
     * Con una de las catorce, la lista es la que el área declara. Con un área
     * escrita a mano, se leen los temas que aparezcan en lo que el asesor capturó.
     * Vacío solo cuando no hay ni área ni nada que leer: entonces deciden la
     * industria y el puesto, como antes de que el campo existiera.
     *
     * @return list<string>
     */
    public function preferredSpecializations(): array
    {
        if ($this->area instanceof LeadArea) {
            return $this->area->specializationSlugs();
        }

        return $this->areaOther === '' ? [] : $this->topics();
    }

    /**
     * Los temas que aparecen en lo que el asesor capturó, en el orden del mapa.
     *
     * @return list<string>
     */
    private function topics(): array
    {
        $texto = $this->areaOther.' '.$this->everything();

        return array_keys(array_filter(
            self::TEMAS,
            fn (string $patron): bool => Str::isMatch($patron, $texto),
        ));
    }

    /** La industria se busca en los dos campos: a veces sale en lo que dijo. */
    public function isAutomotive(): bool
    {
        return Str::isMatch(self::INDUSTRIA_AUTOMOTRIZ, $this->everything());
    }

    /**
     * ¿Manda gente? El área lo dice cuando es Dirección; en las demás lo decide la
     * ocupación. El contexto no se mira: habla de a dónde quiere llegar, y querer
     * liderar no es lo mismo que liderar ya.
     */
    public function leadsPeople(): bool
    {
        return ($this->area?->leadsPeople() ?? false)
            || Str::isMatch(self::PUESTO_DE_MANDO, $this->occupation);
    }

    /**
     * Cómo se nombra su área en la justificación que lee el asesor.
     *
     * El área escrita a mano también se nombra: es lo que le permite reconocer de
     * dónde salió la recomendación y desecharla si el texto lo despistó.
     */
    public function areaLabel(): ?string
    {
        return $this->area?->label() ?? ($this->areaOther === '' ? null : $this->areaOther);
    }

    public function hasContext(): bool
    {
        return trim($this->context) !== '';
    }

    private function everything(): string
    {
        return $this->occupation.' '.$this->context;
    }
}
