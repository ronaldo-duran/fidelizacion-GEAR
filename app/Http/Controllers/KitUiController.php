<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;

/**
 * Catálogo visual de las primitivas compartidas (pantalla interna /_ui).
 *
 * Sirve para comparar contra docs/diseno/reference durante el desarrollo;
 * en producción responde 404.
 */
final class KitUiController extends Controller
{
    public function __invoke(): View
    {
        abort_if(App::isProduction(), 404);

        return view('kit-ui');
    }
}
