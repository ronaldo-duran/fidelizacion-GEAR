<?php

declare(strict_types=1);

namespace App\Actions\Credenciales;

use App\Credenciales\CredencialEmitida;
use App\Enums\PropositoCredencial;
use App\Models\Beneficio;
use App\Models\Cliente;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Emite el código de un beneficio que el cliente pide desde la PWA (E8-04),
 * con un tope de emisiones por minuto. Sin tope, un cliente podría llamar a
 * `renovar` en bucle, llenar `credenciales_qr` y agotar los códigos cortos
 * del comercio (10.000 por ventana), y eso haría fallar la emisión de otros
 * clientes. El contador se incrementa de forma atómica en la caché.
 */
class IssueBeneficioCredencial
{
    private readonly IssueCredencial $issue;

    public function __construct(IssueCredencial $issue)
    {
        $this->issue = $issue;
    }

    /**
     * @throws RuntimeException si el cliente pasó el tope del minuto
     */
    public function __invoke(Cliente $cliente, Beneficio $beneficio): CredencialEmitida
    {
        $tope = config('credenciales.max_benefit_codes_per_minute');

        if (RateLimiter::hit("credencial-beneficio:{$cliente->id}", 60) > (is_int($tope) ? $tope : 0)) {
            throw new RuntimeException('El cliente pidió demasiados códigos de beneficio en un minuto.');
        }

        return ($this->issue)($cliente, PropositoCredencial::Beneficio, $beneficio->id, $beneficio->comercio_id);
    }
}
