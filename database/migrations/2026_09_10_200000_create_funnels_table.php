<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El contenedor comercial: una campaña de venta con vigencia propia.
 *
 * «Embudo» es lenguaje interno y nunca aparece en el documento; por eso
 * `internal_name` y `public_name` están separados. El ángulo comercial es columna
 * aparte de la lista de cursos: la auditoría de publicidad mostró que el mismo
 * paquete rota bajo descuento, urgencia, informativo y retención cada una o dos
 * semanas, y duplicar el embudo por cada gancho no escala.
 *
 * Las columnas de precio se crean nulas y sin interfaz: la administración del
 * descuento sigue sin decidirse (MODULO_EMBUDOS.md §11.2) y docs/ARQUITECTURA.md §5 deja las
 * anclas de precio fuera de la v1. Existen para no migrar después, nada más.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            // Convención obligatoria `{año}-{mes}-{tema}-{angulo}`. A la velocidad
            // de rotación actual habrá decenas en meses; sin ella el listado se
            // vuelve inservible. El formato lo valida el FormRequest.
            $table->string('slug')->unique();
            $table->string('internal_name');
            $table->string('public_name');
            $table->string('angle');
            $table->string('headline')->nullable();
            $table->string('subheadline')->nullable();
            $table->date('starts_on');
            // Null = permanente.
            $table->date('ends_on')->nullable();
            $table->string('state')->default('borrador');

            // --- bloqueados por §11.2: sin interfaz ni lógica asociada ---
            $table->integer('list_price_cents')->nullable();
            $table->integer('promo_price_cents')->nullable();
            $table->string('discount_label')->nullable();
            $table->foreignId('price_anchor_course_id')->nullable()->constrained('courses')->nullOnDelete();

            // --- dormidos: trazabilidad publicitaria, post-v1 ---
            $table->string('utm_campaign')->nullable();
            $table->string('meta_campaign_ref')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['state', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnels');
    }
};
