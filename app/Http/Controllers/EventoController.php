<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EventoController extends Controller
{
    private const CLAVE_LISTADO = 'eventos:publicados';

    private const CLAVE_ADMIN = 'eventos:admin';

    // Consultar eventos publicados (público)
    public function index(): JsonResponse
    {
        $eventos = Cache::remember(self::CLAVE_LISTADO, 60, function () {
            return DB::table('eventos')
                ->where('publicado', true)
                ->orderBy('fecha')
                ->get([
                    'id',
                    'titulo',
                    'descripcion',
                    'fecha',
                    'hora',
                    'lugar',
                    'imagen_url',
                ]);
        });

        return response()->json([
            'success' => true,
            'data' => $eventos,
        ]);
    }

    // Listar todos los eventos para el panel administrativo
    public function adminIndex(): JsonResponse
    {
        $eventos = Cache::remember(self::CLAVE_ADMIN, 30, function () {
            return DB::table('eventos')
                ->orderByDesc('fecha')
                ->get([
                    'id',
                    'titulo',
                    'descripcion',
                    'fecha',
                    'hora',
                    'lugar',
                    'imagen_url',
                    'publicado',
                ]);
        });

        return response()->json([
            'success' => true,
            'data' => $eventos,
        ]);
    }

    // Crear evento (administrador)
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'fecha' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'lugar' => ['nullable', 'string', 'max:255'],
            'imagen_url' => ['nullable', 'url', 'max:2048'],
            'publicado' => ['sometimes', 'boolean'],
        ]);

        $id = DB::table('eventos')->insertGetId([
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'fecha' => $datos['fecha'],
            'hora' => $datos['hora'] ?? null,
            'lugar' => $datos['lugar'] ?? null,
            'imagen_url' => $datos['imagen_url'] ?? null,
            'publicado' => $datos['publicado'] ?? false,
        ]);

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);

        return response()->json([
            'success' => true,
            'message' => 'Evento creado correctamente.',
            'id' => $id,
        ], 201);
    }

    // Editar evento (administrador)
    public function update(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'fecha' => ['sometimes', 'required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'lugar' => ['nullable', 'string', 'max:255'],
            'imagen_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'publicado' => ['sometimes', 'boolean'],
        ]);

        if (! DB::table('eventos')->where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Evento no encontrado.',
            ], 404);
        }

        DB::table('eventos')
            ->where('id', $id)
            ->update($datos);

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);

        return response()->json([
            'success' => true,
            'message' => 'Evento actualizado correctamente.',
        ]);
    }

    // Eliminar evento (administrador)
    public function destroy(int $id): JsonResponse
    {
        $eliminado = DB::table('eventos')->where('id', $id)->delete();

        if (! $eliminado) {
            return response()->json([
                'success' => false,
                'message' => 'Evento no encontrado.',
            ], 404);
        }

        Cache::forget(self::CLAVE_LISTADO);
        Cache::forget(self::CLAVE_ADMIN);

        return response()->json([
            'success' => true,
            'message' => 'Evento eliminado correctamente.',
        ]);
    }
}
