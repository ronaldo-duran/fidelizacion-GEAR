<?php

declare(strict_types=1);

use App\Actions\Membresias\ApplyNotificacionPago;
use App\Enums\EstadoPago;
use App\Enums\ResultadoNotificacionPago;
use App\Models\Membresia;
use App\Pagos\NotificacionPasarela;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\BloqueoTestData;
use Tests\Support\MembresiasTestData;
use Tests\Support\NivelesTestData;
use Tests\Support\SinTransaccionDePrueba;

// Dos webhooks del mismo pago en paralelo (E6-01): los datos se guardan de verdad para que la
// segunda conexión los vea.
uses(SinTransaccionDePrueba::class);

afterEach(function (): void {
    BloqueoTestData::vaciar();
});

it('waits for the payment lock, so a parallel webhook finds it approved and adds no period', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00:00'));
    NivelesTestData::nivelesRegla();
    $cliente = NivelesTestData::cliente([]);
    $pago = MembresiasTestData::pago($cliente, MembresiasTestData::plan());
    $aprobado = new NotificacionPasarela($pago->referencia, 'tx-1', EstadoPago::Aprobado, 'APPROVED', 49900, 'COP', []);
    $aplicar = static fn (): ResultadoNotificacionPago => app(ApplyNotificacionPago::class)('wompi', $aprobado);

    // Otro webhook tiene el pago bloqueado: este espera ahí, antes de leer su estado.
    $segunda = BloqueoTestData::segundaConexion();
    $segunda->beginTransaction();
    $segunda->table('pagos_membresia')->where('id', $pago->id)->lockForUpdate()->first();
    BloqueoTestData::limitarEspera(DB::connection(), 1);

    $bloqueado = null;

    try {
        $aplicar();
    } catch (QueryException $error) {
        $bloqueado = $error;
    }

    expect($bloqueado?->getSql())->toContain('pagos_membresia')
        ->and(Membresia::query()->count())->toBe(0);

    // El otro webhook aprueba el pago y suelta el bloqueo: este lo encuentra aprobado.
    $segunda->table('pagos_membresia')->where('id', $pago->id)->update(['estado' => 'aprobado']);
    $segunda->commit();

    expect($aplicar())->toBe(ResultadoNotificacionPago::Repetido)
        ->and(Membresia::query()->count())->toBe(0);
});
