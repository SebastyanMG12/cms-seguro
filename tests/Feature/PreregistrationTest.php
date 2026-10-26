<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\EmergencyEpisode;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class PreregistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_preregistration_form_can_be_rendered(): void
    {
        $response = $this->get(route('preregistro.create'));

        $response->assertStatus(200);
        $response->assertSee('Hospital de Ubaté');
        $response->assertSee('Aviso Importante sobre la Atención de Urgencias');
        $response->assertSee('Signos de Alarma');
        $response->assertSee('Ley Estatutaria 1581 de 2012');
        $response->assertDontSee('triage_level');
    }

    public function test_successful_adult_preregistration(): void
    {
        $payload = [
            'full_name' => 'María Camila Gómez Restrepo',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1020304050',
            'birth_date' => '1995-04-12',
            'gender' => 'femenino',
            'patient_phone' => '3123456789',
            'emergency_reason' => 'Dolor abdominal agudo e intenso en fosa ilíaca derecha desde hace 6 horas.',
            'blood_type' => 'O+',
            'medication_allergies' => 'ninguna',
            'additional_notes' => 'Sin antecedentes quirúrgicos.',
            'consent_data_treatment' => '1',
            'hospital_contact_time' => '',
        ];

        $response = $this->post(route('preregistro.store'), $payload);

        $this->assertDatabaseCount('emergency_episodes', 1);
        $episode = EmergencyEpisode::first();

        $response->assertRedirect(route('preregistro.success', ['code' => $episode->public_code]));

        $this->assertEquals('preregistrado', $episode->status);
        $this->assertEquals('pre_registro', $episode->origin);
        $this->assertNull($episode->triage_level);
        $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{8}$/', $episode->public_code);

        // Validar paciente y cifrado
        $patient = $episode->patient;
        $this->assertNotNull($patient);
        $this->assertEquals('María Camila Gómez Restrepo', $patient->full_name);
        $this->assertEquals('1020304050', $patient->document_number);
        $this->assertNotNull($patient->document_index);

        // Validar datos clínicos
        $clinical = $episode->clinicalData;
        $this->assertNotNull($clinical);
        $this->assertEquals('ninguna', $clinical->allergies_type);
        $this->assertNull($clinical->allergies_detail);
        $this->assertEquals('O+', $clinical->blood_type);

        // Validar consentimiento Ley 1581
        $consent = Consent::where('episode_id', $episode->id)->where('type', 'tratamiento_datos')->first();
        $this->assertNotNull($consent);
        $this->assertEquals('v1.0', $consent->text_version);
        $this->assertEquals('paciente', $consent->granted_by);
        $this->assertNotEmpty($consent->ip_hash);

        // Validar auditoría
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'preregistro.creado',
            'model_id' => $episode->id,
        ]);
    }

    public function test_optional_companion_sharing_consent_creates_separate_consent_record(): void
    {
        $payload = [
            'full_name' => 'Carlos Alberto Rodríguez',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '79888999',
            'patient_phone' => '3109876543',
            'emergency_reason' => 'Trauma en tobillo derecho tras caída.',
            'medication_allergies' => 'ninguna',
            'companion_name' => 'Martha Patricia López',
            'companion_phone' => '3157778899',
            'companion_relationship' => 'Cónyuge',
            'consent_data_treatment' => '1',
            'consent_share_companion' => '1',
            'hospital_contact_time' => '',
        ];

        $response = $this->post(route('preregistro.store'), $payload);
        $response->assertRedirect();

        $episode = EmergencyEpisode::first();
        $consents = Consent::where('episode_id', $episode->id)->get();

        $this->assertCount(2, $consents);
        $this->assertTrue($consents->contains('type', 'tratamiento_datos'));
        $this->assertTrue($consents->contains('type', 'compartir_acompanante'));

        $companion = $episode->companion;
        $this->assertNotNull($companion);
        $this->assertEquals('Martha Patricia López', $companion->name);
        $this->assertEquals('3157778899', $companion->phone);
    }

    public function test_conditional_validation_minor_requires_companion(): void
    {
        // Paciente con Tarjeta de Identidad (menor) sin datos de acompañante
        $payload = [
            'full_name' => 'Juan David Pérez',
            'document_type' => 'Tarjeta de identidad',
            'document_number' => '1099887766',
            'patient_phone' => '3001112233',
            'emergency_reason' => 'Fiebre persistente y vómito desde hace 2 días.',
            'medication_allergies' => 'no_se',
            'consent_data_treatment' => '1',
            'hospital_contact_time' => '',
        ];

        $response = $this->post(route('preregistro.store'), $payload);
        $response->assertSessionHasErrors(['companion_name', 'companion_phone', 'companion_relationship']);
        $this->assertDatabaseCount('emergency_episodes', 0);
    }

    public function test_conditional_validation_companion_fields_if_any_filled(): void
    {
        // Paciente adulto que llena nombre de acompañante pero olvida teléfono y parentesco
        $payload = [
            'full_name' => 'Andrés Morales',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '80123456',
            'patient_phone' => '3114445566',
            'emergency_reason' => 'Cefalea intensa refractaria.',
            'medication_allergies' => 'ninguna',
            'companion_name' => 'Lucía Morales',
            // companion_phone y companion_relationship ausentes
            'consent_data_treatment' => '1',
            'hospital_contact_time' => '',
        ];

        $response = $this->post(route('preregistro.store'), $payload);
        $response->assertSessionHasErrors(['companion_phone', 'companion_relationship']);
    }

    public function test_conditional_validation_allergy_detail_required_if_allergy_yes(): void
    {
        // Marca que sí tiene alergias pero no pone el detalle
        $payload = [
            'full_name' => 'Lucía Beltrán',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '52123456',
            'patient_phone' => '3145556677',
            'emergency_reason' => 'Reacción alérgica cutánea.',
            'medication_allergies' => 'si',
            // allergies_detail ausente
            'consent_data_treatment' => '1',
            'hospital_contact_time' => '',
        ];

        $response = $this->post(route('preregistro.store'), $payload);
        $response->assertSessionHasErrors(['allergies_detail']);

        // Ahora enviando el detalle obligatorio
        $payload['allergies_detail'] = 'Penicilina y derivados beta-lactámicos';
        $responseSuccess = $this->post(route('preregistro.store'), $payload);
        $responseSuccess->assertRedirect();

        $episode = EmergencyEpisode::first();
        $this->assertEquals('Penicilina y derivados beta-lactámicos', $episode->clinicalData->allergies_detail);
    }

    public function test_conditional_validation_phone_required_at_least_one(): void
    {
        // Ni teléfono de paciente ni de acompañante
        $payload = [
            'full_name' => 'Gonzalo Méndez',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '19456789',
            'emergency_reason' => 'Dolor precordial con mareo.',
            'medication_allergies' => 'ninguna',
            'consent_data_treatment' => '1',
            'hospital_contact_time' => '',
        ];

        $response = $this->post(route('preregistro.store'), $payload);
        $response->assertSessionHasErrors(['patient_phone']);
    }

    public function test_validation_field_length_limits_and_formats(): void
    {
        // Nombre menor a 2 caracteres y motivo menor a 5 caracteres
        $response = $this->post(route('preregistro.store'), [
            'full_name' => 'A',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '123',
            'patient_phone' => '123', // Teléfono muy corto
            'emergency_reason' => 'Mal',
            'medication_allergies' => 'ninguna',
            'consent_data_treatment' => '1',
        ]);

        $response->assertSessionHasErrors(['full_name', 'patient_phone', 'emergency_reason']);

        // Motivo mayor a 500 caracteres
        $responseLong = $this->post(route('preregistro.store'), [
            'full_name' => 'Nombre Válido',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '12345678',
            'patient_phone' => '3101234567',
            'emergency_reason' => str_repeat('a', 501),
            'medication_allergies' => 'ninguna',
            'consent_data_treatment' => '1',
        ]);

        $responseLong->assertSessionHasErrors(['emergency_reason']);
    }

    public function test_triage_level_cannot_be_manipulated_by_patient(): void
    {
        $payload = [
            'full_name' => 'Pedro Infante',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '11223344',
            'patient_phone' => '3119998877',
            'emergency_reason' => 'Dolor lumbar severo.',
            'medication_allergies' => 'ninguna',
            'consent_data_treatment' => '1',
            'triage_level' => 'I', // Intento de autocalificación de triage
        ];

        $response = $this->post(route('preregistro.store'), $payload);
        $response->assertSessionHasErrors(['triage_level']);
    }

    public function test_honeypot_discards_or_fakes_bot_submission(): void
    {
        $payload = [
            'full_name' => 'Bot Spammer',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '99999999',
            'patient_phone' => '3110000000',
            'emergency_reason' => 'Compra ya en http://spam.com',
            'medication_allergies' => 'ninguna',
            'consent_data_treatment' => '1',
            'hospital_contact_time' => 'bot-value', // Honeypot activo
        ];

        $response = $this->post(route('preregistro.store'), $payload);

        // Se redirige pero NO se almacena en base de datos
        $this->assertDatabaseCount('emergency_episodes', 0);
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_anti_enumeration_does_not_reveal_existing_patient_document(): void
    {
        // 1er registro
        $payload1 = [
            'full_name' => 'Elena Vásquez',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025888999',
            'patient_phone' => '3161234567',
            'emergency_reason' => 'Crisis asmática leve.',
            'medication_allergies' => 'ninguna',
            'consent_data_treatment' => '1',
            'hospital_contact_time' => '',
        ];

        $res1 = $this->post(route('preregistro.store'), $payload1);
        $res1->assertRedirect();
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('emergency_episodes', 1);

        $patient = Patient::first();

        // 2do registro con el mismo documento (otra visita o nuevo episodio)
        $payload2 = [
            'full_name' => 'Elena Vásquez',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025888999',
            'patient_phone' => '3161234567',
            'emergency_reason' => 'Nuevo episodio por contusión en rodilla.',
            'medication_allergies' => 'ninguna',
            'consent_data_treatment' => '1',
            'hospital_contact_time' => '',
        ];

        $res2 = $this->post(route('preregistro.store'), $payload2);
        // Anti-enumeración: responde con éxito normal, sin advertencias de duplicidad
        $res2->assertRedirect();

        // Se mantiene un único paciente gracias al índice ciego, pero ahora hay 2 episodios
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('emergency_episodes', 2);

        $episodes = EmergencyEpisode::where('patient_id', $patient->id)->get();
        $this->assertCount(2, $episodes);
        $this->assertNotEquals($episodes[0]->public_code, $episodes[1]->public_code);
    }

    public function test_success_page_never_reveals_sensitive_health_data_or_other_patient_data(): void
    {
        $payload = [
            'full_name' => 'Santiago Pinzón',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1070999888',
            'patient_phone' => '3187654321',
            'emergency_reason' => 'Dolor torácico atípico con sospecha de pericarditis.',
            'medication_allergies' => 'si',
            'allergies_detail' => 'Alergia severa a dipirona',
            'consent_data_treatment' => '1',
            'hospital_contact_time' => '',
        ];

        $this->post(route('preregistro.store'), $payload);
        $episode = EmergencyEpisode::first();

        $response = $this->get(route('preregistro.success', ['code' => $episode->public_code]));

        $response->assertStatus(200);
        $response->assertSee($episode->public_code);
        $response->assertSee('Pre-registro Registrado Exitosamente');

        // Garantizar que NO se muestran datos clínicos ni de salud en la pantalla pública
        $response->assertDontSee('Santiago Pinzón');
        $response->assertDontSee('1070999888');
        $response->assertDontSee('Dolor torácico atípico');
        $response->assertDontSee('Alergia severa a dipirona');

        // Código inexistente responde 404
        $notFound = $this->get(route('preregistro.success', ['code' => 'UBTZZZZZ']));
        $notFound->assertStatus(404);
    }

    public function test_preregistration_expiration_scheduled_command(): void
    {
        // Episodio vencido
        $expiredEpisode = EmergencyEpisode::create([
            'public_code' => 'UBTEXP01',
            'origin' => 'pre_registro',
            'status' => 'preregistrado',
            'reason' => 'Consulta no atendida',
            'pre_registration_expires_at' => now()->subMinutes(10),
        ]);

        // Episodio vigente
        $activeEpisode = EmergencyEpisode::create([
            'public_code' => 'UBTACT01',
            'origin' => 'pre_registro',
            'status' => 'preregistrado',
            'reason' => 'Consulta reciente',
            'pre_registration_expires_at' => now()->addHours(20),
        ]);

        // Ejecutar comando de consola
        Artisan::call('hospital:expire-preregistrations');

        $expiredEpisode->refresh();
        $activeEpisode->refresh();

        $this->assertEquals('expirado', $expiredEpisode->status);
        $this->assertEquals('preregistrado', $activeEpisode->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'preregistro.expirado',
            'model_id' => $expiredEpisode->id,
        ]);
    }

    public function test_consent_immutability_guards(): void
    {
        $episode = EmergencyEpisode::create([
            'public_code' => 'UBTCONS1',
            'origin' => 'pre_registro',
            'status' => 'preregistrado',
            'reason' => 'Consulta consentimiento',
            'pre_registration_expires_at' => now()->addHours(24),
        ]);

        $consent = Consent::create([
            'episode_id' => $episode->id,
            'type' => 'tratamiento_datos',
            'granted_by' => 'paciente',
            'text_version' => 'v1.0',
            'granted_at' => now(),
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'created_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $consent->update(['granted_by' => 'representante']);
    }

    public function test_consent_cannot_be_deleted(): void
    {
        $episode = EmergencyEpisode::create([
            'public_code' => 'UBTCONS2',
            'origin' => 'pre_registro',
            'status' => 'preregistrado',
            'reason' => 'Consulta consentimiento 2',
            'pre_registration_expires_at' => now()->addHours(24),
        ]);

        $consent = Consent::create([
            'episode_id' => $episode->id,
            'type' => 'tratamiento_datos',
            'granted_by' => 'paciente',
            'text_version' => 'v1.0',
            'granted_at' => now(),
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'created_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $consent->delete();
    }
}
