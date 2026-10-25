<?php

namespace Tests\Feature;

use App\Models\PasswordResetCode;
use App\Models\ReceivedEmail;
use App\Models\User;
use App\Services\Mail\MailSource;
use App\Services\TwoFactor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(string $role = 'autor', string $status = 'active', bool $withTwoFactor = true): User
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => $status])->save();
        $user->assignRole($role);

        if ($withTwoFactor) {
            $user->forceFill([
                'two_factor_secret' => app(TwoFactor::class)->generateSecret(),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $user;
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_author_cannot_view_inbox(): void
    {
        $this->actingAs($this->user('autor'))->get('/correos')->assertForbidden();
    }

    public function test_admin_can_view_inbox(): void
    {
        $this->actingAs($this->user('administrador'))->get('/correos')->assertOk();
    }

    public function test_author_cannot_view_audit(): void
    {
        $this->actingAs($this->user('autor'))->get('/auditoria')->assertForbidden();
    }

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@x.test'])->assertStatus(404);
    }

    public function test_only_admin_can_create_users(): void
    {
        $payload = [
            'name' => 'Nuevo',
            'email' => 'nuevo@x.test',
            'roles' => ['autor', 'editor'],
            'password' => 'Clave#Segura2026',
            'password_confirmation' => 'Clave#Segura2026',
        ];

        $this->actingAs($this->user('autor'))->post('/admin/usuarios', $payload)->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@x.test']);

        $this->actingAs($this->user('administrador'))->post('/admin/usuarios', $payload)
            ->assertRedirect(route('admin.users.index'));

        $nuevo = User::where('email', 'nuevo@x.test')->firstOrFail();
        $this->assertTrue($nuevo->hasAllRoles(['autor', 'editor']));
    }

    public function test_user_can_have_multiple_roles(): void
    {
        $user = $this->user('administrador');
        $user->assignRole('auditor');

        $this->actingAs($user)->get('/auditoria')->assertOk();
        $this->actingAs($user)->get('/admin/usuarios')->assertOk();
    }

    public function test_admin_without_auditor_role_cannot_view_audit(): void
    {
        $this->actingAs($this->user('administrador'))->get('/auditoria')->assertForbidden();
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
        $user = $this->user('autor', 'suspended');

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

        $this->actingAs($this->user('administrador'))->get('/correos')
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

        $record = PasswordResetCode::create([
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

    public function test_login_is_limited_per_ip_across_different_emails(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->post('/login', ['email' => "user{$i}@x.test", 'password' => 'mala']);
        }

        $this->post('/login', ['email' => 'otro@x.test', 'password' => 'mala'])
            ->assertStatus(429);
    }

    public function test_contact_honeypot_discards_bot_messages(): void
    {
        Mail::fake();

        $this->post('/contacto', [
            'name' => 'Bot',
            'email' => 'bot@x.test',
            'message' => 'mensaje de spam largo',
            'website' => 'http://spam.test',
        ]);

        Mail::assertNothingSent();
    }

    public function test_session_cookie_is_http_only(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_receive_command_is_idempotent(): void
    {
        $this->app->bind(MailSource::class, fn () => new class implements MailSource
        {
            public function fetch(int $limit = 10): array
            {
                return [
                    [
                        'message_id' => '<test-1@x.test>',
                        'from_email' => 'a@x.test',
                        'from_name' => 'A',
                        'subject' => 'Hola',
                        'body' => 'Texto',
                        'received_at' => '2026-10-23 10:00:00',
                    ],
                ];
            }
        });

        $this->artisan('mail:receive')->assertSuccessful();
        $this->artisan('mail:receive')->assertSuccessful();

        $this->assertDatabaseCount('received_emails', 1);
    }

    public function test_user_can_enable_two_factor_with_valid_code(): void
    {
        $user = $this->user('medico', 'active', false);

        $this->actingAs($user)->get('/seguridad/2fa')->assertOk();

        $secret = $user->fresh()->two_factor_secret;
        $this->assertNotNull($secret);

        $code = app(TwoFactor::class)->currentCode($secret);

        $this->actingAs($user)->post('/seguridad/2fa/confirmar', ['code' => $code])
            ->assertRedirect(route('two-factor.show'))
            ->assertSessionHas('recovery_codes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_two_factor_rejects_invalid_code(): void
    {
        $user = $this->user('medico', 'active', false);

        $this->actingAs($user)->get('/seguridad/2fa');
        $this->actingAs($user)->post('/seguridad/2fa/confirmar', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_two_factor_secret_is_encrypted_in_database(): void
    {
        $user = $this->user('medico', 'active', false);

        $this->actingAs($user)->get('/seguridad/2fa');

        $raw = \DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $plain = $user->fresh()->two_factor_secret;

        $this->assertNotSame($plain, $raw);
    }

    public function test_login_requires_second_factor_when_enabled(): void
    {
        $user = $this->user('medico');
        $user->forceFill([
            'password' => bcrypt('Clave#Segura2026'),
            'two_factor_secret' => app(TwoFactor::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'Clave#Segura2026'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
    }

    public function test_second_factor_completes_login(): void
    {
        $secret = app(TwoFactor::class)->generateSecret();
        $user = $this->user('medico');
        $user->forceFill([
            'password' => bcrypt('Clave#Segura2026'),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'Clave#Segura2026']);

        $this->post('/login/2fa', ['code' => app(TwoFactor::class)->currentCode($secret)])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_totp_code_cannot_be_reused(): void
    {
        $secret = app(TwoFactor::class)->generateSecret();
        $user = $this->user('medico');
        $user->forceFill([
            'password' => bcrypt('Clave#Segura2026'),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $code = app(TwoFactor::class)->currentCode($secret);

        $this->post('/login', ['email' => $user->email, 'password' => 'Clave#Segura2026']);
        $this->post('/login/2fa', ['code' => $code]);
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'Clave#Segura2026']);
        $this->post('/login/2fa', ['code' => $code])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_staff_without_two_factor_is_redirected_to_enable_it(): void
    {
        $this->actingAs($this->user('medico', 'active', false))->get('/dashboard')
            ->assertRedirect(route('two-factor.show'));
    }

    public function test_recovery_code_works_only_once(): void
    {
        $secret = app(TwoFactor::class)->generateSecret();
        $codes = app(TwoFactor::class)->generateRecoveryCodes(2);
        $user = $this->user('medico');
        $user->forceFill([
            'password' => bcrypt('Clave#Segura2026'),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes['hashed'],
        ])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'Clave#Segura2026']);
        $this->post('/login/2fa', ['recovery_code' => $codes['plain'][0]]);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'Clave#Segura2026']);
        $this->post('/login/2fa', ['recovery_code' => $codes['plain'][0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }
}
