<?php

use App\Enums\ValidationResult;
use App\Models\Course;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\ValidationReport;
use App\Services\RouteValidation\RouteValidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Las pruebas del validador corren contra el catálogo real sembrado: una regla que
 * solo pasa contra datos de fábrica no demuestra nada sobre el catálogo de DP SyS.
 */
pest()->beforeEach(fn () => $this->seed(DatabaseSeeder::class))
    ->in(
        'Feature/RouteValidation',
        'Feature/RouteComposition',
        'Feature/RouteGeneration',
        'Feature/Funnels',
        'Feature/Routes',
        'Feature/CatalogSeedTest.php',
        'Feature/CatalogAnalysisSeedTest.php',
    );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/** El veredicto de una regla concreta dentro del informe. */
function evaluationFor(ValidationReport $report, string $ruleCode)
{
    $evaluation = $report->for($ruleCode);

    expect($evaluation)->not->toBeNull("La regla [{$ruleCode}] no se evaluó para este documento.");

    return $evaluation;
}

expect()->extend('toFailRule', function (string $ruleCode) {
    $evaluation = evaluationFor($this->value, $ruleCode);

    expect($evaluation->result)->toBe(
        ValidationResult::Fail,
        "Se esperaba que [{$ruleCode}] reprobara y resultó [{$evaluation->result->value}].",
    );

    return $this;
});

expect()->extend('toPassRule', function (string $ruleCode) {
    $evaluation = evaluationFor($this->value, $ruleCode);

    expect($evaluation->result)->toBe(
        ValidationResult::Pass,
        "Se esperaba que [{$ruleCode}] pasara y resultó [{$evaluation->result->value}]: "
            .json_encode($evaluation->details, JSON_UNESCAPED_UNICODE),
    );

    return $this;
});

expect()->extend('toSkipRule', function (string $ruleCode) {
    expect(evaluationFor($this->value, $ruleCode)->result)->toBe(ValidationResult::Skip);

    return $this;
});

expect()->extend('toNotEvaluateRule', function (string $ruleCode) {
    expect($this->value->for($ruleCode))->toBeNull("La regla [{$ruleCode}] no debía aplicar a este documento.");

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** El id de un curso por su slug, para que las pruebas se lean como el catálogo. */
function courseId(string $slug): int
{
    return (int) Course::where('slug', $slug)->sole()->id;
}

/** Corre el validador real, con las reglas tal como están sembradas. */
function validateDraft(RouteDraft $draft): ValidationReport
{
    return app(RouteValidator::class)->validate($draft);
}
