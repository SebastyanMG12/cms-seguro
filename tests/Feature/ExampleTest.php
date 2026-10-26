<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_home_page_displays_hospital_de_ubate_institutional_content_and_triage(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Hospital de Ubaté');
        $response->assertSee('Resolución 5596 de 2015');
        $response->assertSee('Triage I');
        $response->assertSee('Triage V');
        $response->assertDontSee('CMS Seguro');
    }

    public function test_about_page_displays_regulatory_and_ethical_framework(): void
    {
        $response = $this->get('/acerca');

        $response->assertStatus(200);
        $response->assertSee('Hospital de Ubaté');
        $response->assertSee('Resolución 1995 de 1999');
        $response->assertSee('Ley 1581 de 2012');
        $response->assertDontSee('CMS Seguro');
    }

    public function test_contact_page_loads_with_hospital_branding(): void
    {
        $response = $this->get('/contacto');

        $response->assertStatus(200);
        $response->assertSee('Hospital de Ubaté');
        $response->assertSee('Contacto y Atención al Usuario');
        $response->assertDontSee('CMS Seguro');
    }

    public function test_companion_link_is_visible_for_guests_in_navbar(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('companion.login'));
        $response->assertSee('Acompañantes');
        $response->assertSee(route('login'));
    }

    public function test_companion_and_contact_links_are_hidden_for_authenticated_staff_in_navbar(): void
    {
        $doctor = User::factory()->create([
            'email' => 'doctor.navbar@hospital.test',
            'status' => 'active',
            'two_factor_confirmed_at' => now(),
        ]);
        $doctor->assignRole('medico');

        $response = $this->actingAs($doctor)->get('/');

        $response->assertStatus(200);
        // Los enlaces de contacto ciudadano y acompañantes no se muestran al personal autenticado
        $response->assertDontSee(route('companion.login'));
        $response->assertDontSee('href="'.route('contact.create').'"', false);
        $response->assertSee('Cerrar sesión');
        $response->assertSee(route('dashboard'));
    }

    public function test_authenticated_doctor_sees_pre_registration_and_nn_actions_in_urgencias(): void
    {
        $doctor = User::factory()->create([
            'email' => 'doctor.urgencias@hospital.test',
            'status' => 'active',
            'two_factor_confirmed_at' => now(),
        ]);
        $doctor->assignRole('medico');

        $response = $this->actingAs($doctor)->get(route('episodes.index'));

        $response->assertStatus(200);
        $response->assertSee(route('preregistro.create'));
        $response->assertSee('Nuevo Pre-registro');
        $response->assertSee(route('episodes.nn.create'));
        $response->assertSee('Registrar Paciente NN');
    }

    public function test_admin_route_redirects_to_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.redirect@hospital.test',
            'status' => 'active',
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole('administrador');

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_documentation_page_loads_with_technical_markdown_content(): void
    {
        $response = $this->get(route('documentation'));

        $response->assertStatus(200);
        $response->assertSee('Documentación Oficial del Sistema');
        $response->assertSee('php artisan hospital:docs');
        $response->assertSee('Hospital de Ubaté');
    }
}
