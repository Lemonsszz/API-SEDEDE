<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComunicadoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\NoticiaController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/noticias', [NoticiaController::class, 'index']);
Route::get('/noticias/{slug}', [NoticiaController::class, 'show']);
Route::get('/comunicados', [ComunicadoController::class, 'index']);
Route::get('/comunicados/{id}', [ComunicadoController::class, 'show'])->whereNumber('id');
Route::get('/eventos', [EventoController::class, 'index']);

// Inicio de sesión (máximo 6 intentos por minuto)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

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
    Route::put('/noticias/{id}', [NoticiaController::class, 'update'])->whereNumber('id');
    Route::delete('/noticias/{id}', [NoticiaController::class, 'destroy'])->whereNumber('id');
    Route::post('/noticias/subir-imagen', [NoticiaController::class, 'subirImagen']);

    Route::get('/comunicados', [ComunicadoController::class, 'adminIndex']);
    Route::post('/comunicados', [ComunicadoController::class, 'store']);
    Route::put('/comunicados/{id}', [ComunicadoController::class, 'update'])->whereNumber('id');
    Route::delete('/comunicados/{id}', [ComunicadoController::class, 'destroy'])->whereNumber('id');
    Route::post('/comunicados/subir-archivo', [ComunicadoController::class, 'subirArchivo']);

    Route::get('/eventos', [EventoController::class, 'adminIndex']);
    Route::post('/eventos', [EventoController::class, 'store']);
    Route::put('/eventos/{id}', [EventoController::class, 'update'])->whereNumber('id');
    Route::delete('/eventos/{id}', [EventoController::class, 'destroy'])->whereNumber('id');
});
