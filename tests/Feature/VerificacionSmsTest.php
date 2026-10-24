<?php

declare(strict_types=1);

use App\Actions\Verificacion\SendVerificationCode;
use App\Actions\Verificacion\VerifyCode;
use App\Enums\PropositoVerificacion;
use App\Models\CodigoVerificacion;
use App\Sms\ArraySmsSender;
use App\Sms\LogSmsSender;
use App\Sms\ProveedorSmsSender;
use App\Sms\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/otp-escenarios.json'), true);
    $this->start = CarbonImmutable::parse('2026-10-23 10:00:00');
    $this->travelTo($this->start);
    $this->sms = app(SmsSender::class);
    $this->lastCode = function (): string {
        $enviados = $this->sms->enviados();
        preg_match('/\b(\d{6})\b/', end($enviados)['mensaje'] ?? '', $match);

        return $match[1] ?? '';
    };
    $this->case = function (string $criterion): array {
        foreach ($this->fixture['cases'] as $case) {
            if ($case['_e8_06_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    };
});

it('uses the array driver in tests and keeps the config equal to the fixture', function (): void {
    expect($this->sms)->toBeInstanceOf(ArraySmsSender::class)
        ->and(config('verificacion.digits'))->toBe($this->fixture['code']['digits']['value'])
        ->and(config('verificacion.ttl_minutes'))->toBe($this->fixture['code']['ttl_minutes']['value'])
        ->and(config('verificacion.max_attempts'))->toBe($this->fixture['code']['max_attempts']['value'])
        ->and(config('verificacion.resend_cooldown_seconds'))
        ->toBe($this->fixture['sending']['resend_cooldown_seconds']['value'])
        ->and(config('verificacion.max_per_phone_per_hour'))
        ->toBe($this->fixture['sending']['max_per_phone_per_hour']['value'])
        ->and(config('verificacion.max_per_ip_per_hour'))
        ->toBe($this->fixture['sending']['max_per_ip_per_hour']['value'])
        ->and(config('verificacion.message'))->toBe($this->fixture['message_template']);
});

it('runs each fixture timeline', function (string $criterion): void {
    $case = ($this->case)($criterion);
    $codes = [];
    $sent = 0;

    foreach ($case['steps'] as $step) {
        $this->travelTo($this->start->addSeconds($step['at_seconds']));
        $purpose = PropositoVerificacion::from($step['purpose']);

        if ($step['action'] === 'send') {
            try {
                app(SendVerificationCode::class)($purpose, $step['phone']);
                $codes[$step['label'] ?? 'sent'] = ($this->lastCode)();
                $codes['sent'] = $codes[$step['label'] ?? 'sent'];
                $sent++;
                $result = 'sent';
            } catch (ValidationException) {
                $result = 'rejected';
            }

            if (isset($step['expected'])) {
                expect($result)->toBe($step['expected'], "{$case['_description']} at {$step['at_seconds']} s");
            }

            continue;
        }

        $code = $step['code'] === 'wrong'
            ? str_pad((string) (((int) $codes['sent'] + 1) % 1000000), 6, '0', STR_PAD_LEFT)
            : $codes[$step['code']];

        for ($i = 0; $i < ($step['times'] ?? 1); $i++) {
            $valid = app(VerifyCode::class)($purpose, $step['phone'], $code);
        }

        expect($valid ? 'valid' : 'rejected')
            ->toBe($step['expected'], "{$case['_description']} at {$step['at_seconds']} s");
    }

    if (isset($case['expected']['sms_sent'])) {
        expect($sent)->toBe($case['expected']['sms_sent']);
    }
})->with([
    'sent, hashed and single use' => ['a code is sent, hashed and verifies once'],
    'expiry' => ['an expired code is rejected'],
    'only the last code' => ['only the last code works'],
    'attempt limit' => ['too many attempts invalidate the code'],
    'cooldown and caps' => ['cooldown and caps stop sending'],
    'purpose bound' => ['codes are bound to their purpose'],
]);

it('stores only the hash, normalizes the phone and never logs the code', function (): void {
    $logged = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message . json_encode($event->context);
    });

    app(SendVerificationCode::class)(PropositoVerificacion::Registro, '+57 300 123 4567', '10.0.0.1');
    app(VerifyCode::class)(PropositoVerificacion::Registro, '3001234567', '000000');
    $code = ($this->lastCode)();
    $row = CodigoVerificacion::query()->sole();

    expect($row->celular)->toBe('3001234567')
        ->and($row->codigo_hash)->not->toContain($code)
        ->and(json_encode($row->getAttributes()))->not->toContain('"' . $code . '"')
        ->and($row->toArray())->not->toHaveKey('codigo_hash')
        ->and(implode("\n", $logged))->not->toContain($code)
        ->and($this->sms->enviados()[0]['mensaje'])->toBe(str_replace(
            ['{code}', '{minutes}'],
            [$code, '5'],
            $this->fixture['message_template'],
        ));
});

it('caps the codes requested from one IP', function (): void {
    $results = [];

    foreach (range(1, 11) as $i) {
        $celular = '30' . str_pad((string) $i, 8, '0', STR_PAD_LEFT);

        try {
            app(SendVerificationCode::class)(PropositoVerificacion::Registro, $celular, '10.0.0.9');
            $results[] = 'sent';
        } catch (ValidationException) {
            $results[] = 'rejected';
        }
    }

    expect(array_count_values($results))->toBe(['sent' => 10, 'rejected' => 1])
        ->and(end($results))->toBe('rejected');
});

it('accepts only one of two simultaneous verifications', function (): void {
    app(SendVerificationCode::class)(PropositoVerificacion::Registro, '3001234567');
    $code = ($this->lastCode)();

    $first = app(VerifyCode::class)(PropositoVerificacion::Registro, '3001234567', $code);
    $second = app(VerifyCode::class)(PropositoVerificacion::Registro, '3001234567', $code);

    expect([$first, $second])->toBe([true, false]);
});

it('keeps the log driver to the local environment and the array driver out of production', function (): void {
    expect(fn () => app(LogSmsSender::class)->send('3001234567', 'x'))->toThrow(LogicException::class);

    app()->detectEnvironment(static fn (): string => 'production');

    try {
        expect(fn () => app(ArraySmsSender::class)->send('3001234567', 'x'))->toThrow(LogicException::class);
    } finally {
        app()->detectEnvironment(static fn (): string => 'testing');
    }
});

it('refuses to send through the provider until the client chooses one', function (): void {
    expect(fn () => app(ProveedorSmsSender::class)->send('3001234567', 'x'))->toThrow(RuntimeException::class);
});
