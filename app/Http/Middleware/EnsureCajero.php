<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RolUsuario;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * La caja (E4-02) solo admite cajeros activos con sede. Se revisa en cada
 * petición: si al cajero lo desactivan o le quitan la sede, su sesión se
 * cierra en la siguiente. Otro rol con sesión del panel recibe 403, sin
 * cerrarle la sesión del panel.
 */
class EnsureCajero
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->puedeEntrarALaCaja()) {
            return $next($request);
        }

        if ($user instanceof User && $user->rol !== RolUsuario::Cajero) {
            abort(403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('caja.ingreso');
    }
}
