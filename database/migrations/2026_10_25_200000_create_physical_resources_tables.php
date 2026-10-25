<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->string('address', 255)->nullable();
            $table->string('status', 20)->default('activo'); // activo, inactivo
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->integer('number');
            $table->string('name', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['site_id', 'number']);
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained('floors')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 50); // urgencias, observacion, hospitalizacion, etc.
            $table->string('status', 20)->default('activo'); // activo, inactivo
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('code', 50); // ej: H101
            $table->unsignedInteger('capacity')->default(4); // Configurable, no fijo
            $table->string('status', 30)->default('disponible'); // disponible, fuera_de_servicio, mantenimiento
            $table->boolean('is_isolation')->default(false);
            $table->boolean('is_pediatric')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['area_id', 'code']);
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('code', 50)->unique(); // Código generado, ej: P1-H101-A
            $table->string('status', 30)->default('disponible'); // disponible, ocupada, en_limpieza, mantenimiento, fuera_de_servicio, reservada
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bed_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bed_id')->constrained('beds')->cascadeOnDelete();
            $table->uuid('episode_id')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        // Índice único parcial en PostgreSQL para impedir doble asignación activa de una camilla
        DB::statement('CREATE UNIQUE INDEX bed_assignments_active_bed_unique ON bed_assignments (bed_id) WHERE released_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_assignments');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('sites');
    }
};
