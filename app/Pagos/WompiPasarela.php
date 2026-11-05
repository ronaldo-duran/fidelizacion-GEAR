<?php

declare(strict_types=1);

namespace App\Pagos;

use App\Enums\EstadoPago;
use App\Models\PagoMembresia;
use Illuminate\Http\Request;

/**
 * Adaptador de Wompi (E6-01): Web Checkout con firma de integridad y
 * eventos `transaction.updated` firmados con el secreto de eventos.
 * Documentación: docs.wompi.co, «Widget & Checkout Web» y «Eventos».
 */
final class WompiPasarela implements PasarelaPagos
{
    private const MONEDA = 'COP';

    private const ESTADOS = [
        'APPROVED' => EstadoPago::Aprobado,
        'DECLINED' => EstadoPago::Rechazado,
        'ERROR' => EstadoPago::Rechazado,
        'VOIDED' => EstadoPago::Anulado,
        'PENDING' => EstadoPago::Pendiente,
    ];

    public function checkout(PagoMembresia $pago, string $urlRegreso): string
    {
        $centavos = $pago->monto_cop * 100;
        $query = http_build_query([
            'public-key' => $this->config('llave_publica'),
            'currency' => self::MONEDA,
            'amount-in-cents' => $centavos,
            'reference' => $pago->referencia,
            'signature:integrity' => self::integrity($pago->referencia, $centavos, $this->config('secreto_integridad')),
            'redirect-url' => $urlRegreso,
        ]);

        return $this->config('checkout_url') . '?' . $query;
    }

    public function notificacion(Request $request): NotificacionPasarela
    {
        $payload = $request->json()->all();
        $checksum = data_get($payload, 'signature.checksum', $request->header('X-Event-Checksum'));
        $esperado = self::checksum($payload, $this->config('secreto_eventos'));

        if (! is_string($checksum) || $esperado === null || ! hash_equals($esperado, strtolower($checksum))) {
            throw new FirmaInvalida($payload);
        }

        $transaccion = data_get($payload, 'data.transaction');
        $transaccion = is_array($transaccion) ? $transaccion : [];
        $estado = is_string($transaccion['status'] ?? null) ? $transaccion['status'] : '';
        $centavos = $transaccion['amount_in_cents'] ?? null;

        return new NotificacionPasarela(
            self::texto($transaccion['reference'] ?? null),
            self::texto($transaccion['id'] ?? null),
            self::ESTADOS[$estado] ?? null,
            $estado,
            is_int($centavos) && $centavos % 100 === 0 ? intdiv($centavos, 100) : null,
            self::texto($transaccion['currency'] ?? null),
            $payload,
        );
    }

    /**
     * Firma de integridad del checkout: SHA256 de referencia, monto en
     * centavos, moneda y secreto de integridad, concatenados.
     */
    public static function integrity(string $referencia, int $centavos, string $secreto): string
    {
        return hash('sha256', $referencia . $centavos . self::MONEDA . $secreto);
    }

    /**
     * Checksum de un evento: SHA256 de los valores de `signature.properties`
     * en su orden, el `timestamp` y el secreto de eventos. Las propiedades se
     * leen de cada evento porque Wompi puede cambiarlas. Null si el evento no
     * trae lo necesario o no hay secreto configurado.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function checksum(array $payload, string $secreto): ?string
    {
        $propiedades = data_get($payload, 'signature.properties');
        $timestamp = $payload['timestamp'] ?? null;

        if ($secreto === '' || ! is_array($propiedades) || $propiedades === [] || ! is_scalar($timestamp)) {
            return null;
        }

        $cadena = '';

        foreach ($propiedades as $propiedad) {
            $valor = is_string($propiedad) ? data_get($payload, "data.{$propiedad}") : null;

            if (! is_scalar($valor)) {
                return null;
            }

            $cadena .= (string) $valor;
        }

        return hash('sha256', $cadena . $timestamp . $secreto);
    }

    private static function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private function config(string $llave): string
    {
        $valor = config("pagos.wompi.{$llave}");

        return is_string($valor) ? $valor : '';
    }
}
