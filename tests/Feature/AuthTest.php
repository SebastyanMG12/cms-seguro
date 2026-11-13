<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas automatizadas de autenticación y autorización (Sección 8 de la Guía MEDIT)
 * Cubre:
 * - 8.2 Disponibilidad de página de Login
 * - 8.3 Creación/Registro seguro de usuario y aserción en base de datos
 * - 8.4 Protección de ruta privada /dashboard que requiere autenticación
 * - 8.5 Control de acceso basado en roles (RBAC) con retorno 403 Forbidden
 * - 9.0 Aislamiento y persistencia segura con RefreshDatabase
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * 8.2 Login: Verifica que la página de login sea accesible públicamente.
     */
    public function test_login_page_is_available(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Iniciar sesión');
    }

    /**
     * 8.3 Registro / Creación de Usuario:
     * En el CMS hospitalario, el registro público (/register) está cerrado por diseño seguro.
     * La creación de cuentas la realiza el Administrador en /admin/usuarios.
     */
    public function test_admin_can_register_user_and_persists_in_database(): void
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'two_factor_secret' => app(TwoFactor::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole('administrador');

        $response = $this->actingAs($admin)->post('/admin/usuarios', [
            'name' => 'Usuario Prueba Medit',
            'email' => 'prueba.medit@hospitalubate.gov.co',
            'roles' => ['autor'],
            'password' => 'Password123#Segura',
            'password_confirmation' => 'Password123#Segura',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'prueba.medit@hospitalubate.gov.co',
            'name' => 'Usuario Prueba Medit',
        ]);
    }

    /**
     * 8.4 Ruta privada: Verifica que acceder a /dashboard sin sesión redirija a /login.
     */
    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * 8.5 Autorización: Verifica que un usuario sin rol administrativo reciba 403 Forbidden.
     */
    public function test_user_without_permission_gets_403(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('autor');

        $response = $this->actingAs($user)->get('/admin/usuarios');

        $response->assertForbidden();
    }

    /**
     * 8.3 & Política de Seguridad: Verifica que el auto-registro no autorizado esté bloqueado (404 Not Found).
     */
    public function test_public_registration_endpoint_is_disabled_for_security(): void
    {
        $response = $this->get('/register');
        $response->assertNotFound();

        $postResponse = $this->post('/register', [
            'name' => 'Hacker Test',
            'email' => 'hacker@malicious.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);
        $postResponse->assertNotFound();
    }
}
