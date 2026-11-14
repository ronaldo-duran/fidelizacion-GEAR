<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encabezados de seguridad de toda respuesta (auditoría 2026-11, hallazgo 4):
 *
 * - nadie enmarca la caja, el panel ni la PWA en otro sitio (clickjacking);
 * - el navegador no adivina tipos de archivo;
 * - a otros sitios solo les llega el origen, nunca la ruta ni la consulta (el
 *   enlace firmado del registro lleva el documento);
 * - solo la cámara, y solo en este sitio (el escáner de QR de la caja);
 * - en producción y por HTTPS, el navegador recuerda usar siempre HTTPS.
 *
 * No hay Content-Security-Policy todavía: Livewire, Alpine y Filament usan
 * scripts en línea y exigiría un nonce en cada uno.
 */
class SecurityHeaders
{
    public const HSTS = 'max-age=31536000; includeSubDomains';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', self::HSTS);
        }

        return $response;
    }
}
