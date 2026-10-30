<?php

declare(strict_types=1);

namespace App\Actions\Credenciales;

use App\Credenciales\TokenSigner;
use App\Credenciales\ValidacionCredencial;
use App\Enums\PropositoCredencial;
use App\Enums\ResultadoCredencial;
use App\Models\Cliente;
use App\Models\CredencialQr;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Valida lo que el cajero escaneó (el token firmado) o digitó (el código
 * corto) para el propósito que espera: firma, vencimiento con una pequeña
 * tolerancia de reloj, propósito y, para un beneficio, el comercio del cajero.
 *
 * Una credencial de beneficio se usa una vez, pero validarla no la gasta: la
 * gasta la entrega (`RedeemBeneficio`, E5-02) en su misma transacción, así
 * cancelar o una entrega fallida no le queman el código al cliente. Aquí una
 * ya gastada vuelve como `usado`. Los códigos cortos fallidos se limitan por
 * cajero contra la fuerza bruta.
 */
class ValidateCredencial
{
    private readonly TokenSigner $signer;

    public function __construct(TokenSigner $signer)
    {
        $this->signer = $signer;
    }

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $cajero, string $entrada, PropositoCredencial $esperado): ValidacionCredencial
    {
        if (! $cajero->activo) {
            throw new AuthorizationException('Un usuario inactivo no puede validar credenciales.');
        }

        $entrada = trim($entrada);

        if (str_contains($entrada, '.')) {
            return $this->check($cajero, $this->fromToken($entrada), $esperado);
        }

        $limiter = "credencial-corta:{$cajero->id}";

        if (RateLimiter::tooManyAttempts($limiter, self::setting('max_failed_short_codes_per_minute'))) {
            return ValidacionCredencial::rechazada(ResultadoCredencial::Bloqueado);
        }

        $validacion = $this->check($cajero, $this->fromShortCode($entrada), $esperado);

        if (! $validacion->esValida()) {
            RateLimiter::hit($limiter, 60);
        }

        return $validacion;
    }

    private function fromToken(string $token): ?CredencialQr
    {
        $payload = $this->signer->read($token);
        $nonce = $payload['n'] ?? null;
        $credencial = is_string($nonce) ? CredencialQr::query()->where('nonce', $nonce)->first() : null;

        $matches = $credencial !== null
            && ($payload['c'] ?? null) === $credencial->cliente_id
            && ($payload['p'] ?? null) === $credencial->proposito->value
            && ($payload['b'] ?? null) === $credencial->beneficio_id;

        return $matches ? $credencial : null;
    }

    private function fromShortCode(string $codigo): ?CredencialQr
    {
        $codigo = mb_strtoupper(str_replace(' ', '', $codigo));

        return CredencialQr::query()
            ->where('codigo_corto', $codigo)
            ->where('vence_at', '>=', CarbonImmutable::now()->subSeconds(self::setting('clock_skew_seconds')))
            ->latest('id')
            ->first();
    }

    private function check(User $cajero, ?CredencialQr $credencial, PropositoCredencial $esperado): ValidacionCredencial
    {
        $aplica = $credencial !== null
            && $credencial->proposito === $esperado
            && $this->sameMerchant($cajero, $credencial);

        if (! $aplica || $credencial === null) {
            return ValidacionCredencial::rechazada(ResultadoCredencial::Invalido);
        }

        // Un código gastado dice dónde se usó aunque ya haya vencido.
        if ($credencial->usado_at !== null) {
            return ValidacionCredencial::usada($credencial);
        }

        $limite = $credencial->vence_at->addSeconds(self::setting('clock_skew_seconds'));

        if (CarbonImmutable::now()->gt($limite)) {
            return ValidacionCredencial::rechazada(ResultadoCredencial::Vencido);
        }

        return ValidacionCredencial::valida(Cliente::query()->findOrFail($credencial->cliente_id), $credencial);
    }

    private function sameMerchant(User $cajero, CredencialQr $credencial): bool
    {
        return $credencial->proposito !== PropositoCredencial::Beneficio
            || $cajero->esAdministradorDelGrupo()
            || $cajero->comercio_id === $credencial->comercio_id;
    }

    private static function setting(string $key): int
    {
        $value = config("credenciales.{$key}");

        return is_int($value) ? $value : 0;
    }
}
