<?php

// Este archivo se envía por stdin al contenedor aislado de la prueba, no a producción.
declare(strict_types=1);

use App\Enums\FunnelState;
use App\Enums\RouteStatus;
use App\Jobs\RenderRouteDocument;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\LearningRoute;
use App\Models\Specialization;
use App\Models\User;
use App\Services\RouteGeneration\Data\LeadCapture;
use App\Services\RouteGeneration\RouteIssuer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_starts_with((string) getenv('COMPOSE_PROJECT_NAME'), 'route-generator-smoke-')) {
    throw new RuntimeException('Esta prueba solo puede ejecutarse en su proyecto Docker temporal.');
}

$advisor = User::create(['name' => 'Asesor de prueba', 'email' => 'smoke@example.test']);
$funnel = Funnel::create([
    'slug' => 'smoke-calidad',
    'internal_name' => 'Calidad · prueba aislada',
    'state' => FunnelState::Activo,
    'created_by' => $advisor->id,
]);
$preset = $funnel->routes()->create([
    'name' => 'Ruta de calidad',
    'position' => 1,
    'duration_months' => 12,
    'main_specialization_id' => Specialization::where('slug', 'calidad')->sole()->id,
]);
foreach (['siete-herramientas-de-la-calidad', 'spc', 'amef'] as $index => $slug) {
    $preset->items()->create(['course_id' => Course::where('slug', $slug)->sole()->id, 'position' => $index + 1]);
}
$result = app(RouteIssuer::class)->issueFocused(LeadCapture::fromArray([
    'name' => 'Mariana de prueba',
    'occupation' => 'Coordinadora de calidad',
    'area' => 'calidad',
    'funnel_id' => $funnel->id,
]), $advisor, requireValid: true);
RenderRouteDocument::dispatch($result->route->id, $result->route->snapshot_hash, $result->route->revisions);

for ($attempt = 0; $attempt < 60; $attempt++) {
    $route = LearningRoute::findOrFail($result->route->id);
    if ($route->status === RouteStatus::Rendered) {
        $pdf = Storage::disk('local')->get($route->pdf_path);
        if (! str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('El archivo guardado no es un PDF.');
        }
        echo 'Worker y Gotenberg: PDF válido en el volumen compartido.'.PHP_EOL;
        exit(0);
    }
    if ($route->status === RouteStatus::Failed) {
        throw new RuntimeException('El worker no pudo generar el PDF.');
    }
    sleep(1);
}
throw new RuntimeException('El PDF no se procesó dentro del plazo de la prueba.');
