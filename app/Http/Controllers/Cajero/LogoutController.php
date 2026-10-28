<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cajero;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cierra la sesión del cajero en el celular del comercio.
 */
class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('caja.ingreso');
    }
}
