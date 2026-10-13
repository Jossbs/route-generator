<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration\Data;

use App\Models\Lead;
use App\Services\Leads\AreaNormalizer;
use App\Services\Leads\CapturedArea;
use App\Services\Leads\MexicanPhone;

/**
 * Lo que el asesor captura de un lead.
 *
 * Seis campos, y tres de ellos no son datos de contacto sino la materia prima de
 * la ruta: el área —una de catorce, tipificada—, la ocupación —«de aquí salen los
 * cursos que te voy a proponer»— y lo que el lead dijo —«lo uso para escoger
 * entre cursos parecidos»—. Sin ellos el generador propone lo mismo para
 * cualquiera y el documento deja de demostrar trato personalizado, que es de lo
 * único que vive el gancho.
 *
 * El área llega ya resuelta: la pantalla manda lo que el asesor eligió y, si
 * eligió «Otro», lo que escribió; `AreaNormalizer` decide de una vez si eso es
 * una de las catorce o un área nueva, y nadie más vuelve a interpretarlo.
 *
 * El teléfono es opcional en el gancho. No siempre lo hay —el asesor puede estar
 * armando la ruta antes de pedirlo— y exigirlo obligaría a inventar uno. Lo que
 * cuesta es el reencuentro: sin teléfono, el mismo lead vuelve a darse de alta
 * cuando compre. Por eso la ruta final sí lo exige.
 *
 * El embudo es el cúmulo de cursos de la rama por la que preguntó. De
 * ahí sale la ruta principal del documento, y de sus cursos se deriva la
 * especialidad que lo abre. El asesor elige un embudo, no una especialidad
 * suelta: la estrategia es enseñarle al lead una rama armada, no el catálogo.
 *
 * `externalId` es el identificador en Kommo. Viaja desde el primer día aunque la
 * integración no exista: el formulario acepta parámetros por URL, así que el día
 * que el CRM mande al asesor aquí con el lead en la mano, no hay nada que
 * cambiar.
 *
 * `leadId` es el lead que ya existe cuando la ruta final se arma desde su gancho
 * —la bandeja o la ficha—. Con él la final cae sobre el mismo lead aunque el
 * gancho se haya capturado sin teléfono o con el nombre corto, que es justo el
 * caso en el que el teléfono no alcanzaba para reencontrarlo.
 */
final readonly class LeadCapture
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $occupation,
        public ?string $context,
        public int $funnelId,
        public ?string $externalId = null,
        public CapturedArea $area = new CapturedArea,
        public ?int $leadId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            phone: MexicanPhone::normalize($data['phone'] ?? null),
            occupation: $data['occupation'] ?? null,
            context: $data['context'] ?? null,
            funnelId: (int) $data['funnel_id'],
            externalId: $data['external_id'] ?? null,
            area: (new AreaNormalizer(
                fn (): iterable => Lead::query()->whereNotNull('area_other')->distinct()->pluck('area_other'),
            ))->resolve($data['area'] ?? null, $data['area_other'] ?? null),
            leadId: isset($data['lead_id']) ? (int) $data['lead_id'] : null,
        );
    }
}
