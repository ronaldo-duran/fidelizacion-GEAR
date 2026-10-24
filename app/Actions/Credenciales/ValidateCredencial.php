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
 * Validates what the cashier scanned (the signed token) or typed (the short
 * code) for the purpose the cashier expects: signature, expiry with a small
 * clock skew, purpose and, for a benefit, the cashier's merchant.
 *
 * A benefit credential is used once: it is marked with an UPDATE conditioned
 * on `usado_at IS NULL`, so two simultaneous validations accept only one.
 * Failed short codes are limited per cashier against brute force.
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
            throw new AuthorizationException('Inactive users cannot validate credentials.');
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

        $limite = $credencial->vence_at->addSeconds(self::setting('clock_skew_seconds'));

        if (CarbonImmutable::now()->gt($limite)) {
            return ValidacionCredencial::rechazada(ResultadoCredencial::Vencido);
        }

        if ($esperado === PropositoCredencial::Beneficio && ! $this->markUsed($credencial, $cajero)) {
            return ValidacionCredencial::rechazada(ResultadoCredencial::Usado);
        }

        return ValidacionCredencial::valida(Cliente::query()->findOrFail($credencial->cliente_id), $credencial);
    }

    private function sameMerchant(User $cajero, CredencialQr $credencial): bool
    {
        return $credencial->proposito !== PropositoCredencial::Beneficio
            || $cajero->esAdministradorDelGrupo()
            || $cajero->comercio_id === $credencial->comercio_id;
    }

    private function markUsed(CredencialQr $credencial, User $cajero): bool
    {
        $now = CarbonImmutable::now();
        $updated = CredencialQr::query()->toBase()
            ->where('id', $credencial->id)
            ->whereNull('usado_at')
            ->update(['usado_at' => $now, 'usado_por' => $cajero->id]);

        return $updated === 1;
    }

    private static function setting(string $key): int
    {
        $value = config("credenciales.{$key}");

        return is_int($value) ? $value : 0;
    }
}
