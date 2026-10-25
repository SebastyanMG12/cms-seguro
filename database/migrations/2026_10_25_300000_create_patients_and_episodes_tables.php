<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adaptar audit_logs para soportar identificadores UUID
        if (Schema::hasTable('audit_logs')) {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN model_id TYPE VARCHAR(64) USING model_id::varchar;');
        }

        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('full_name'); // Cifrado en aplicación
            $table->string('document_type', 50)->nullable();
            $table->text('document_number')->nullable(); // Cifrado en aplicación
            $table->string('document_index', 64)->nullable()->index(); // HMAC blind index
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('phone')->nullable(); // Cifrado en aplicación
            $table->string('identity_status', 30)->default('identificado'); // identificado, no_identificado
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('emergency_episodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('public_code', 12)->unique();
            $table->string('origin', 30)->default('pre_registro'); // pre_registro, emergencia
            $table->string('status', 30)->default('preregistrado'); // preregistrado, expirado, en_espera, en_valoracion, ingresado, egresado
            $table->string('triage_level', 5)->nullable(); // I, II, III, IV, V (asignado por profesional)
            $table->text('reason'); // Motivo de urgencia (máx 500 caracteres)
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('admitted_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->string('discharge_type', 40)->nullable(); // alta_medica, remision, alta_voluntaria, abandono, fallecimiento
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('pre_registration_expires_at')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'pre_registration_expires_at']);
            $table->index('origin');
        });

        Schema::create('episode_clinical_data', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('episode_id')->constrained('emergency_episodes')->cascadeOnDelete();
            $table->string('allergies_type', 20)->default('ninguna'); // ninguna, no_se, si
            $table->text('allergies_detail')->nullable(); // Cifrado en aplicación
            $table->string('blood_type', 15)->default('desconocido'); // A+, A-, B+, B-, AB+, AB-, O+, O-, desconocido
            $table->text('notes')->nullable(); // Notas adicionales (máx 1000 caracteres)
            $table->text('current_medication')->nullable(); // Cifrado en aplicación
            $table->json('warning_signs')->nullable();
            $table->timestamps();

            $table->unique('episode_id');
        });

        Schema::create('companions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('episode_id')->constrained('emergency_episodes')->cascadeOnDelete();
            $table->text('name')->nullable();
            $table->text('phone')->nullable(); // Cifrado en aplicación
            $table->string('relationship', 50)->nullable();
            $table->string('pin_hash')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('episode_id');
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('episode_id')->constrained('emergency_episodes')->cascadeOnDelete();
            $table->string('type', 40); // tratamiento_datos, compartir_acompanante
            $table->string('granted_by', 30)->default('paciente'); // paciente, representante
            $table->string('granted_by_relationship', 50)->nullable();
            $table->json('scope')->nullable();
            $table->string('text_version', 20)->default('v1.0');
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_hash', 64)->nullable(); // SHA-256 de la IP
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['episode_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('companions');
        Schema::dropIfExists('episode_clinical_data');
        Schema::dropIfExists('emergency_episodes');
        Schema::dropIfExists('patients');

        if (Schema::hasTable('audit_logs')) {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN model_id TYPE BIGINT USING (CASE WHEN model_id ~ \'^[0-9]+$\' THEN model_id::bigint ELSE NULL END);');
        }
    }
};
