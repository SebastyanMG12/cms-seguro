<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsAndPublicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createActiveUser(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'two_factor_confirmed_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_public_news_index_only_displays_published_articles(): void
    {
        $author = $this->createActiveUser('editor');

        $author->posts()->create([
            'title' => 'Campaña de Vacunación Hospital de Ubaté',
            'slug' => 'campana-vacunacion-hospital-ubate',
            'status' => 'published',
            'body' => 'Se inicia la jornada masiva de vacunación en el municipio de Ubaté.',
        ]);

        $author->posts()->create([
            'title' => 'Borrador Confidencial Interno',
            'slug' => 'borrador-confidencial-interno',
            'status' => 'draft',
            'body' => 'Contenido preliminar en revisión que no debe ser público.',
        ]);

        $response = $this->get(route('news.index'));

        $response->assertStatus(200);
        $response->assertSee('Campaña de Vacunación Hospital de Ubaté');
        $response->assertDontSee('Borrador Confidencial Interno');
    }

    public function test_public_news_detail_returns_ok_for_published_and_404_for_draft_or_missing(): void
    {
        $author = $this->createActiveUser('editor');

        $publishedPost = $author->posts()->create([
            'title' => 'Nuevo Horario de Atención Consulta Externa',
            'slug' => 'nuevo-horario-atencion-consulta-externa',
            'status' => 'published',
            'body' => 'A partir del próximo lunes el horario se extenderá hasta las 18:00 horas.',
        ]);

        $draftPost = $author->posts()->create([
            'title' => 'Protocolo No Publicado',
            'slug' => 'protocolo-no-publicado',
            'status' => 'draft',
            'body' => 'Este borrador no debe ser accesible por URL pública.',
        ]);

        // Publicado debe responder 200
        $this->get(route('news.show', $publishedPost->slug))
            ->assertStatus(200)
            ->assertSee('Nuevo Horario de Atención Consulta Externa')
            ->assertSee('A partir del próximo lunes el horario se extenderá hasta las 18:00 horas.');

        // Borrador por slug debe responder 404 (ocultamiento estricto)
        $this->get(route('news.show', $draftPost->slug))
            ->assertStatus(404);

        // Slug inexistente debe responder 404
        $this->get(route('news.show', 'slug-totalmente-inexistente'))
            ->assertStatus(404);
    }

    public function test_public_news_escapes_html_body_preventing_stored_xss(): void
    {
        $author = $this->createActiveUser('editor');

        $xssPayload = '<script>alert("XSS-ATTACK")</script><img src="x" onerror="alert(1)">';
        $safeText = 'Párrafo con texto normal e informativo.';

        $post = $author->posts()->create([
            'title' => 'Comunicado Sobre Medidas Sanitarias',
            'slug' => 'comunicado-medidas-sanitarias',
            'status' => 'published',
            'body' => $safeText . "\n" . $xssPayload,
        ]);

        $response = $this->get(route('news.show', $post->slug));

        $response->assertStatus(200);
        // Debe escapar el script y la etiqueta img
        $response->assertDontSee('<script>alert("XSS-ATTACK")</script>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;XSS-ATTACK&quot;)&lt;/script&gt;', false);
    }

    public function test_editor_or_admin_can_publish_directly_and_generates_audit_log(): void
    {
        $admin = $this->createActiveUser('administrador');

        $response = $this->actingAs($admin)->post(route('posts.store'), [
            'title' => 'Comunicado Oficial de la Gerencia',
            'body' => 'La gerencia del Hospital de Ubaté presenta el balance de gestión anual.',
            'status' => 'published',
        ]);

        $response->assertRedirect();
        $post = Post::where('title', 'Comunicado Oficial de la Gerencia')->first();
        $this->assertNotNull($post);
        $this->assertEquals('published', $post->status);

        // Verificar registro de auditoría
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'post_published',
            'model_type' => 'Post',
            'model_id' => (string) $post->id,
        ]);
    }

    public function test_author_without_publish_permission_cannot_publish_directly(): void
    {
        // Creamos un usuario que solo tiene rol de autor (publicaciones.ver y publicaciones.crear, no publicaciones.publicar)
        $author = $this->createActiveUser('autor');

        $response = $this->actingAs($author)->post(route('posts.store'), [
            'title' => 'Intento de Noticia sin Aprobación',
            'body' => 'Este artículo intenta ser publicado sin permiso de publicación institucional.',
            'status' => 'published',
        ]);

        // Debe fallar la validación en status
        $response->assertSessionHasErrors(['status']);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Intento de Noticia sin Aprobación',
        ]);

        // Pero sí puede guardarlo como borrador
        $draftResponse = $this->actingAs($author)->post(route('posts.store'), [
            'title' => 'Propuesta de Noticia en Borrador',
            'body' => 'Este artículo queda en borrador a la espera de revisión editorial.',
            'status' => 'draft',
        ]);

        $draftResponse->assertRedirect();
        $draftPost = Post::where('title', 'Propuesta de Noticia en Borrador')->first();
        $this->assertNotNull($draftPost);
        $this->assertEquals('draft', $draftPost->status);
    }

    public function test_unauthorized_roles_cannot_access_post_management(): void
    {
        $unauthorizedUser = $this->createActiveUser('enfermero');

        // Intentar ver el índice de administración de posts
        $this->actingAs($unauthorizedUser)
            ->get(route('posts.index'))
            ->assertStatus(403);

        // Intentar crear un post
        $this->actingAs($unauthorizedUser)
            ->get(route('posts.create'))
            ->assertStatus(403);

        $this->actingAs($unauthorizedUser)
            ->post(route('posts.store'), [
                'title' => 'Publicación Ilegítima',
                'body' => 'Texto que no debe ser guardado por rol no autorizado.',
                'status' => 'draft',
            ])
            ->assertStatus(403);
    }
}
