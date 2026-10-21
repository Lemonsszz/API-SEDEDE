<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('noticias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('resumen')->nullable();
            $table->text('contenido')->nullable();
            $table->string('imagen_url')->nullable();
            $table->string('categoria')->nullable();
            $table->timestampTz('fecha_publicacion')->nullable();
            $table->boolean('destacada')->default(false);
            $table->boolean('publicada')->default(false);
            $table->timestamps();
        });

        Schema::create('comunicados', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('resumen')->nullable();
            $table->text('contenido')->nullable();
            $table->string('archivo_url')->nullable();
            $table->timestampTz('fecha_publicacion')->nullable();
            $table->boolean('publicado')->default(false);
            $table->timestamps();
        });

        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('lugar')->nullable();
            $table->string('imagen_url')->nullable();
            $table->boolean('publicado')->default(false);
            $table->timestamps();
        });
    }

    private function crearUsuario(string $rol): User
    {
        return User::factory()->create(['rol' => $rol]);
    }

    private function tokenDe(User $usuario): string
    {
        $respuesta = $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $respuesta->assertOk();

        return $respuesta->json('token');
    }

    private function autorizacion(User $usuario): array
    {
        return ['Authorization' => 'Bearer '.$this->tokenDe($usuario)];
    }

    public function test_login_returns_token_for_admin(): void
    {
        $admin = $this->crearUsuario('admin');

        $respuesta = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $respuesta
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.rol', 'admin')
            ->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $admin = $this->crearUsuario('admin');

        $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'incorrecta',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_is_throttled_after_six_attempts(): void
    {
        $admin = $this->crearUsuario('admin');

        for ($intento = 1; $intento <= 6; $intento++) {
            $this->postJson('/api/login', [
                'email' => $admin->email,
                'password' => 'incorrecta',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'incorrecta',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_public_endpoints_only_return_published_content(): void
    {
        DB::table('noticias')->insert([
            [
                'titulo' => 'Publicada',
                'slug' => 'publicada',
                'publicada' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'titulo' => 'Borrador',
                'slug' => 'borrador',
                'publicada' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('comunicados')->insert([
            [
                'titulo' => 'Publicado',
                'publicado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'titulo' => 'Borrador',
                'publicado' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('eventos')->insert([
            [
                'titulo' => 'Publicado',
                'fecha' => '2026-12-01',
                'publicado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'titulo' => 'Borrador',
                'fecha' => '2026-12-02',
                'publicado' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/noticias')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Publicada');

        $this->getJson('/api/comunicados')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Publicado');

        $this->getJson('/api/eventos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Publicado');
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $usuario = $this->crearUsuario('usuario');

        $this->getJson('/api/admin/noticias', $this->autorizacion($usuario))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/noticias')->assertStatus(401);
    }

    public function test_admin_can_create_update_and_delete_noticia(): void
    {
        $admin = $this->crearUsuario('admin');
        $cabeceras = $this->autorizacion($admin);

        $primera = $this->postJson('/api/admin/noticias', [
            'titulo' => 'Título de prueba',
            'resumen' => 'Resumen',
            'contenido' => 'Contenido',
            'categoria' => 'Institucional',
            'publicada' => true,
        ], $cabeceras);

        $primera->assertCreated()->assertJsonPath('slug', 'titulo-de-prueba');

        $segunda = $this->postJson('/api/admin/noticias', [
            'titulo' => 'Título de prueba',
            'resumen' => 'Resumen 2',
            'contenido' => 'Contenido 2',
            'categoria' => 'Deportivo',
        ], $cabeceras);

        $segunda->assertCreated()->assertJsonPath('slug', 'titulo-de-prueba-2');

        $id = $primera->json('id');

        $this->putJson("/api/admin/noticias/{$id}", [
            'titulo' => 'Título actualizado',
        ], $cabeceras)->assertOk();

        $this->assertDatabaseHas('noticias', [
            'id' => $id,
            'slug' => 'titulo-actualizado',
        ]);

        $this->deleteJson("/api/admin/noticias/{$id}", [], $cabeceras)
            ->assertOk();

        $this->assertDatabaseMissing('noticias', ['id' => $id]);
    }

    public function test_admin_can_create_update_and_delete_evento(): void
    {
        $admin = $this->crearUsuario('admin');
        $cabeceras = $this->autorizacion($admin);

        $creado = $this->postJson('/api/admin/eventos', [
            'titulo' => 'Maratón',
            'descripcion' => 'Carrera departamental',
            'fecha' => '2026-12-01',
            'hora' => '08:30',
            'lugar' => 'Oruro',
            'publicado' => true,
        ], $cabeceras);

        $creado->assertCreated();

        $id = $creado->json('id');

        $this->assertDatabaseHas('eventos', [
            'id' => $id,
            'titulo' => 'Maratón',
        ]);

        $this->putJson("/api/admin/eventos/{$id}", [
            'titulo' => 'Maratón actualizada',
            'lugar' => 'Estadio Jesús Bermúdez',
        ], $cabeceras)->assertOk();

        $this->assertDatabaseHas('eventos', [
            'id' => $id,
            'titulo' => 'Maratón actualizada',
        ]);

        $this->deleteJson("/api/admin/eventos/{$id}", [], $cabeceras)
            ->assertOk();

        $this->assertDatabaseMissing('eventos', ['id' => $id]);
    }

    public function test_evento_rejects_invalid_hour(): void
    {
        $admin = $this->crearUsuario('admin');

        $this->postJson('/api/admin/eventos', [
            'titulo' => 'Evento inválido',
            'fecha' => '2026-12-01',
            'hora' => '25:99',
        ], $this->autorizacion($admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora');
    }
}
