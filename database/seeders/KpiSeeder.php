<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\MeasureKind;
use App\Enums\Kpis\MeasureUnit;
use App\Enums\Kpis\SalesFieldType;
use App\Enums\MembershipPlan;
use App\Models\Kpi;
use App\Models\SalesEventField;
use App\Models\SalesEventType;
use Illuminate\Database\Seeder;

/**
 * El punto de partida del módulo de KPIs: el tipo de evento «Venta» y un KPI que
 * lo mide.
 *
 * No son los KPIs de ventas —esos son variables y los configura el administrador
 * (decidido el 2026-09-26)—. Es lo mínimo para que el puente con el generador de
 * rutas tenga a dónde sugerir: la venta lleva los mismos datos que ya captura la
 * membresía, el plan publicado y lo que se cobró. Nada de aquí es un número de
 * negocio: no se siembran metas, equipos ni personas.
 *
 * Solo crea lo que falta. Si el administrador ya editó el tipo o el KPI, correr el
 * seeder otra vez no le pisa nada.
 */
class KpiSeeder extends Seeder
{
    public function run(): void
    {
        $venta = SalesEventType::firstOrCreate(
            ['slug' => (string) config('kpis.membership_event_type', 'venta')],
            ['name' => 'Venta', 'description' => 'Una membresía vendida. El generador de rutas sugiere una por cada ruta final emitida.'],
        );

        $campos = [
            ['key' => 'plan', 'label' => 'Plan', 'data_type' => SalesFieldType::Opcion, 'options' => MembershipPlan::options(), 'is_required' => true],
            ['key' => 'importe', 'label' => 'Importe cobrado', 'data_type' => SalesFieldType::Dinero, 'options' => [], 'is_required' => true],
            ['key' => 'cantidad', 'label' => 'Cantidad', 'data_type' => SalesFieldType::Numero, 'options' => [], 'is_required' => false],
        ];

        foreach ($campos as $posicion => $campo) {
            SalesEventField::firstOrCreate(
                ['sales_event_type_id' => $venta->id, 'key' => $campo['key']],
                [...$campo, 'position' => $posicion],
            );
        }

        if (Kpi::query()->where('slug', 'ventas')->exists()) {
            return;
        }

        $kpi = Kpi::create([
            'slug' => 'ventas',
            'name' => 'Ventas',
            'description' => 'Punto de partida: membresías vendidas y lo que se cobró. Se edita o se desactiva en Configuración.',
            'display_order' => 0,
        ]);

        $medidas = [
            ['slug' => 'membresias', 'name' => 'Membresías', 'unit' => MeasureUnit::Piezas, 'aggregation' => MeasureAggregation::Conteo, 'field_key' => null],
            ['slug' => 'importe', 'name' => 'Importe', 'unit' => MeasureUnit::Dinero, 'aggregation' => MeasureAggregation::Suma, 'field_key' => 'importe'],
            ['slug' => 'ticket-promedio', 'name' => 'Ticket promedio', 'unit' => MeasureUnit::Dinero, 'aggregation' => MeasureAggregation::Promedio, 'field_key' => 'importe'],
        ];

        foreach ($medidas as $posicion => $medida) {
            $kpi->measures()->create([
                ...$medida,
                'kind' => MeasureKind::Agregado,
                'sales_event_type_id' => $venta->id,
                'filters' => [],
                'position' => $posicion,
            ]);
        }
    }
}
