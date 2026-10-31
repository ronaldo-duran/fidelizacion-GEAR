<?php

declare(strict_types=1);

use App\Models\Beneficio;
use App\Models\Redencion;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\BeneficiosTestData;
use Tests\Support\BloqueoTestData;
use Tests\Support\RedencionTestData;
use Tests\Support\SinTransaccionDePrueba;

// Prueba de concurrencia de E5-02: los datos se guardan de verdad para que la segunda conexión los vea.
uses(SinTransaccionDePrueba::class);

afterEach(function (): void {
    BloqueoTestData::vaciar();
});

it('waits for the benefit lock before delivering, so the last global slot is never given twice', function (): void {
    $fixture = RedencionTestData::fixture();
    $this->travelTo(CarbonImmutable::parse($fixture['today']));
    RedencionTestData::seed();
    $escenario = $fixture['global_quota'][0];
    $beneficio = BeneficiosTestData::beneficio($escenario['beneficio']);
    Beneficio::query()->toBase()->where('id', $beneficio->id)->update(['cupo_global' => $escenario['cupo_global']]);
    $nit = collect($fixture['cashiers'])->firstWhere('key', $escenario['cashier'])['comercio_nit'];
    $cajero = RedencionTestData::cajero($nit);
    $confirmacion = $escenario['confirmations'][0];
    $cliente = RedencionTestData::cliente(collect($fixture['customers'])->firstWhere('key', $confirmacion['customer']));
    $entregar = static fn (): Redencion => RedencionTestData::entregar(
        $cajero,
        $cliente,
        $beneficio,
        $confirmacion['monto_cuenta'],
    );

    // Otro cajero tiene el beneficio bloqueado: la entrega espera ahí, antes de leer cupos o escribir nada.
    $segunda = BloqueoTestData::segundaConexion();
    $segunda->beginTransaction();
    $segunda->table('beneficios')->where('id', $beneficio->id)->lockForUpdate()->first();
    BloqueoTestData::limitarEspera(DB::connection(), 1);

    $inicio = hrtime(true);
    $bloqueada = null;

    try {
        $entregar();
    } catch (QueryException $error) {
        $bloqueada = $error;
    }

    expect($bloqueada)->toBeInstanceOf(QueryException::class)
        ->and($bloqueada?->getSql())->toContain('beneficios')
        ->and((hrtime(true) - $inicio) / 1e9)->toBeGreaterThanOrEqual(0.9)
        ->and(Redencion::query()->count())->toBe(0);

    // Suelto el bloqueo, la entrega pasa.
    $segunda->rollBack();
    $entregar();

    expect(Redencion::query()->count())->toBe($escenario['expected_redemptions']);
});
