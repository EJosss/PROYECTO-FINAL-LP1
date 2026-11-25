<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CabecerasSeguridad
{
    /**
     * Agrega cabeceras HTTP de seguridad en todas las respuestas de la aplicación.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Protección anti-Clickjacking (solo permite iframes del mismo origen)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        // Evita MIME sniffing (el navegador respeta el Content-Type declarado)
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        // Controla qué información de origen se comparte al salir a enlaces externos
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        // Deshabilita permisos de hardware innecesarios (cámara, micrófono y geolocalización)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);
        // Bloquea políticas cross-domain de plugins heredados (Flash/PDF)
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none', false);

        // Content Security Policy (CSP): restringe de dónde se cargan scripts, estilos y formularios (mitiga XSS)
        if (! $response->headers->has('Content-Security-Policy')) {
            $csp = implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "img-src 'self' data: blob: https:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "font-src 'self' data:",
                "connect-src 'self'",
            ]);
            $response->headers->set('Content-Security-Policy', $csp, false);
        }

        // HSTS: fuerza conexiones HTTPS durante 1 año cuando se ejecuta bajo HTTPS o en producción
        if ($request->isSecure() || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
