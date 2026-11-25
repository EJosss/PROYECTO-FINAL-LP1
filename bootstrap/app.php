<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\VerificarRol;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    // 1. Rutas principales del sistema (web, comandos Artisan y health check interno)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // 2. Middlewares ("guardias") globales, redirecciones de sesión y alias de roles
    ->withMiddleware(function (Middleware $middleware): void {
        // Inyecta cabeceras HTTP de seguridad (CSP, HSTS, X-Frame-Options, etc.) en todas las respuestas
        $middleware->append(CabecerasSeguridad::class);

        // A dónde redirigir si un visitante sin sesión intenta entrar a una ruta protegida
        $middleware->redirectGuestsTo('/admin/login');

        // A dónde redirigir si un usuario ya autenticado entra a /admin/login
        $middleware->redirectUsersTo('/admin/propiedades');

        // Registra el alias 'rol' para usarlo en rutas como middleware('rol:ADMINISTRADOR')
        $middleware->alias([
            'rol' => VerificarRol::class,
        ]);
    })
    // 3. Manejo global de excepciones: registra fallos críticos de MySQL en el log diario 'disponibilidad'
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $e): void {
            if ($e instanceof QueryException || $e instanceof \PDOException) {
                Log::channel('disponibilidad')->critical('Error crítico de base de datos detectado.', [
                    'excepcion' => $e::class,
                    'codigo' => $e->getCode(),
                ]);
            }
        });

        // Responde en JSON si la petición pide JSON o pertenece a /api/*
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
