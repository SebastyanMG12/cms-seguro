<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_episodes', function (Blueprint $table) {
            $table->string('nn_code', 30)->nullable()->index()->after('public_code');
            $table->boolean('is_nn')->default(false)->index()->after('nn_code');
            $table->string('apparent_sex', 30)->nullable()->after('is_nn'); // masculino, femenino, indeterminado
            $table->string('estimated_age', 60)->nullable()->after('apparent_sex');
            $table->text('distinguishing_marks')->nullable()->after('estimated_age'); // señas particulares, vestimenta, tatuajes
            $table->string('arrival_mode', 50)->nullable()->after('distinguishing_marks'); // ambulancia, policia, bomberos, transeunte, propio_pie, otro
            $table->string('brought_by_name', 150)->nullable()->after('arrival_mode');
            $table->string('brought_by_contact', 150)->nullable()->after('brought_by_name');
            $table->text('no_consent_reason')->nullable()->after('brought_by_contact'); // interés vital, paciente sin capacidad
            $table->foreignUuid('original_nn_patient_id')->nullable()->constrained('patients')->nullOnDelete()->after('patient_id');
            $table->timestamp('reconciled_at')->nullable()->after('closed_at');
            $table->foreignId('reconciled_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('reconciled_at');
            $table->text('reconciliation_evidence')->nullable()->after('reconciled_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_episodes', function (Blueprint $table) {
            $table->dropForeign(['original_nn_patient_id']);
            $table->dropForeign(['reconciled_by_user_id']);
            $table->dropIndex(['nn_code']);
            $table->dropIndex(['is_nn']);
            $table->dropColumn([
                'nn_code',
                'is_nn',
                'apparent_sex',
                'estimated_age',
                'distinguishing_marks',
                'arrival_mode',
                'brought_by_name',
                'brought_by_contact',
                'no_consent_reason',
                'original_nn_patient_id',
                'reconciled_at',
                'reconciled_by_user_id',
                'reconciliation_evidence',
            ]);
        });
    }
};
