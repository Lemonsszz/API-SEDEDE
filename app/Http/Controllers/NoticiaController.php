<?php

namespace App\Http\Controllers;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NoticiaController extends Controller
{
    private const CLAVE_LISTADO = 'noticias:publicadas';

    private const CLAVE_ADMIN = 'noticias:admin';

    // Consultar noticias publicadas (público)
    public function index(): JsonResponse
    {
        $noticias = Cache::remember(self::CLAVE_LISTADO, 60, function () {
            return DB::table('noticias')
                ->where('publicada', true)
                ->orderByDesc('fecha_publicacion')
                ->get([
                    'id',
                    'titulo',
                    'slug',
                    'resumen',
                    'contenido',
                    'imagen_url',
                    'categoria',
                    'fecha_publicacion',
                    'destacada',
                ]);
        });

        return response()->json([
            'success' => true,
            'data' => $noticias,
        ]);
    }

    // Listar todas las noticias para el panel administrativo
    public function adminIndex(): JsonResponse
    {
        $noticias = Cache::remember(self::CLAVE_ADMIN, 30, function () {
            return DB::table('noticias')
                ->orderByDesc('fecha_publicacion')
                ->get([
                    'id',
                    'titulo',
                    'slug',
                    'resumen',
                    'contenido',
                    'imagen_url',
                    'categoria',
                    'fecha_publicacion',
                    'destacada',
                    'publicada',
                ]);
        });

        return response()->json([
            'success' => true,
            'data' => $noticias,
        ]);
    }

    // Crear noticia (administrador)
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'resumen' => ['required', 'string'],
            'contenido' => ['required', 'string'],
            'imagen_url' => ['nullable', 'url', 'max:2048'],
            'categoria' => ['required', 'string', 'max:100'],
            'fecha_publicacion' => ['nullable', 'date'],
            'destacada' => ['sometimes', 'boolean'],
            'publicada' => ['sometimes', 'boolean'],
        ]);

        [$id, $slug] = $this->guardarConSlugUnico(function () use ($datos) {
            $slug = $this->generarSlug($datos['titulo']);
            $ahora = now();

            $id = DB::table('noticias')->insertGetId([
                'titulo' => $datos['titulo'],
                'slug' => $slug,
                'resumen' => $datos['resumen'],
                'contenido' => $datos['contenido'],
                'imagen_url' => $datos['imagen_url'] ?? null,
                'categoria' => $datos['categoria'],
                'fecha_publicacion' => $datos['fecha_publicacion'] ?? $ahora,
                'destacada' => $datos['destacada'] ?? false,
                'publicada' => $datos['publicada'] ?? false,
            ]);

            return [$id, $slug];
        });

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);

        return response()->json([
            'success' => true,
            'message' => 'Noticia creada correctamente.',
            'id' => $id,
            'slug' => $slug,
        ], 201);
    }

    // Editar noticia (administrador)
    public function update(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'resumen' => ['sometimes', 'required', 'string'],
            'contenido' => ['sometimes', 'required', 'string'],
            'imagen_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'categoria' => ['sometimes', 'required', 'string', 'max:100'],
            'fecha_publicacion' => ['sometimes', 'nullable', 'date'],
            'destacada' => ['sometimes', 'boolean'],
            'publicada' => ['sometimes', 'boolean'],
        ]);

        if (! DB::table('noticias')->where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Noticia no encontrada.',
            ], 404);
        }

        $this->guardarConSlugUnico(function () use ($datos, $id) {
            if (isset($datos['titulo'])) {
                $datos['slug'] = $this->generarSlug($datos['titulo'], $id);
            }

            return DB::table('noticias')
                ->where('id', $id)
                ->update($datos);
        });

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);

        return response()->json([
            'success' => true,
            'message' => 'Noticia actualizada correctamente.',
        ]);
    }

    // Eliminar noticia (administrador)
    public function destroy(int $id): JsonResponse
    {
        $eliminada = DB::table('noticias')->where('id', $id)->delete();

        if (! $eliminada) {
            return response()->json([
                'success' => false,
                'message' => 'Noticia no encontrada.',
            ], 404);
        }

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);

        return response()->json([
            'success' => true,
            'message' => 'Noticia eliminada correctamente.',
        ]);
    }

    /**
     * Consultar una noticia publicada (público).
     *
     * Sin caché a propósito: Next.js lo llama con `cache: "no-store"` desde la
     * página de detalle, y una respuesta cacheada haría parecer que la noticia
     * no existe.
     */
    public function show(string $slug)
    {
        $noticia = DB::table('noticias')
            ->where('slug', $slug)
            ->where('publicada', true)
            ->first();

        if (! $noticia) {
            return response()->json([
                'success' => false,
                'message' => 'Noticia no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'noticia' => $noticia,
        ]);
    }

    public function subirImagen(Request $request): JsonResponse
    {
        $request->validate([
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:15360',
        ]);

        try {
            $resultado = Cloudinary::uploadApi()->upload(
                $request->file('imagen')->getRealPath(),
                [
                    'folder' => 'sedede/noticias',
                ]
            );

            return response()->json([
                'success' => true,
                'url' => $resultado['secure_url'],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error al subir imagen a Cloudinary: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo subir la imagen.',
            ], 500);
        }
    }

    /**
     * Genera un slug único para una noticia.
     */
    private function generarSlug(string $titulo, ?int $ignorarId = null): string
    {
        $slugBase = Str::slug($titulo);

        if ($slugBase === '') {
            $slugBase = 'noticia';
        }

        $slug = $slugBase;
        $contador = 2;

        while (
            DB::table('noticias')
                ->where('slug', $slug)
                ->when(
                    $ignorarId !== null,
                    fn ($consulta) => $consulta->where('id', '!=', $ignorarId)
                )
                ->exists()
        ) {
            $slug = $slugBase.'-'.$contador++;
        }

        return $slug;
    }

    /**
     * Ejecuta la operación en una transacción y reintenta si el slug choca.
     */
    private function guardarConSlugUnico(callable $operacion): mixed
    {
        $intentos = 0;

        while (true) {
            try {
                return DB::transaction($operacion);
            } catch (QueryException $excepcion) {
                $intentos++;

                if ($intentos >= 3 || ! $this->esColisionDeSlug($excepcion)) {
                    throw $excepcion;
                }
            }
        }
    }

    private function esColisionDeSlug(QueryException $excepcion): bool
    {
        return in_array((string) $excepcion->getCode(), ['23505', '23000'], true)
            && str_contains($excepcion->getMessage(), 'slug');
    }
}
