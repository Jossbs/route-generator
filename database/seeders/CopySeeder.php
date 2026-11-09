<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BannedPhrase;
use App\Models\CopyBlock;
use App\Services\Documents\InitialRouteScriptCopy;
use Illuminate\Database\Seeder;

/**
 * Dos mitades distintas.
 *
 * `copy_blocks` siembra el texto del documento en estado `borrador`: propuesta
 * redactada, no aprobada. Es el mismo criterio que ya se usó con las 64 frases de
 * resultado —texto real que Contenido todavía tiene que revisar— y no el de
 * inventar contenido que docs/ARQUITECTURA.md §6 prohíbe: cada bloque declara en `claims` los
 * hechos que afirma, y todos salen de §6 «Resuelto» o de cifras calculadas.
 *
 * Un bloque que Contenido ya marcó `aprobado` no se toca al resembrar. Lo que el
 * área aprobó manda sobre lo que este archivo propone.
 *
 * `banned_phrases` sí se siembra completa: no es copy, son las restricciones 4 y 5
 * de docs/ARQUITECTURA.md expresadas como patrones que el validador escanea sobre el texto
 * ensamblado antes de renderizar. El copy de aquí pasa por ese mismo escaneo.
 */
class CopySeeder extends Seeder
{
    public const SCOPE_CERTIFICACION = 'certificacion';

    public const SCOPE_PRECIO = 'precio';

    /**
     * Léxico del documento. No es una promesa falsa como las otras dos: es una
     * palabra que el sistema no usa. El conteo de lecciones se contradice entre
     * el sitio y las fichas, así que el documento cuantifica horas.
     */
    public const SCOPE_LEXICO = 'lexico';

    public function run(): void
    {
        $this->seedCopySlots();
        $this->seedBannedPhrases();
    }

    private function seedCopySlots(): void
    {
        $this->pruneRetiredSlots();

        foreach ($this->copySlots() as $slot) {
            $block = CopyBlock::query()->where('slug', $slot['slug'])->first();

            // Lo que Contenido aprobó manda. Resembrar no puede pisarlo.
            if ($block?->status === 'aprobado') {
                continue;
            }

            CopyBlock::updateOrCreate(
                ['slug' => $slot['slug']],
                [
                    'scope' => $slot['scope'],
                    'locale' => 'es_MX',
                    'body' => $slot['body'],
                    'claims' => $slot['claims'],
                    'applies_when' => $slot['applies_when'],
                    'status' => $slot['status'] ?? 'borrador',
                ],
            );
        }
    }

    /**
     * Retira los bloques que el documento dejó de imprimir.
     *
     * Un bloque que ya no sale en ninguna plantilla sigue pesando: lo escanea el
     * validador y lo lee quien revisa el copy, creyendo que va impreso. Lo que
     * Contenido aprobó no se toca, aunque haya dejado de usarse: borrar trabajo
     * aprobado no es tarea de un seeder.
     */
    private function pruneRetiredSlots(): void
    {
        CopyBlock::query()
            ->whereNotIn('slug', array_column($this->copySlots(), 'slug'))
            ->where('status', '!=', 'aprobado')
            ->delete();
    }

    /**
     * El texto del documento.
     *
     * Persuade con lo que se puede sostener si el lead lo revisa: la constancia
     * DC-3 oficial ante la STPS, el acceso autogestivo 24/7, y las cifras del
     * catálogo con su contención dicha. Ni un precio, ni una comparación, ni una
     * equivalencia de acreditación: eso lo maneja el asesor en la conversación,
     * donde puede atender objeciones.
     *
     * @return list<array<string, mixed>>
     */
    private function copySlots(): array
    {
        return [
            [
                'slug' => 'portada.ruta-enfocada',
                'scope' => 'portada',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'El orden en que te conviene empezar, armado con lo que nos contaste.',
                'claims' => ['La ruta enfocada es un subconjunto del catálogo, elegido a partir de la captura del asesor.'],
            ],
            [
                'slug' => 'portada.ruta-anual',
                'scope' => 'portada',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => 'Un año ordenado para que cada mes se apoye en el anterior. El orden lo recomendamos nosotros; el ritmo lo pones tú.',
                'claims' => ['Los cursos son autogestivos: el itinerario es recomendación de secuencia, no calendario (docs/ARQUITECTURA.md §6).'],
            ],
            [
                // La frase que vuelve la ruta suya y no una plantilla. Los
                // marcadores los sustituye CopyResolver con lo que el asesor
                // capturó y con la especialización que el motor eligió: nada que
                // inventar. La ruta inicial no necesita grado ni cargo para
                // presentar el objetivo sin incongruencias.
                'slug' => 'objetivo.ruta-enfocada',
                'scope' => 'intro_especializacion',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'Empieza por {ancla}, la ruta por la que preguntaste, y avanza con cursos de refuerzo elegidos según lo que nos compartiste. Cada curso cierra con su constancia oficial.',
                'claims' => [
                    'La ruta es la que consultó el lead; los refuerzos se eligen con su ocupación y objetivo capturados.',
                ],
            ],
            [
                'slug' => 'objetivo.ruta-anual',
                'scope' => 'portada',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => 'Para tu puesto de {ocupacion}: arrancas en {ancla}, por donde empezaste, y de ahí avanzas al resto del plan.',
                'claims' => ['El mes 1 arranca con la especialización ancla que el lead pidió (docs/ARQUITECTURA.md §8).'],
            ],
            [
                // El dato que el lead necesita para decidir: cuánto le va a tomar.
                // «Lo» y no «la»: el bloque va debajo de las horas de contenido, que
                // en el gancho son las de su ruta y en el anual las de todo el
                // catálogo. El mismo texto sirve a los dos.
                // La duración se calcula de las horas netas de su propia ruta, no
                // se teclea, y el ritmo de referencia va dicho para que la cifra
                // sea verificable en vez de una promesa.
                'slug' => 'ritmo.estudio',
                'scope' => 'cierre',
                'applies_when' => null,
                'body' => 'A {horas_semana} horas por semana, la terminas en {duracion}.',
                'claims' => [
                    'Los cursos son autogestivos con acceso 24/7 (docs/ARQUITECTURA.md §6).',
                    'La duración se calcula de las horas netas de la ruta al ritmo de referencia; no es un compromiso de fechas.',
                ],
            ],
            [
                // Core Tools publica cuatro de las cinco herramientas AIAG. La
                // nota nombra las cuatro y dice las dos que faltan: es la única
                // forma de que el documento no prometa nunca una completa
                // (CORE_TOOLS_PARTIAL). Qué lleva la ruta y qué no se enumera
                // desde el snapshot, al lado de este texto.
                'slug' => 'nota.core-tools',
                'scope' => 'cierre',
                'applies_when' => null,
                'body' => 'Core Tools en DP SyS son cuatro programas: AMEF, PPAP, SPC y Plan de Control. No abarca APQP ni MSA, dos de las cinco herramientas AIAG.',
                'claims' => ['Core Tools publica 4 de las 5 herramientas AIAG; faltan APQP y MSA (docs/ARQUITECTURA.md §4.4).'],
            ],
            [
                'slug' => 'cta.boton-whatsapp',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'Conocer certificaciones y pagos',
                'claims' => ['Etiqueta del enlace de vuelta al asesor. El canal es WhatsApp (docs/ARQUITECTURA.md §1).'],
            ],
            [
                'slug' => 'cta.mensaje-whatsapp',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'Hola, soy {nombre}. Ya vi mi ruta principal personalizada. Quiero conocer las certificaciones y las facilidades de pago para empezar.',
                'claims' => ['Mensaje que el lead envía al asesor. No afirma nada del producto.'],
            ],
            [
                // El botón de compra que el documento sí puede llevar: abre la
                // conversación, no imprime el precio. El precio lo dice el asesor,
                // donde puede atender objeciones (docs/ARQUITECTURA.md §4.5).
                'slug' => 'cta.boton-membresia',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'Quiero activar mi membresía',
                'claims' => ['Etiqueta del enlace de intención de compra. No declara precio ni vigencia.'],
            ],
            [
                'slug' => 'cta.mensaje-membresia',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'Hola, soy {nombre}. Vi mi ruta formativa y quiero activar mi membresía.',
                'claims' => ['Mensaje que el lead envía al asesor. No afirma nada del producto.'],
            ],
            [
                'slug' => 'intro-especializacion.generico',
                'scope' => 'intro_especializacion',
                'applies_when' => null,
                'body' => 'Empieza por el curso marcado: es el que responde a lo que buscas. Lo que sigue es lo que más lo refuerza.',
                'claims' => [
                    'La ruta enfocada arranca con el primer curso del embudo o de la especialización ancla (docs/ARQUITECTURA.md §8).',
                ],
            ],
            [
                'slug' => 'cierre.ruta-anual',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => 'Es una recomendación flexible: tu acceso es a todo el catálogo durante la vigencia.',
                'claims' => [
                    'La membresía anual da acceso completo al catálogo durante la vigencia (docs/ARQUITECTURA.md §1).',
                    'Los cursos son autogestivos, con acceso 24/7 (docs/ARQUITECTURA.md §6).',
                ],
            ],
            [
                'slug' => 'credencial.dc3',
                'scope' => 'cierre',
                'applies_when' => null,
                'body' => 'Cada curso termina con constancia DC-3 oficial ante la STPS y diploma verificable. Sirve en tu expediente, no solo dentro de la plataforma.',
                'claims' => [
                    'La credencial es constancia DC-3 oficial ante la STPS más diploma verificable (docs/ARQUITECTURA.md §6, publicado por DP SyS).',
                    'No afirma equivalencia con ninguna certificación de mercado.',
                ],
            ],
            [
                'slug' => 'membresia.alcance',
                'scope' => 'cierre',
                'applies_when' => null,
                'body' => 'Esta ruta es solo el inicio. Tu membresía te da acceso al resto del catálogo: al terminarla, puedes aprovechar el tiempo que te quede de tus 12 meses de acceso para seguir aprendiendo y acreditando más cursos.',
                'claims' => [
                    'El catálogo son 12 especializaciones con acceso completo por suscripción anual (docs/ARQUITECTURA.md §1).',
                    'Al cerrar la membresía el lead recibe la ruta anual de 12 meses; lo mismo dicen el guión del audio y el mensaje escrito (docs/ARQUITECTURA.md §5).',
                    'Las cifras impresas junto a este bloque se calculan del catálogo, no se teclean.',
                ],
            ],
            [
                'slug' => 'intro-inventario.ruta-anual',
                'scope' => 'intro_especializacion',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => 'Tu ruta usa una parte del catálogo. Esto es todo lo que tienes abierto durante tu vigencia.',
                'claims' => ['Contención de los tres paquetes «Completo» (docs/ARQUITECTURA.md §4.2).'],
            ],
            [
                'slug' => 'conteo.contencion',
                'scope' => 'cierre',
                'applies_when' => null,
                'body' => 'Los paquetes Completo de Excel, AutoCAD y SolidWorks ya incluyen sus niveles.',
                'claims' => ['Contención de los tres paquetes «Completo»: 64 cursos equivalen a 55 programas (docs/ARQUITECTURA.md §4.2).'],
            ],
            [
                'slug' => 'siguiente-paso.ruta-enfocada',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'Da el siguiente paso hacia tu objetivo. Responde a nuestra conversación y te cuento cómo obtener tus constancias y diplomas, qué certificaciones acompañan tu ruta y qué facilidades de pago tienes para empezar. Resolvemos tus dudas y elegimos juntos la opción que mejor se adapte a ti.',
                'claims' => [
                    'El canal de vuelta es WhatsApp (docs/ARQUITECTURA.md §1).',
                    'Menciona facilidades de pago sin cifra, igual que el guión del audio: el precio lo dice el asesor (docs/ARQUITECTURA.md §4.5).',
                ],
            ],
            [
                'slug' => 'frase-puente.general',
                'scope' => 'frase_puente',
                'applies_when' => ['archetype' => 'general'],
                'body' => 'Lo que separa un currículum de una entrevista es poder demostrar lo que sabes. Esta ruta te deja con qué.',
                'claims' => ['Arquetipo general: el clúster de empleabilidad es el puente de persuasión del catálogo (docs/ARQUITECTURA.md §4).'],
            ],
            [
                // Lo que el lead contó, devuelto con sus palabras, y el curso por el
                // que arranca: el mismo que nombra el guión del audio, para que lo
                // que oye y lo que lee digan lo mismo. Sin contexto capturado no se
                // imprime (docs/ARQUITECTURA.md §6).
                'slug' => 'porque.con-contexto',
                'scope' => 'portada',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'Nos dijiste: «{contexto}». Por eso tu ruta arranca con {primer_curso}.',
                'claims' => [
                    'El contexto es lo que el asesor capturó de la conversación.',
                    'El primer curso es el primero del itinerario, en el orden del embudo.',
                ],
            ],
            [
                'slug' => 'saber.modalidad',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-enfocada'],
                'body' => 'Estudias en línea y a tu ritmo: acceso 24/7, clases en video con instructores y material de apoyo para reforzar todo aprendizaje.',
                'claims' => [
                    'Cursos autogestivos con acceso 24/7 (docs/ARQUITECTURA.md §6).',
                    'Clases en video y material de apoyo confirmados por el usuario el 2026-11-08.',
                ],
            ],
            [
                'slug' => 'uso.paso-1',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => 'Entra a academy.dpsys.com.mx con tu cuenta.',
                'claims' => ['La plataforma es academy.dpsys.com.mx; el sitio publica «Iniciar sesión» (consultado el 2026-09-24).'],
            ],
            [
                'slug' => 'uso.paso-2',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => 'Abre tu ruta en la hoja 2 y empieza por el Mes 1: {primer_curso}.',
                'claims' => ['El primer curso es el primero del itinerario anual, que arranca con la especialización ancla (docs/ARQUITECTURA.md §8).'],
            ],
            [
                'slug' => 'uso.paso-3',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => 'Al terminar cada curso recibes tu constancia DC-3 y tu diploma verificable.',
                'claims' => ['Constancia DC-3 oficial ante la STPS más diploma verificable (docs/ARQUITECTURA.md §6).'],
            ],
            [
                'slug' => 'asesor.contacto-anual',
                'scope' => 'cierre',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => 'Si quieres cambiar el orden o tienes dudas con la plataforma, escríbeme.',
                'claims' => ['Contacto del asesor. El documento anual no lleva llamada a la venta (decidido el 2026-09-24).'],
            ],
            [
                'slug' => 'inventario.leyenda',
                'scope' => 'intro_especializacion',
                'applies_when' => ['document_type' => 'ruta-anual'],
                'body' => '● en tu plan · ○ disponible · ↳ ya incluido en su curso Completo.',
                'claims' => ['Contención de los tres paquetes «Completo» (docs/ARQUITECTURA.md §4.2).'],
            ],
            ...$this->catalogSlots(),
            ...$this->socialProofSlots(),
            ...InitialRouteScriptCopy::slots(),
        ];
    }

    /**
     * El catálogo en PDF que el asesor descarga desde la barra, decidido el
     * 2026-09-25. No es una ruta ni un tipo de documento: es la lista completa con
     * sus horas, igual para todo lead, y un cierre con el WhatsApp de quien lo
     * bajó.
     *
     * `applies_when.document_type = catalogo` no nombra una fila de
     * `document_types`, y es a propósito: así el escaneo de las rutas no lee este
     * texto como suyo. El catálogo lo escanea por su cuenta antes de renderizar.
     *
     * @return list<array<string, mixed>>
     */
    private function catalogSlots(): array
    {
        $catalogo = ['document_type' => 'catalogo'];

        return [
            [
                'slug' => 'catalogo.portada',
                'scope' => 'portada',
                'applies_when' => $catalogo,
                'body' => 'Todo lo que abre la membresía anual, especialización por especialización, con las horas de cada curso.',
                'claims' => [
                    'La membresía anual da acceso completo al catálogo (docs/ARQUITECTURA.md §1).',
                    'Las horas son las que publica cada curso en academy.dpsys.com.mx (docs/ARQUITECTURA.md §6).',
                ],
            ],
            // La franja de la portada: lo que trae la membresía, en cuatro
            // tarjetas. Título en el primer renglón y texto en el segundo, la
            // misma convención que las reseñas. Ni precio ni plazo de pago.
            [
                'slug' => 'catalogo.membresia.acceso',
                'scope' => 'portada',
                'applies_when' => $catalogo,
                'body' => "Acceso a todo\nLos {cursos} cursos durante 12 meses",
                'claims' => ['La membresía es una suscripción anual de acceso completo al catálogo (docs/ARQUITECTURA.md §1). La cifra se calcula del catálogo.'],
            ],
            [
                'slug' => 'catalogo.membresia.modalidad',
                'scope' => 'portada',
                'applies_when' => $catalogo,
                'body' => "En línea 24/7\nCursos autogestivos, a tu ritmo",
                'claims' => ['Cursos autogestivos con acceso 24/7 (docs/ARQUITECTURA.md §6).'],
            ],
            [
                'slug' => 'catalogo.membresia.dc3',
                'scope' => 'portada',
                'applies_when' => $catalogo,
                'body' => "Constancia DC-3\nOficial ante la STPS en cada curso",
                'claims' => ['Constancia DC-3 oficial ante la STPS (docs/ARQUITECTURA.md §6, publicado por DP SyS).'],
            ],
            [
                'slug' => 'catalogo.membresia.diploma',
                'scope' => 'portada',
                'applies_when' => $catalogo,
                'body' => "Diploma verificable\nAl terminar cada curso",
                'claims' => ['Diploma verificable además de la DC-3 (docs/ARQUITECTURA.md §6).'],
            ],
            [
                'slug' => 'catalogo.intro',
                'scope' => 'intro_especializacion',
                'applies_when' => $catalogo,
                'body' => 'Cada curso con sus horas de contenido. Con la membresía tienes abiertos todos, en el orden que tú elijas.',
                'claims' => [
                    'Acceso completo al catálogo por suscripción anual (docs/ARQUITECTURA.md §1).',
                    'Cursos autogestivos: el orden lo decide el miembro (docs/ARQUITECTURA.md §6).',
                ],
            ],
            [
                'slug' => 'catalogo.leyenda',
                'scope' => 'intro_especializacion',
                'applies_when' => $catalogo,
                'body' => '↳ ya incluido en su curso Completo.',
                'claims' => ['Contención de los tres paquetes «Completo» (docs/ARQUITECTURA.md §4.2).'],
            ],
            [
                'slug' => 'catalogo.siguiente-paso',
                'scope' => 'cierre',
                'applies_when' => $catalogo,
                'body' => '¿No sabes por dónde empezar? Escríbeme y te armo una ruta con los cursos que van con tu puesto.',
                'claims' => [
                    'El asesor genera la ruta inicial a partir del puesto del lead (docs/ARQUITECTURA.md §1 y §8).',
                    'No menciona precio: lo dice el asesor en la conversación (docs/ARQUITECTURA.md §4.5).',
                ],
            ],
            [
                'slug' => 'catalogo.contacto',
                'scope' => 'cierre',
                'applies_when' => $catalogo,
                'body' => 'academy.dpsys.com.mx · contacto@dpsys.com.mx · WhatsApp 899 498 0054 · Reynosa, Tamaulipas',
                'claims' => ['Contacto que publica academy.dpsys.com.mx: el WhatsApp del botón flotante del sitio, el correo y la ciudad, los mismos del centro de confianza (2026-09-24).'],
            ],
            [
                'slug' => 'catalogo.boton-whatsapp',
                'scope' => 'cierre',
                'applies_when' => $catalogo,
                'body' => 'Escríbeme por WhatsApp',
                'claims' => ['Etiqueta del enlace de vuelta al asesor. El canal es WhatsApp (docs/ARQUITECTURA.md §1).'],
            ],
            [
                'slug' => 'catalogo.mensaje-whatsapp',
                'scope' => 'cierre',
                'applies_when' => $catalogo,
                'body' => 'Hola, vi el catálogo de cursos de DP SyS y quiero que me ayudes a elegir por dónde empezar.',
                'claims' => ['Mensaje que el lead envía al asesor. No afirma nada del producto.'],
            ],
        ];
    }

    /**
     * La hoja «Quiénes somos» de la ruta inicial, decidida el 2026-09-24.
     *
     * Todo es lo que publica academy.dpsys.com.mx, consultado ese día: las cifras
     * de la portada del sitio, la franja «Empresas que confían en SyS», las fotos
     * de capacitación presencial que acompañan a cada empresa y las reseñas de
     * Google tal como el sitio las muestra. Nada se redacta de más.
     *
     * Dos convenciones de formato, porque `copy_blocks` solo guarda texto:
     *
     *  - Una cifra es «valor etiqueta»: lo que va antes del primer espacio se
     *    imprime grande y lo demás es su rótulo.
     *  - Una reseña lleva el nombre de quien la escribió en el primer renglón y el
     *    texto en el segundo. `applies_when.especialidad` la liga a la
     *    especialidad de la que habla; sin ella es general.
     *
     * Los respaldos y redes se publican con sus enlaces en config/dpsys.php.
     * La hoja distingue el alcance de cada programa; no afirma equivalencias.
     * Las fotografías de eventos fueron proporcionadas por el usuario.
     *
     * @return list<array<string, mixed>>
     */
    private function socialProofSlots(): array
    {
        $fuente = 'Publicado en academy.dpsys.com.mx, consultado el 2026-09-24.';
        $cuando = ['document_type' => 'ruta-enfocada'];

        $slots = [
            'confianza.entradilla' => [
                'Desde 2018 impulsamos el desarrollo de profesionales con formación en ingeniería. Una comunidad internacional, experiencia en empresas y respaldos que puedes consultar.',
                null,
                ['Fundación en 2018: linkedin.com/company/desarrollo-profesional-sys/. Comunidad y experiencia empresarial: academy.dpsys.com.mx/home (2026-11-08).'],
            ],
            'confianza.cifra.comunidad' => ['+80,000 ingenieros', null, ['«+80,000 ingenieros» en la portada del sitio. '.$fuente]],
            'confianza.cifra.paises' => ['15+ países', null, ['«15+ países» en la portada del sitio. '.$fuente]],
            'confianza.cifra.google' => ['2018 Fundación', null, ['Año de fundación publicado en linkedin.com/company/desarrollo-profesional-sys/. El indicador sustituye la calificación de Google que se retiró de la hoja institucional.']],
            'confianza.empresas' => [
                'Pfizer, Hospital Santander, COPARMEX, APTIV, Nidec, LG, index, C&D Technologies, Trostel, IEnova, PEMEX, AMETEK, Panasonic, Caterpillar, Baxter',
                null,
                ['Franja «Empresas que confían en SyS». Los logos viven en public/img/dpsys/empresas, nombrados por el slug de cada empresa. '.$fuente],
            ],
            'confianza.fotos' => [
                'Caterpillar, Pfizer, PEMEX',
                null,
                ['Fotos de capacitación presencial que el sitio muestra con cada empresa, en public/img/dpsys/fotos. '.$fuente],
            ],
            'confianza.pie-fotos' => ['Capacitaciones presenciales en {empresas}.', null, ['Pie de las fotos del sitio. '.$fuente]],
            'resena.general-1' => [
                "Fernanda Sánchez\nTuve una muy buena experiencia con esta empresa. El contenido de los cursos es actualizado, las clases son dinámicas y el acompañamiento durante el aprendizaje es excelente. Recomiendo ampliamente sus capacitaciones.",
                null,
                ['Reseña de Google mostrada en el sitio, textual. '.$fuente],
            ],
            'resena.general-2' => [
                "Sebastián Franco\nAcadémicos con capacidades totalmente adquiridas, que hablan con claridad y transmiten seguridad sobre la información que entregan. Me ayuda a seguir creciendo profesionalmente.",
                null,
                ['Reseña de Google mostrada en el sitio. '.$fuente],
            ],
            'resena.excel' => [
                "Gemapao\nExcelente curso, de verdad muy recomendable. Me ayudó a comprender mejor las funciones de Excel que no son tan intuitivas y cómo se pueden aplicar en un caso laboral.",
                'Microsoft Excel',
                ['Reseña de Google mostrada en el sitio; habla de Excel. '.$fuente],
            ],
            'resena.datos' => [
                "Iki Sama\nLos instructores brindan información muy útil y práctica que me ha ayudado a ampliar mis conocimientos en Power BI, SQL y Excel. Su forma de enseñar facilita el aprendizaje. Recomiendo ampliamente sus cursos.",
                'Análisis de Datos',
                ['Reseña de Google mostrada en el sitio; habla de Power BI y SQL. '.$fuente],
            ],
        ];

        return array_map(
            fn (string $slug, array $slot): array => [
                'slug' => $slug,
                'scope' => 'prueba_social',
                'applies_when' => $slot[1] === null ? $cuando : [...$cuando, 'especialidad' => $slot[1]],
                'body' => $slot[0],
                'claims' => $slot[2],
            ],
            array_keys($slots),
            $slots,
        );
    }

    private function seedBannedPhrases(): void
    {
        $replacement = 'Cuantifica horas, programas y constancias DC-3 oficiales ante la STPS. Usa fraseo de cumplimiento, no de equivalencia. El precio lo dice el asesor en la conversación.';

        $phrases = [
            // Equivalencia de acreditación (docs/ARQUITECTURA.md §4.5).
            [
                'pattern' => '/\b(equivale|equivalen|equivalente[s]?)\s+a\b/iu',
                'scope' => self::SCOPE_CERTIFICACION,
                'reason' => 'Afirma equivalencia directa de acreditación. Una comparación solo es válida si la acreditación lo es.',
            ],
            [
                'pattern' => '/\bmismo\s+valor\s+que\b/iu',
                'scope' => self::SCOPE_CERTIFICACION,
                'reason' => 'Equivalencia implícita con una certificación de mercado.',
            ],
            [
                'pattern' => '/\bsustituye\s+(a\s+)?(la\s+|una\s+|el\s+)?certificaci[oó]n\b/iu',
                'scope' => self::SCOPE_CERTIFICACION,
                'reason' => 'Promete sustitución de una certificación que DP SyS no acredita.',
            ],
            [
                'pattern' => '/\bequivalent[e]?\s+(al|a\s+la|a\s+un[a]?)\s+(green\s+belt|black\s+belt|six\s+sigma|pmp|lean\s+six\s+sigma)\b/iu',
                'scope' => self::SCOPE_CERTIFICACION,
                'reason' => 'Nombra una certificación de mercado y afirma equivalencia.',
            ],
            // Comparación de precio, lleve cifra o no (docs/ARQUITECTURA.md §4.5).
            [
                'pattern' => '/\b\d+\s*(a|-|–|hasta)?\s*\d*\s*veces\s+(m[aá]s\s+)?(barato|econ[oó]mico|accesible)/iu',
                'scope' => self::SCOPE_CERTIFICACION,
                'reason' => 'Comparación de precio contra certificaciones de mercado.',
            ],
            [
                'pattern' => '/\b(la\s+)?(mitad|tercera\s+parte|d[eé]cima\s+parte|fracci[oó]n)\s+del\s+(precio|costo|valor)\b/iu',
                'scope' => self::SCOPE_CERTIFICACION,
                'reason' => 'Comparación de precio sin equivalencia de acreditación.',
            ],
            [
                'pattern' => '/\bm[aá]s\s+(barato|econ[oó]mico)\s+que\b/iu',
                'scope' => self::SCOPE_CERTIFICACION,
                'reason' => 'Comparación de precio sin equivalencia de acreditación.',
            ],
            // El PDF no imprime precios (docs/ARQUITECTURA.md §4.5).
            [
                'pattern' => '/\$\s?\d/u',
                'scope' => self::SCOPE_PRECIO,
                'reason' => 'El documento no imprime precios: el asesor los maneja en la conversación, donde puede atender objeciones.',
            ],
            [
                'pattern' => '/\b\d{1,3}([.,]\d{3})+\s*(mxn|pesos)\b/iu',
                'scope' => self::SCOPE_PRECIO,
                'reason' => 'Ancla de precio en el documento.',
            ],
        ];

        $phrases = [...$phrases, ...$this->lexiconPhrases()];

        foreach ($phrases as $phrase) {
            BannedPhrase::updateOrCreate(
                ['pattern' => $phrase['pattern'], 'scope' => $phrase['scope']],
                [
                    'reason' => $phrase['reason'],
                    'suggested_replacement' => $phrase['suggested_replacement'] ?? $replacement,
                    'severity' => 'blocking',
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * El sistema habla de horas. «Lección» se cuela con facilidad al redactar una
     * promoción, y prometer un conteo que las fuentes contradicen es prometer un
     * dato que no se sostiene si el lead lo revisa.
     *
     * @return list<array<string, string>>
     */
    private function lexiconPhrases(): array
    {
        return [
            [
                'pattern' => '/\blecci[oó]n(es)?\b/iu',
                'scope' => self::SCOPE_LEXICO,
                'reason' => 'El documento cuantifica horas, programas y constancias DC-3. El conteo de lecciones se contradice entre fuentes y no se imprime.',
                'suggested_replacement' => 'Habla de horas. «12 lecciones» se dice «16 horas»; si hace falta la unidad menor, usa «módulos» o «temas».',
            ],
        ];
    }
}
