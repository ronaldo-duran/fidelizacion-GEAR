<?php

declare(strict_types=1);

namespace App\Actions\Verificacion;

use App\Enums\PropositoVerificacion;
use App\Models\CodigoVerificacion;
use App\Sms\SmsSender;
use App\Verificacion\CodigoHasher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Envía un código de un solo uso por SMS (E8-06, cambio de alcance aceptado
 * en #1). Un código nuevo invalida el anterior de ese celular y propósito,
 * pero solo cuando el SMS ya salió. Una espera entre envíos y topes por
 * celular y por IP frenan el abuso y limitan el costo de los SMS. Solo se
 * guarda la huella del código; el código nunca llega a un log.
 */
class SendVerificationCode
{
    /** El tope de todos los SMS de un día (auditoría 2026-11, hallazgo 3). */
    private const GLOBAL_KEY = 'sms-dia';

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

        if (! CodigoHasher::isCelular($celular)) {
            throw ValidationException::withMessages(['celular' => 'Escribe un celular colombiano de 10 dígitos.']);
        }

        $this->countRequest($celular, $ip);

        $codigo = $this->newCode();
        $now = CarbonImmutable::now();
        $nuevo = CodigoVerificacion::query()->create([
            'proposito' => $proposito,
            'celular' => $celular,
            'codigo_hash' => $this->hasher->hash($proposito, $celular, $codigo),
            'vence_at' => $now->addMinutes(self::setting('ttl_minutes')),
            'ip' => $ip,
        ]);

        try {
            $this->sms->send($celular, $this->message($codigo));
        } catch (Throwable $e) {
            // El código anterior sigue vigente; la petición igual cuenta para los límites.
            CodigoVerificacion::query()->toBase()->where('id', $nuevo->id)->update(['invalidado_at' => $now]);

            throw $e;
        }

        CodigoVerificacion::query()->toBase()
            ->where('celular', $celular)
            ->where('proposito', $proposito->value)
            ->where('id', '<>', $nuevo->id)
            ->whereNull('usado_at')
            ->whereNull('invalidado_at')
            ->update(['invalidado_at' => $now]);
    }

    /**
     * Cuenta la petición antes de enviar, con un incremento atómico por límite,
     * para que peticiones simultáneas no pasen todas el chequeo. El primer
     * límite superado rechaza la petición sin SMS; los siguientes no se
     * cuentan.
     *
     * @throws ValidationException
     */
    private function countRequest(string $celular, ?string $ip): void
    {
        foreach ($this->limiters($celular, $ip) as $key => [$max, $decay, $message]) {
            $hits = RateLimiter::hit($key, $decay);

            if ($hits > $max) {
                throw ValidationException::withMessages(['celular' => $message]);
            }

            if ($key === self::GLOBAL_KEY) {
                self::warnNearGlobalCap($hits, $max);
            }
        }
    }

    /**
     * Un solo aviso por ventana, en la petición que cruza el porcentaje: el tope global se
     * puede agotar a propósito y deja sin registro a los clientes reales hasta que pase la
     * ventana (24 horas móviles desde el primer SMS, no el día calendario).
     */
    private static function warnNearGlobalCap(int $hits, int $max): void
    {
        $umbral = (int) ceil($max * self::setting('day_global_alert_percent') / 100);

        if ($umbral > 0 && $hits === $umbral) {
            Log::warning('Los SMS de verificación llegaron al umbral de aviso del tope global.', [
                'enviados' => $hits,
                'tope' => $max,
            ]);
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

        // Va de último: una petición que ya frenó otro tope no gasta el cupo del día.
        $limiters[self::GLOBAL_KEY] = [
            self::setting('max_per_day_global'),
            86400,
            'No pudimos enviar el código en este momento. Intenta más tarde.',
        ];

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
