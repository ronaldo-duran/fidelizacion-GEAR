<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Avisa en el log cuando producción recibe encabezados de un proxy sin tener
 * TRUSTED_PROXIES (revisión del PR de la auditoría 2026-11, hallazgo 3). Detrás
 * de un proxy sin configurar, todos los visitantes comparten la IP del proxy
 * (y sus topes) y la aplicación cree que la conexión es HTTP. Un aviso al día
 * como mucho; si el servidor recibe las conexiones directo, se ignora.
 */
class WarnUntrustedProxy
{
    public const CACHE_KEY = 'aviso-proxy-sin-configurar';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            app()->isProduction()
            && config('app.trusted_proxies') === []
            && $request->headers->has('X-Forwarded-For')
            && Cache::add(self::CACHE_KEY, true, 86400)
        ) {
            Log::warning('Llegan encabezados de un proxy y TRUSTED_PROXIES está vacío.', [
                'ip_vista' => $request->ip(),
                'ver' => 'docs/seguridad/auditoria-2026-11.md',
            ]);
        }

        return $next($request);
    }
}
