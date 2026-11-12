<?php

declare(strict_types=1);

namespace App\Http\Controllers\Routes;

use App\Enums\RouteStatus;
use App\Http\Controllers\Controller;
use App\Models\DocumentVisit;
use App\Models\LearningRoute;
use App\Services\Documents\DocumentReading;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentReadingController extends Controller
{
    public function __invoke(Request $request, string $token, string $visit): Response
    {
        $route = LearningRoute::query()->where('access_token', $token)
            ->whereIn('status', [RouteStatus::Validated, RouteStatus::Rendering, RouteStatus::Rendered, RouteStatus::Failed])
            ->firstOrFail();
        abort_unless((string) $route->revisions === $request->query('revision'), 409);

        $data = $request->validate([
            'progress' => ['required', 'integer', 'between:0,100'],
            'active_ms' => ['required', 'integer', 'between:0,86400000'],
            'sections' => ['present', 'array:'.implode(',', array_keys(DocumentReading::SECTIONS))],
            'sections.*' => ['integer', 'between:1000,86400000'],
            'furthest_section' => ['nullable', Rule::in(array_keys(DocumentReading::SECTIONS))],
            'last_section' => ['nullable', Rule::in(array_keys(DocumentReading::SECTIONS))],
            'clicks' => ['present', 'array', 'max:3'],
            'clicks.*' => ['required', 'distinct', Rule::in(array_keys(DocumentReading::CLICKS))],
        ]);
        abort_if($data['active_ms'] < 1000 && $data['clicks'] === [], 422);
        abort_if(array_sum($data['sections']) > $data['active_ms'], 422);
        abort_if(isset($data['furthest_section']) && ! isset($data['sections'][$data['furthest_section']]), 422);
        abort_if(isset($data['last_section']) && ! isset($data['sections'][$data['last_section']]), 422);

        DB::transaction(function () use ($route, $visit, $data): void {
            // Insertar y bloquear evita duplicados y pérdidas si llegan dos beacons juntos.
            DB::table('document_visits')->insertOrIgnore([
                'id' => $visit, 'route_id' => $route->id, 'revision' => $route->revisions,
                'sections' => '{}', 'clicks' => '[]', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $reading = DocumentVisit::query()->where('route_id', $route->id)
                ->where('revision', $route->revisions)->lockForUpdate()->findOrFail($visit);
            // El cliente envía acumulados: reintentar un envío no duplica tiempo ni clics.
            $sections = $reading->sections;
            foreach ($data['sections'] as $key => $ms) {
                $sections[$key] = max($sections[$key] ?? 0, $ms);
            }
            $newer = $data['active_ms'] >= $reading->active_ms;
            $reading->fill([
                'furthest_section' => $data['progress'] > $reading->progress ? ($data['furthest_section'] ?? null) : $reading->furthest_section,
                'progress' => max($reading->progress, $data['progress']),
                'active_ms' => max($reading->active_ms, $data['active_ms']),
                'sections' => $sections,
                'clicks' => array_values(array_unique([...$reading->clicks, ...$data['clicks']])),
                'last_section' => $newer ? ($data['last_section'] ?? $reading->last_section) : $reading->last_section,
            ])->save();
        });

        return response()->noContent();
    }
}
