<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NoticiaController extends Controller
{
    // Consultar noticias publicadas (público)
    public function index(): JsonResponse
    {
        $noticias = DB::table('noticias')
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

        return response()->json([
            'success' => true,
            'data' => $noticias,
        ]);
    }
    
    // Listar todas las noticias para el panel administrativo
    public function adminIndex(): JsonResponse
    {
        $noticias = DB::table('noticias')
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

        $slugBase = Str::slug($datos['titulo']);
        $slug = $slugBase;
        $contador = 2;

        while (DB::table('noticias')->where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $contador++;
        }

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

        if (isset($datos['titulo'])) {
            $slugBase = Str::slug($datos['titulo']);
            $slug = $slugBase;
            $contador = 2;

            while (
                DB::table('noticias')
                    ->where('slug', $slug)
                    ->where('id', '!=', $id)
                    ->exists()
            ) {
                $slug = $slugBase . '-' . $contador++;
            }

            $datos['slug'] = $slug;
        }

        $actualizado = DB::table('noticias')
            ->where('id', $id)
            ->update($datos);

        if (!$actualizado && !DB::table('noticias')->where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Noticia no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Noticia actualizada correctamente.',
        ]);
    }

    // Eliminar noticia (administrador)
    public function destroy(int $id): JsonResponse
    {
        $eliminada = DB::table('noticias')->where('id', $id)->delete();

        if (!$eliminada) {
            return response()->json([
                'success' => false,
                'message' => 'Noticia no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Noticia eliminada correctamente.',
        ]);
    }
    public function show(string $slug)
{
    $noticia = \Illuminate\Support\Facades\DB::table('noticias')
        ->where('slug', $slug)
        ->where('publicada', true)
        ->first();

    if (!$noticia) {
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
        $resultado = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload(
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
}