<?php

declare(strict_types=1);

namespace App\Pagos;

use App\Models\PagoMembresia;
use Illuminate\Http\Request;
use LogicException;

/**
 * Pasarela simulada para local y las pruebas E2E (E6-01): el checkout lleva
 * a una pantalla propia donde se aprueba o rechaza el pago, sin dinero real.
 * No acepta webhooks: el webhook firmado de verdad es el de Wompi.
 */
final class SimuladaPasarela implements PasarelaPagos
{
    public static function habilitada(): bool
    {
        return config('pagos.simulada') === true && ! app()->isProduction();
    }

    public function checkout(PagoMembresia $pago, string $urlRegreso): string
    {
        if (! self::habilitada()) {
            throw new LogicException('La pasarela simulada no está habilitada.');
        }

        return route('cliente.pagos.simulada', ['referencia' => $pago->referencia, 'regreso' => $urlRegreso]);
    }

    public function notificacion(Request $request): NotificacionPasarela
    {
        throw new FirmaInvalida($request->json()->all());
    }
}
