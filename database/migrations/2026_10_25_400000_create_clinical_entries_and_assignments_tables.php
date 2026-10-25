<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_episodes', function (Blueprint $table) {
            $table->foreignId('triage_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('triage_at')->nullable();
        });

        Schema::create('clinical_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('episode_id')->constrained('emergency_episodes')->cascadeOnDelete();
            $table->string('type', 30); // diagnostico, procedimiento, nota, aclaracion, anulacion
            $table->text('content'); // Cifrado en la aplicación
            $table->foreignId('author_user_id')->constrained('users');
            $table->timestamp('performed_at'); // Hora inmutable fijada por el servidor
            $table->foreignId('references_entry_id')->nullable()->constrained('clinical_entries')->nullOnDelete();
            $table->text('reason')->nullable(); // Obligatorio en aclaraciones y anulaciones
            $table->timestamp('created_at')->useCurrent();

            $table->index(['episode_id', 'type']);
            $table->index('author_user_id');
        });

        Schema::create('episode_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('episode_id')->constrained('emergency_episodes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('role', 30)->default('responsable'); // responsable, apoyo
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['episode_id', 'ended_at']);
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_assignments');
        Schema::dropIfExists('clinical_entries');

        Schema::table('emergency_episodes', function (Blueprint $table) {
            $table->dropForeign(['triage_user_id']);
            $table->dropColumn(['triage_user_id', 'triage_at']);
        });
    }
};
