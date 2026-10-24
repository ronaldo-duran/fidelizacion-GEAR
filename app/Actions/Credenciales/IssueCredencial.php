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
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Issues a QR credential that lives for seconds (hard rule 3.12): a signed
 * token with a random nonce, so the same customer never gets the same code
 * twice, and an equivalent short code for the cashier. A benefit credential
 * belongs to the benefit's merchant and its short code carries the
 * merchant's prefix.
 */
class IssueCredencial
{
    private const MAX_SHORT_CODE_TRIES = 20;

    private readonly TokenSigner $signer;

    public function __construct(TokenSigner $signer)
    {
        $this->signer = $signer;
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

        $now = CarbonImmutable::now();
        $credencial = CredencialQr::query()->create([
            'cliente_id' => $cliente->id,
            'proposito' => $proposito,
            'beneficio_id' => $beneficioId,
            'comercio_id' => $comercioId,
            'nonce' => Str::random(40),
            'codigo_corto' => $this->shortCode($this->prefix($comercioId), $now),
            'emitido_at' => $now,
            'vence_at' => $now->addSeconds(self::seconds('ttl_seconds')),
        ]);

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
     * A prefix plus four digits, not shared with any credential still valid.
     */
    private function shortCode(string $prefix, CarbonImmutable $now): string
    {
        $vigenteDesde = $now->subSeconds(self::seconds('clock_skew_seconds'));

        for ($try = 0; $try < self::MAX_SHORT_CODE_TRIES; $try++) {
            $code = sprintf('%s-%04d', $prefix, random_int(0, 9999));
            $taken = CredencialQr::query()->toBase()
                ->where('codigo_corto', $code)
                ->where('vence_at', '>=', $vigenteDesde)
                ->exists();

            if (! $taken) {
                return $code;
            }
        }

        throw new RuntimeException('Could not find a free short code.');
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
