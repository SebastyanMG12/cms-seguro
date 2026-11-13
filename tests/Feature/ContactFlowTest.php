<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\ReceivedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('correos.ver', 'web');
        $adminRole = Role::findOrCreate('administrador', 'web');
        $adminRole->givePermissionTo('correos.ver');
    }

    public function test_contact_submission_persists_in_database_and_sends_email(): void
    {
        Mail::fake();

        $response = $this->post('/contacto', [
            'name' => 'Carlos Pérez',
            'email' => 'carlos.perez@ejemplo.com',
            'message' => 'Solicito información sobre la disponibilidad de consulta externa y citas para medicina general.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 1. Verificar persistencia en base de datos para la bandeja del administrador
        $this->assertDatabaseHas('received_emails', [
            'from_email' => 'carlos.perez@ejemplo.com',
            'from_name' => 'Carlos Pérez',
            'is_read' => false,
        ]);

        // 2. Verificar que se despachó el correo hacia el buzón administrativo
        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            return $mail->senderEmail === 'carlos.perez@ejemplo.com'
                && $mail->senderName === 'Carlos Pérez';
        });
    }

    public function test_admin_can_view_contact_messages_in_received_emails_inbox(): void
    {
        $email = ReceivedEmail::create([
            'message_id' => 'contact_test_uuid@hospitalubate.gov.co',
            'from_email' => 'maria.gomez@ejemplo.com',
            'from_name' => 'María Gómez',
            'subject' => 'Contacto Web: María Gómez',
            'body' => 'Necesito copia de mi epicrisis del último ingreso.',
            'received_at' => now(),
            'is_read' => false,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@hospital.test',
            'status' => 'active',
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole('administrador');

        // Actuar como admin autenticado
        $response = $this->actingAs($admin)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('emails.index'));

        $response->assertStatus(200);
        $response->assertSee('María Gómez');
        $response->assertSee('Nuevo');

        // Ver el correo en detalle y comprobar que pasa a leído
        $showResponse = $this->actingAs($admin)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('emails.show', $email));

        $showResponse->assertStatus(200);
        $showResponse->assertSee('Necesito copia de mi epicrisis');
        $this->assertTrue($email->fresh()->is_read);
    }

    public function test_admin_can_trigger_mailbox_sync(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin_sync@hospital.test',
            'status' => 'active',
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole('administrador');

        $response = $this->actingAs($admin)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('emails.sync'));

        $response->assertRedirect(route('emails.index'));
        $response->assertSessionHas('success');
    }
}
