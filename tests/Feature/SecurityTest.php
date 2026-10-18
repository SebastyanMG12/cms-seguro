<?php

namespace Tests\Feature;

use App\Models\ReceivedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'author', string $status = 'active'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'status' => $status])->save();

        return $user;
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_author_cannot_view_inbox(): void
    {
        $this->actingAs($this->user('author'))->get('/correos')->assertForbidden();
    }

    public function test_admin_can_view_inbox(): void
    {
        $this->actingAs($this->user('admin'))->get('/correos')->assertOk();
    }

    public function test_author_cannot_view_audit(): void
    {
        $this->actingAs($this->user('author'))->get('/auditoria')->assertForbidden();
    }

    public function test_registration_ignores_role_field(): void
    {
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@secureapp.test',
            'password' => 'Clave#Segura2026',
            'password_confirmation' => 'Clave#Segura2026',
            'role' => 'admin',
        ]);

        $this->assertSame('author', User::where('email', 'intruso@secureapp.test')->value('role'));
    }

    public function test_user_cannot_view_other_users_post(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $post = $owner->posts()->create(['title' => 'x', 'slug' => 'x', 'body' => 'x', 'status' => 'draft']);

        $this->actingAs($other)->get("/posts/{$post->id}")->assertForbidden();
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = $this->user('author', 'suspended');

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_inbox_shows_html_as_text(): void
    {
        ReceivedEmail::create([
            'message_id' => '<t@x>',
            'from_email' => 'a@x.test',
            'subject' => '<script>alert(1)</script>',
            'body' => 'x',
        ]);

        $this->actingAs($this->user('admin'))->get('/correos')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_errors_do_not_leak_details_in_production(): void
    {
        config(['app.debug' => false]);

        $this->get('/ruta-que-no-existe')->assertNotFound()->assertDontSee('vendor');
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
    public function test_password_reset_deletes_existing_sessions(): void
    {
        $user = $this->user();

        DB::table('sessions')->insert([
            'id' => 'sesion-robada-123',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $record = \App\Models\PasswordResetCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['reset_verified_id' => $record->id])
            ->post('/recuperar/nueva', [
                'password' => 'NuevaClave#Segura2026',
                'password_confirmation' => 'NuevaClave#Segura2026',
            ]);

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-robada-123']);
    }
}