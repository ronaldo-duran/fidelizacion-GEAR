<?php

declare(strict_types=1);

namespace App\Actions\Credenciales;

use App\Credenciales\CredencialEmitida;
use App\Credenciales\TokenSigner;
use App\Enums\PropositoCredencial;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\CredencialQr;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Random\Randomizer;
use RuntimeException;

/**
 * Emite una credencial QR que vive segundos (regla dura 3.12): un token
 * firmado con un nonce aleatorio, para que el mismo cliente nunca reciba dos
 * veces el mismo código, y un código corto equivalente para el cajero. Una
 * credencial de beneficio pertenece al comercio del beneficio y su código
 * corto lleva el prefijo de ese comercio.
 *
 * El código corto es único entre las credenciales vigentes por una
 * restricción de la base de datos (`credenciales_qr_codigos`), nunca por una
 * verificación en código: de dos emisiones simultáneas con el mismo código,
 * la segunda reintenta.
 */
class IssueCredencial
{
    private const MAX_SHORT_CODE_TRIES = 20;

    private readonly TokenSigner $signer;

    private readonly Randomizer $random;

    public function __construct(TokenSigner $signer, Randomizer $random)
    {
        $this->signer = $signer;
        $this->random = $random;
    }

    public function __invoke(
        Cliente $cliente,
        PropositoCredencial $proposito,
        ?int $beneficioId = null,
        ?int $comercioId = null,
    ): CredencialEmitida {
        $esBeneficio = $proposito === PropositoCredencial::Beneficio;

        if ($esBeneficio !== ($beneficioId !== null && $comercioId !== null)) {
            throw new InvalidArgumentException('Solo una credencial de beneficio lleva un beneficio y su comercio.');
        }

        $credencial = $this->create([
            'cliente_id' => $cliente->id,
            'proposito' => $proposito,
            'beneficio_id' => $beneficioId,
            'comercio_id' => $comercioId,
        ], $this->prefix($comercioId));

        $token = $this->signer->sign([
            'c' => $cliente->id,
            'p' => $proposito->value,
            'b' => $beneficioId,
            'n' => $credencial->nonce,
            'exp' => $credencial->vence_at->getTimestamp(),
        ]);

        return new CredencialEmitida($credencial, $token);
    }

    /**
     * Crea la credencial con un código corto aleatorio (un prefijo y cuatro
     * dígitos) y reserva ese código; si la reserva rompe la restricción única,
     * reintenta con otro. Cada intento es su propia transacción, así un insert
     * fallido no aborta el siguiente.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function create(array $attributes, string $prefix): CredencialQr
    {
        for ($try = 1; $try <= self::MAX_SHORT_CODE_TRIES; $try++) {
            $codigo = sprintf('%s-%04d', $prefix, $this->random->getInt(0, 9999));

            try {
                return DB::transaction(function () use ($attributes, $codigo): CredencialQr {
                    $now = CarbonImmutable::now();
                    $credencial = CredencialQr::query()->create([
                        ...$attributes,
                        'nonce' => Str::random(40),
                        'codigo_corto' => $codigo,
                        'emitido_at' => $now,
                        'vence_at' => $now->addSeconds(self::seconds('ttl_seconds')),
                    ]);

                    $this->reserve($credencial);

                    return $credencial;
                });
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('No se encontró un código corto libre.');
    }

    /**
     * Reserva el código corto en cada ventana de tiempo que toca su vigencia:
     * desde la emisión hasta el vencimiento más la tolerancia de reloj, lo que
     * `ValidateCredencial` todavía lo encuentra. Las ventanas duran eso mismo,
     * así una vigencia toca una o dos y dos vigencias que se cruzan siempre
     * comparten una.
     */
    private function reserve(CredencialQr $credencial): void
    {
        $skew = self::seconds('clock_skew_seconds');
        $largo = max(1, self::seconds('ttl_seconds') + $skew);
        $desde = intdiv($credencial->emitido_at->getTimestamp(), $largo);
        $hasta = intdiv($credencial->vence_at->getTimestamp() + $skew, $largo);

        DB::table('credenciales_qr_codigos')->insert(array_map(
            static fn (int $ventana): array => [
                'credencial_qr_id' => $credencial->id,
                'codigo_corto' => $credencial->codigo_corto,
                'ventana' => $ventana,
            ],
            range($desde, $hasta),
        ));
    }

    /**
     * `AR` para identificar al cliente; para un beneficio, las tres primeras
     * letras del nombre del comercio sin artículos al inicio (p. ej. "El Rancho
     * de Javi" da "RAN").
     */
    private function prefix(?int $comercioId): string
    {
        $default = config('credenciales.identify_prefix');

        if ($comercioId === null) {
            return is_string($default) ? $default : 'AR';
        }

        $nombre = mb_strtoupper(Str::ascii(Comercio::query()->findOrFail($comercioId)->razon_social));
        $sinArticulos = preg_replace('/^((EL|LA|LOS|LAS)\s+)+/', '', trim($nombre));
        $letters = preg_replace('/[^A-Z]/', '', is_string($sinArticulos) ? $sinArticulos : $nombre);

        return str_pad(substr(is_string($letters) ? $letters : '', 0, 3), 3, 'X');
    }

    private static function seconds(string $key): int
    {
        $value = config("credenciales.{$key}");

        return is_int($value) ? $value : 0;
    }
}
