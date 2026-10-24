<?php

declare(strict_types=1);

namespace App\Actions\Verificacion;

use App\Enums\PropositoVerificacion;
use App\Models\CodigoVerificacion;
use App\Sms\SmsSender;
use App\Verificacion\CodigoHasher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Sends a one-time code by SMS (E8-06, a scope change accepted in #1). A new
 * code invalidates the previous one for that phone and purpose. A cooldown
 * between sends and caps per phone and per IP stop abuse and cap the SMS
 * cost. Only the code's hash is stored; the code never reaches a log.
 */
class SendVerificationCode
{
    private readonly SmsSender $sms;

    private readonly CodigoHasher $hasher;

    public function __construct(SmsSender $sms, CodigoHasher $hasher)
    {
        $this->sms = $sms;
        $this->hasher = $hasher;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(PropositoVerificacion $proposito, string $celular, ?string $ip = null): void
    {
        $celular = CodigoHasher::normalizeCelular($celular);
        $limiters = $this->limiters($celular, $ip);

        foreach ($limiters as $key => $limiter) {
            if (RateLimiter::tooManyAttempts($key, $limiter[0])) {
                throw ValidationException::withMessages(['celular' => $limiter[2]]);
            }
        }

        $codigo = $this->newCode();

        DB::transaction(function () use ($proposito, $celular, $ip, $codigo): void {
            $now = CarbonImmutable::now();

            CodigoVerificacion::query()->toBase()
                ->where('celular', $celular)
                ->where('proposito', $proposito->value)
                ->whereNull('usado_at')
                ->whereNull('invalidado_at')
                ->update(['invalidado_at' => $now]);

            CodigoVerificacion::query()->create([
                'proposito' => $proposito,
                'celular' => $celular,
                'codigo_hash' => $this->hasher->hash($proposito, $celular, $codigo),
                'vence_at' => $now->addMinutes(self::setting('ttl_minutes')),
                'ip' => $ip,
            ]);
        });

        $this->sms->send($celular, $this->message($codigo));

        foreach ($limiters as $key => $limiter) {
            RateLimiter::hit($key, $limiter[1]);
        }
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: string}>
     */
    private function limiters(string $celular, ?string $ip): array
    {
        $limiters = [
            "sms-espera:{$celular}" => [
                1,
                self::setting('resend_cooldown_seconds'),
                'Espera un momento antes de pedir otro código.',
            ],
            "sms-celular:{$celular}" => [
                self::setting('max_per_phone_per_hour'),
                3600,
                'Pediste demasiados códigos. Intenta más tarde.',
            ],
        ];

        if ($ip !== null) {
            $limiters["sms-ip:{$ip}"] = [
                self::setting('max_per_ip_per_hour'),
                3600,
                'Pediste demasiados códigos. Intenta más tarde.',
            ];
        }

        return $limiters;
    }

    private function newCode(): string
    {
        $digits = self::setting('digits');

        return str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }

    private function message(string $codigo): string
    {
        $template = config('verificacion.message');

        return str_replace(
            ['{code}', '{minutes}'],
            [$codigo, (string) self::setting('ttl_minutes')],
            is_string($template) ? $template : '{code}',
        );
    }

    private static function setting(string $key): int
    {
        $value = config("verificacion.{$key}");

        return is_int($value) ? $value : 0;
    }
}
