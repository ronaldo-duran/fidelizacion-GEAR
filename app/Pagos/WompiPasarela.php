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

    /**
     * Lo que la firma debe cubrir sí o sí: con esto se decide si un pago se
     * aplica. Un evento que firme otra lista se rechaza.
     */
    private const PROPIEDADES_FIRMADAS = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'];

    /**
     * Los campos de la transacción que se guardan en `notificaciones_pago`.
     * Nada del pagador (correo, nombre, teléfono, documento, direcciones):
     * no hace falta para defender un pago y no se guarda (Ley 1581).
     */
    private const CAMPOS_GUARDADOS = [
        'id',
        'reference',
        'status',
        'status_message',
        'amount_in_cents',
        'currency',
        'payment_method_type',
        'created_at',
        'finalized_at',
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
            self::resumen($payload, $transaccion),
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
     * leen de cada evento porque Wompi puede cambiarlas, pero deben incluir el
     * id, el estado y el monto. Null si el evento no trae lo necesario, si la
     * lista no cubre esas tres o si no hay secreto configurado.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function checksum(array $payload, string $secreto): ?string
    {
        $propiedades = data_get($payload, 'signature.properties');
        $timestamp = $payload['timestamp'] ?? null;

        if ($secreto === '' || ! is_array($propiedades) || ! is_scalar($timestamp)) {
            return null;
        }

        foreach (self::PROPIEDADES_FIRMADAS as $requerida) {
            if (! in_array($requerida, $propiedades, true)) {
                return null;
            }
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

    /**
     * El evento sin los datos del pagador: solo el tipo, las fechas de envío y
     * los campos de la transacción de la lista blanca.
     *
     * @param  array<array-key, mixed>  $payload
     * @param  array<array-key, mixed>  $transaccion
     * @return array<string, mixed>
     */
    private static function resumen(array $payload, array $transaccion): array
    {
        return [
            'event' => $payload['event'] ?? null,
            'environment' => $payload['environment'] ?? null,
            'sent_at' => $payload['sent_at'] ?? null,
            'timestamp' => $payload['timestamp'] ?? null,
            'transaction' => array_intersect_key($transaccion, array_flip(self::CAMPOS_GUARDADOS)),
        ];
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
