<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EventoController extends Controller
{
    public function index(): JsonResponse
    {
        $eventos = DB::table('eventos')
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

        return response()->json([
            'success' => true,
            'data' => $eventos,
        ]);
    }
}

