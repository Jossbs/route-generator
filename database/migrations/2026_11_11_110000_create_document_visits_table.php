<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('route_id')->constrained('learning_routes')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedSmallInteger('progress')->default(0);
            $table->unsignedInteger('active_ms')->default(0);
            $table->jsonb('sections');
            $table->jsonb('clicks');
            $table->string('last_section')->nullable();
            $table->string('furthest_section')->nullable();
            $table->timestamps();
            $table->index(['route_id', 'revision', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_visits');
    }
};
