<?php

declare(strict_types=1);

use App\Enums\RuleSeverity;
use App\Models\BannedPhrase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * El catálogo de cursos en PDF, decidido el 2026-09-25.
 *
 * El asesor lo descarga desde la barra y se lo manda a cualquier lead. No es una
 * ruta: no recomienda, no deja renglón en la bandeja. Pero imprime el catálogo
 * delante de un lead, así que le aplican las mismas restricciones de lo que se
 * cuenta y de lo que se dice: 64 cursos con la contención de los paquetes, horas y
 * no lecciones, ningún precio y ninguna frase prohibida.
 *
 * Como en `PdfEntregableTest`, se finge la respuesta de Gotenberg y se revisa el
 * HTML que se le manda.
 */

function asesorConWhatsApp(?string $numero = '8991234567'): User
{
    return User::factory()->create(['name' => 'Laura Méndez', 'whatsapp' => $numero]);
}

/** El HTML que recibió Gotenberg, ya sin fuentes incrustadas. */
function htmlEnviado(): string
{
    $html = '';

    Http::assertSent(function ($request) use (&$html): bool {
        $html = $request->body();

        return true;
    });

    return (string) preg_replace('#<style\b.*?</style>#is', '', $html);
}

beforeEach(function () {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fingido')]);
});

it('descarga el catálogo como archivo PDF', function () {
    $respuesta = $this->actingAs(asesorConWhatsApp())->get('/catalogo/pdf');

    $respuesta->assertOk()->assertDownload('Catalogo de cursos DP SyS.pdf');
    expect($respuesta->streamedContent())->toBe('%PDF-1.7 fingido');
});

it('lista cada especialización con sus cursos y sus horas, sin sumar dos veces los niveles', function () {
    $this->actingAs(asesorConWhatsApp())->get('/catalogo/pdf')->assertOk();

    $html = htmlEnviado();

    // Las dos cifras con la contención dicha (MODULE_DOUBLE_COUNT).
    expect($html)->toContain('64 cursos · 55 programas de contenido distintos')
        ->and($html)->toContain('Gestión de Inventarios')
        ->and($html)->toContain('Microsoft Excel Completo')
        ->and($html)->toContain('incluido');

    // Cadena de Suministros conserva su propio encabezado (NO_HEADING_MERGE).
    expect($html)->toContain('Cadena de Suministros');

    // Core Tools lista sus cuatro cursos y nada la llama completa
    // (CORE_TOOLS_PARTIAL). La nota de APQP y MSA salió el 2026-09-25.
    expect(strip_tags($html))->not->toMatch('/Core Tools complet/iu');
});

it('no imprime precios ni lecciones', function () {
    $this->actingAs(asesorConWhatsApp())->get('/catalogo/pdf')->assertOk();

    $texto = strip_tags(htmlEnviado());

    expect($texto)->not->toMatch('/\$\s?\d/')
        ->and($texto)->not->toMatch('/MXN/i')
        ->and($texto)->not->toMatch('/lecci[oó]n/iu');
});

it('cierra con el WhatsApp de quien lo descarga', function () {
    $this->actingAs(asesorConWhatsApp('8991234567'))->get('/catalogo/pdf')->assertOk();

    // El botón lleva al asesor, pero la firma es de la empresa.
    expect(htmlEnviado())->toContain('wa.me/528991234567')
        ->and(htmlEnviado())->not->toContain('Laura Méndez')
        ->and(htmlEnviado())->toContain('contacto@dpsys.com.mx');
});

it('sin número en el perfil no pinta el botón', function () {
    $this->actingAs(asesorConWhatsApp(null))->get('/catalogo/pdf')->assertOk();

    expect(htmlEnviado())->not->toContain('wa.me');
});

it('sirve el archivo ya hecho mientras nada cambie', function () {
    $asesor = asesorConWhatsApp();

    $this->actingAs($asesor)->get('/catalogo/pdf')->assertOk();
    $this->actingAs($asesor)->get('/catalogo/pdf')->assertOk();

    Http::assertSentCount(1);
});

it('una frase prohibida detiene el render', function () {
    BannedPhrase::query()->create([
        'pattern' => '/Gestión de Inventarios/u',
        'scope' => 'prueba',
        'reason' => 'Frase de prueba.',
        'severity' => RuleSeverity::Blocking,
        'is_active' => true,
    ]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs(asesorConWhatsApp())->get('/catalogo/pdf'))
        ->toThrow(RuntimeException::class, 'Gestión de Inventarios');

    Http::assertNothingSent();
});

it('la portada dice especialidades, cursos, horas y lo que trae la membresía, sin precio', function () {
    $this->actingAs(asesorConWhatsApp())->get('/catalogo/pdf')->assertOk();

    $html = htmlEnviado();
    $portada = substr($html, 0, (int) strpos($html, 'class="hoja anual"'));

    expect($portada)->toContain('Especialidades')
        ->and($portada)->toContain('55 programas distintos')
        ->and($portada)->toContain('Cadena de Suministros')
        ->and($portada)->toContain('1&nbsp;curso')
        ->and($portada)->toContain('Los 64 cursos durante 12 meses')
        ->and($portada)->toContain('Constancia DC-3')
        ->and(strip_tags($portada))->not->toMatch('/\$\s?\d|MXN/i');
});
