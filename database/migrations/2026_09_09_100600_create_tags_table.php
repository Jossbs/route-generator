<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->enum('type', ['tema', 'cluster', 'industria', 'nivel_experiencia']);
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });

        Schema::create('course_tag', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['course_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_tag');
        Schema::dropIfExists('tags');
    }
};
