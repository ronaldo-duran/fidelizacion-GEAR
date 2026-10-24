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
 * Issues a QR credential that lives for seconds (hard rule 3.12): a signed
 * token with a random nonce, so the same customer never gets the same code
 * twice, and an equivalent short code for the cashier. A benefit credential
 * belongs to the benefit's merchant and its short code carries the
 * merchant's prefix.
 *
 * A short code is unique among the credentials still valid by a database
 * constraint (`credenciales_qr_codigos`), never by a check in code: of two
 * simultaneous issues with the same code, the second one retries.
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
            throw new InvalidArgumentException('Only a benefit credential carries a benefit and its merchant.');
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
     * Creates the credential with a random short code (a prefix plus four
     * digits) and reserves that code; when the reservation breaks the unique
     * constraint, retries with another. Each try is its own transaction, so a
     * failed insert doesn't abort the next one.
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

        throw new RuntimeException('Could not find a free short code.');
    }

    /**
     * Reserves the short code in every time window its validity touches: from
     * the issue until the expiry plus the clock skew, as long as
     * `ValidateCredencial` still finds it. The windows are that long, so a
     * validity touches one or two of them and two overlapping validities
     * always share one.
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
     * `AR` to identify the customer; for a benefit, the first three letters
     * of the merchant's name without leading articles (e.g. "El Rancho de
     * Javi" gives "RAN").
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
