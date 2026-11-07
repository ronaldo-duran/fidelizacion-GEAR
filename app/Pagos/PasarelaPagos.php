<?php

declare(strict_types=1);

namespace App\Pagos;

use App\Models\PagoMembresia;
use Illuminate\Http\Request;

/**
 * Contrato de una pasarela de pagos (E6-01). El dominio no sabe cuál es: la
 * conversión a la unidad de la pasarela (centavos en Wompi) y la firma viven
 * solo en el adaptador.
 */
interface PasarelaPagos
{
    /**
     * La URL de la pasarela a la que se lleva al cliente para pagar.
     */
    public function checkout(PagoMembresia $pago, string $urlRegreso): string;

    /**
     * Verifica la firma de una notificación y la traduce a un objeto propio.
     *
     * @throws FirmaInvalida
     */
    public function notificacion(Request $request): NotificacionPasarela;
}
