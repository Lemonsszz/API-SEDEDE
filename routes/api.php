<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NoticiaController;
use App\Http\Controllers\ComunicadoController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\AuthController;

Route::get('/noticias', [NoticiaController::class, 'index']);
Route::get('/noticias/{slug}', [NoticiaController::class, 'show']);
Route::get('/comunicados', [ComunicadoController::class, 'index']);
Route::get('/comunicados/{id}', [ComunicadoController::class, 'show'])->whereNumber('id');
Route::get('/eventos', [EventoController::class, 'index']);

// Inicio de sesión
Route::post('/login', [AuthController::class, 'login']);

// Rutas para cualquier usuario autenticado
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/usuario', function (Request $request) {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    });
});

// Rutas exclusivas de administradores
Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->group(function () {
    Route::get('/noticias', [NoticiaController::class, 'adminIndex']);
    Route::post('/noticias', [NoticiaController::class, 'store']);
    Route::post('/noticias', [NoticiaController::class, 'store']);
    Route::put('/noticias/{id}', [NoticiaController::class, 'update']);
    Route::delete('/noticias/{id}', [NoticiaController::class, 'destroy']);
    Route::post('/noticias/subir-imagen', [NoticiaController::class, 'subirImagen']);

    Route::get('/comunicados', [ComunicadoController::class, 'adminIndex']);
    Route::post('/comunicados', [ComunicadoController::class, 'store']);
    Route::put('/comunicados/{id}', [ComunicadoController::class, 'update']);
    Route::delete('/comunicados/{id}', [ComunicadoController::class, 'destroy']);
    Route::post('/comunicados/subir-archivo', [ComunicadoController::class, 'subirArchivo']);
});