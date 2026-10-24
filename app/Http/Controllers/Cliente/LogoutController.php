<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cierra la sesión del cliente en este dispositivo.
 */
class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('cliente')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cliente.ingreso');
    }
}
