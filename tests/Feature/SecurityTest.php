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
        $response = $this->get('/');
        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);

        preg_match('/script-src\s+([^;]+)/', $csp, $matches);
        $this->assertStringNotContainsString("'unsafe-inline'", $matches[1] ?? '');
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

    public function test_admin_can_update_user_and_roles(): void
    {
        $admin = $this->user('administrador');
        $target = $this->user('autor');

        $payload = [
            'name' => 'Target Modificado',
            'email' => 'target.mod@x.test',
            'roles' => ['editor', 'autor'],
        ];

        $this->actingAs($admin)
            ->put("/admin/usuarios/{$target->id}", $payload)
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame('Target Modificado', $target->name);
        $this->assertSame('target.mod@x.test', $target->email);
        $this->assertTrue($target->hasAllRoles(['editor', 'autor']));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_updated',
            'model_type' => 'User',
            'model_id' => $target->id,
        ]);
    }

    public function test_user_cannot_update_own_roles(): void
    {
        $admin = $this->user('administrador');

        $payload = [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => ['autor'],
        ];

        $this->actingAs($admin)
            ->put("/admin/usuarios/{$admin->id}", $payload)
            ->assertSessionHasErrors('roles');

        $admin->refresh();
        $this->assertTrue($admin->hasRole('administrador'));
    }

    public function test_cannot_remove_role_from_last_active_admin(): void
    {
        $admin = $this->user('administrador');
        $otherAdmin = $this->user('administrador');

        // Segundo admin gestiona al primero pero intenta quitarle el rol admin dejando sólo 1
        // Primero suspendemos al segundo admin para que quede sólo 1 admin activo
        $otherAdmin->forceFill(['status' => 'suspended'])->save();

        // Le damos permiso usuarios.gestionar a un usuario autor para que intente editar al único admin activo
        $manager = $this->user('autor');
        $manager->givePermissionTo('usuarios.gestionar');

        $this->actingAs($manager)
            ->put("/admin/usuarios/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => ['autor'],
            ])
            ->assertSessionHasErrors('roles');

        $admin->refresh();
        $this->assertTrue($admin->hasRole('administrador'));
    }

    public function test_admin_can_suspend_and_reactivate_user_with_reason(): void
    {
        $admin = $this->user('administrador');
        $target = $this->user('autor');

        DB::table('sessions')->insert([
            'id' => 'sesion-target-activo',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->post("/admin/usuarios/{$target->id}/suspender", [
                'reason' => 'Incumplimiento de normas internas del hospital',
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('suspended', $target->status);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-target-activo']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_suspended',
            'model_id' => $target->id,
        ]);

        $this->actingAs($admin)
            ->post("/admin/usuarios/{$target->id}/reactivar", [
                'reason' => 'Levantamiento de suspensión tras revisión',
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame('active', $target->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_reactivated',
            'model_id' => $target->id,
        ]);
    }

    public function test_user_cannot_suspend_self(): void
    {
        $admin = $this->user('administrador');

        $this->actingAs($admin)
            ->post("/admin/usuarios/{$admin->id}/suspender", [
                'reason' => 'Intento de auto suspensión',
            ])
            ->assertForbidden();

        $admin->refresh();
        $this->assertSame('active', $admin->status);
    }

    public function test_cannot_suspend_last_active_admin(): void
    {
        $admin = $this->user('administrador');

        $manager = $this->user('autor');
        $manager->givePermissionTo('usuarios.gestionar');

        $this->actingAs($manager)
            ->post("/admin/usuarios/{$admin->id}/suspender", [
                'reason' => 'Intento de suspender al único admin',
            ])
            ->assertSessionHasErrors('user');

        $admin->refresh();
        $this->assertSame('active', $admin->status);
    }

    public function test_admin_can_reset_two_factor_with_reason(): void
    {
        $admin = $this->user('administrador');
        $target = $this->user('medico', 'active', true);

        $this->assertTrue($target->hasTwoFactorEnabled());

        $this->actingAs($admin)
            ->post("/admin/usuarios/{$target->id}/restablecer-2fa", [
                'reason' => 'Médico perdió su teléfono y requiere reconfiguración',
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertFalse($target->hasTwoFactorEnabled());
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_last_timestep);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'two_factor_reset',
            'model_id' => $target->id,
        ]);
    }

    public function test_admin_can_soft_delete_user(): void
    {
        $admin = $this->user('administrador');
        $target = $this->user('autor');

        DB::table('sessions')->insert([
            'id' => 'sesion-target-delete',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->delete("/admin/usuarios/{$target->id}")
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull(User::find($target->id));
        $this->assertNotNull(User::withTrashed()->find($target->id)?->deleted_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-target-delete']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_deleted',
            'model_id' => $target->id,
        ]);
    }

    public function test_user_cannot_delete_self(): void
    {
        $admin = $this->user('administrador');

        $this->actingAs($admin)
            ->delete("/admin/usuarios/{$admin->id}")
            ->assertForbidden();

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_cannot_delete_last_active_admin(): void
    {
        $admin = $this->user('administrador');

        $manager = $this->user('autor');
        $manager->givePermissionTo('usuarios.gestionar');

        $this->actingAs($manager)
            ->delete("/admin/usuarios/{$admin->id}")
            ->assertSessionHasErrors('user');

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_authenticated_user_can_change_password_and_close_other_sessions(): void
    {
        $user = $this->user('medico');
        $user->forceFill(['password' => Hash::make('Clave#Inicial2026')])->save();

        // Creamos una sesión "otra" en la base de datos
        DB::table('sessions')->insert([
            'id' => 'otra-sesion-remota',
            'user_id' => $user->id,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'OtherBrowser',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($user)
            ->put('/seguridad/password', [
                'current_password' => 'Clave#Inicial2026',
                'password' => 'Nueva#Clave2026!A',
                'password_confirmation' => 'Nueva#Clave2026!A',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('Nueva#Clave2026!A', $user->password));

        // La sesión remota fue eliminada
        $this->assertDatabaseMissing('sessions', ['id' => 'otra-sesion-remota']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'password_changed',
            'model_id' => $user->id,
        ]);
    }

    public function test_change_password_rejects_invalid_current_password(): void
    {
        $user = $this->user('medico');
        $user->forceFill(['password' => Hash::make('Clave#Inicial2026')])->save();

        $this->actingAs($user)
            ->put('/seguridad/password', [
                'current_password' => 'Clave#Incorrecta123',
                'password' => 'Nueva#Clave2026!A',
                'password_confirmation' => 'Nueva#Clave2026!A',
            ])
            ->assertSessionHasErrors('current_password');

        $user->refresh();
        $this->assertTrue(Hash::check('Clave#Inicial2026', $user->password));
    }

    public function test_admin_can_reset_user_password_with_reason_and_terminate_sessions(): void
    {
        $admin = $this->user('administrador');
        $target = $this->user('medico');

        DB::table('sessions')->insert([
            'id' => 'sesion-activa-medico',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestBrowser',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($admin)
            ->post("/admin/usuarios/{$target->id}/restablecer-contrasena", [
                'password' => 'NuevaPassword123#',
                'password_confirmation' => 'NuevaPassword123#',
                'reason' => 'Olvido de contraseña reportado presencialmente',
            ]);

        $response->assertRedirect()->assertSessionHas('success');

        $target->refresh();
        $this->assertTrue(Hash::check('NuevaPassword123#', $target->password));

        // Sesión remota cerrada
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-activa-medico']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user_password_reset_by_admin',
            'model_id' => $target->id,
            'user_id' => $admin->id,
        ]);
    }
}
