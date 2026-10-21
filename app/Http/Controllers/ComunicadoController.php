<?php

namespace App\Http\Controllers;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ComunicadoController extends Controller
{
    private const CLAVE_LISTADO = 'comunicados:publicados';

    private const CLAVE_ADMIN = 'comunicados:admin';

    // Consultar comunicados publicados (público)
    public function index(): JsonResponse
    {
        $comunicados = Cache::remember(self::CLAVE_LISTADO, 60, function () {
            return DB::table('comunicados')
                ->where('publicado', true)
                ->orderByDesc('fecha_publicacion')
                ->get([
                    'id',
                    'titulo',
                    'resumen',
                    'contenido',
                    'archivo_url',
                    'fecha_publicacion',
                ]);
        });

        return response()->json([
            'success' => true,
            'data' => $comunicados,
        ]);
    }

    // Consultar un comunicado publicado (público)
    public function show(int $id): JsonResponse
    {
        $comunicado = Cache::remember("comunicados:{$id}", 60, function () use ($id) {
            return DB::table('comunicados')
                ->where('id', $id)
                ->where('publicado', true)
                ->first([
                    'id',
                    'titulo',
                    'resumen',
                    'contenido',
                    'archivo_url',
                    'fecha_publicacion',
                ]);
        });

        if (! $comunicado) {
            return response()->json([
                'success' => false,
                'message' => 'Comunicado no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'comunicado' => $comunicado,
        ]);
    }

    // Listar todos los comunicados para el panel administrativo
    public function adminIndex(): JsonResponse
    {
        $comunicados = Cache::remember(self::CLAVE_ADMIN, 30, function () {
            return DB::table('comunicados')
                ->orderByDesc('fecha_publicacion')
                ->get([
                    'id',
                    'titulo',
                    'resumen',
                    'contenido',
                    'archivo_url',
                    'fecha_publicacion',
                    'publicado',
                ]);
        });

        return response()->json([
            'success' => true,
            'data' => $comunicados,
        ]);
    }

    // Crear comunicado (administrador)
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'resumen' => ['required', 'string'],
            'contenido' => ['nullable', 'string'],
            'archivo_url' => ['nullable', 'url', 'max:2048'],
            'publicado' => ['sometimes', 'boolean'],
        ]);

        $id = DB::table('comunicados')->insertGetId([
            'titulo' => $datos['titulo'],
            'resumen' => $datos['resumen'],
            'contenido' => $datos['contenido'] ?? null,
            'archivo_url' => $datos['archivo_url'] ?? null,
            'fecha_publicacion' => now(),
            'publicado' => $datos['publicado'] ?? false,
        ]);

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);

        return response()->json([
            'success' => true,
            'message' => 'Comunicado creado correctamente.',
            'id' => $id,
        ], 201);
    }

    // Editar comunicado (administrador)
    public function update(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'resumen' => ['sometimes', 'required', 'string'],
            'contenido' => ['nullable', 'string'],
            'archivo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'publicado' => ['sometimes', 'boolean'],
        ]);

        if (! DB::table('comunicados')->where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Comunicado no encontrado.',
            ], 404);
        }

        DB::table('comunicados')
            ->where('id', $id)
            ->update($datos);

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);
        Cache::forget("comunicados:{$id}");

        return response()->json([
            'success' => true,
            'message' => 'Comunicado actualizado correctamente.',
        ]);
    }

    // Eliminar comunicado (administrador)
    public function destroy(int $id): JsonResponse
    {
        $eliminado = DB::table('comunicados')->where('id', $id)->delete();

        if (! $eliminado) {
            return response()->json([
                'success' => false,
                'message' => 'Comunicado no encontrado.',
            ], 404);
        }

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);
        Cache::forget("comunicados:{$id}");

        return response()->json([
            'success' => true,
            'message' => 'Comunicado eliminado correctamente.',
        ]);
    }

    // Subir documento adjunto a Cloudinary (administrador)
    public function subirArchivo(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:15360',
        ]);

        try {
            $archivo = $request->file('archivo');
            $nombreOriginal = $archivo->getClientOriginalName();

            $resultado = Cloudinary::uploadApi()->upload(
                $archivo->getRealPath(),
                [
                    'folder' => 'sedede/comunicados',
                    'resource_type' => 'raw',
                    'filename' => $nombreOriginal,
                    'use_filename' => true,
                    'unique_filename' => true,
                ]
            );

            $url = $resultado['secure_url'];

            // Nombre amigable para la descarga (fl_attachment conserva la extensión original)
            $nombreSinExtension = pathinfo($nombreOriginal, PATHINFO_FILENAME);
            $nombreSeguro = preg_replace('/[^A-Za-z0-9_\- ]/', '_', $nombreSinExtension);

            if (! empty($nombreSeguro)) {
                $url = preg_replace(
                    '#/raw/upload/#',
                    '/raw/upload/fl_attachment:'.rawurlencode($nombreSeguro).'/',
                    $url,
                    1
                );
            }

            return response()->json([
                'success' => true,
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error al subir archivo a Cloudinary: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo subir el archivo.',
            ], 500);
        }
    }
}
