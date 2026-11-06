<?php

declare(strict_types=1);

namespace App\Actions\Membresias;

use App\Enums\EstadoPago;
use App\Models\Cliente;
use App\Models\PagoMembresia;
use App\Models\PlanMembresia;
use App\Niveles\NivelEfectivo;
use App\Pagos\Pasarelas;
use App\Support\FormatoColombiano;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Inicia el pago de una membresía (E6-01): crea el pago pendiente con una
 * copia del plan y una referencia que pone el servidor, y devuelve la URL de
 * la pasarela. Nada se activa aquí: solo el webhook confirma el pago.
 */
class StartPagoMembresia
{
    private readonly Pasarelas $pasarelas;

    private readonly NivelEfectivo $niveles;

    public function __construct(Pasarelas $pasarelas, NivelEfectivo $niveles)
    {
        $this->pasarelas = $pasarelas;
        $this->niveles = $niveles;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(Cliente $cliente, int $planId): string
    {
        $plan = PlanMembresia::query()->whereKey($planId)->where('activo', true)->first();

        if ($plan === null) {
            throw ValidationException::withMessages(['plan' => 'Elige uno de los planes disponibles.']);
        }

        $this->validateNivel($cliente, $plan);

        $nombre = $this->pasarelas->nombreActual();
        $pasarela = $this->pasarelas->para($nombre)
            ?? throw new LogicException("La pasarela «{$nombre}» no está configurada.");

        $pago = DB::transaction(fn (): PagoMembresia => $this->pago($cliente, $plan, $nombre));

        return $pasarela->checkout($pago, route('cliente.membresia.pago', $pago->referencia));
    }

    /**
     * El pago pendiente del mismo plan que el cliente abrió hace poco, o uno
     * nuevo. Así un doble toque, un reintento o volver atrás desde la pasarela
     * no dejan varios pagos abiertos con referencias distintas. La fila del
     * cliente queda bloqueada: dos peticiones a la vez no crean dos pagos.
     */
    private function pago(Cliente $cliente, PlanMembresia $plan, string $pasarela): PagoMembresia
    {
        $this->niveles->lockCliente($cliente->id);
        $minutos = config('pagos.reusar_pendiente_minutos');
        $query = PagoMembresia::query();
        $query->getQuery()
            ->where('cliente_id', $cliente->id)
            ->where('plan_id', $plan->id)
            ->where('pasarela', $pasarela)
            ->where('monto_cop', $plan->precio_cop)
            ->where('estado', EstadoPago::Pendiente->value)
            ->where('created_at', '>=', CarbonImmutable::now()->subMinutes(is_int($minutos) ? $minutos : 30))
            ->orderByDesc('id');

        return $query->first() ?? PagoMembresia::query()->create([
            'cliente_id' => $cliente->id,
            'plan_id' => $plan->id,
            'referencia' => 'M' . Str::upper((string) Str::ulid()),
            'pasarela' => $pasarela,
            'nivel' => $plan->nivel,
            'duracion_meses' => $plan->duracion_meses,
            'monto_cop' => $plan->precio_cop,
        ]);
    }

    /**
     * Un cliente tiene un solo `nivel_por_membresia` (regla 9): mientras su
     * membresía esté vigente, solo renueva el mismo nivel.
     *
     * @throws ValidationException
     */
    private function validateNivel(Cliente $cliente, PlanMembresia $plan): void
    {
        $hasta = $cliente->membresia_vigente_hasta;
        $actual = $cliente->nivel_por_membresia;

        if ($hasta === null || $actual === null || $hasta->lt(CarbonImmutable::today()) || $actual === $plan->nivel) {
            return;
        }

        throw ValidationException::withMessages([
            'plan' => "Tu membresía {$actual->getLabel()} va hasta el " . FormatoColombiano::fechaConAnio($hasta)
                . '. Mientras tanto solo puedes renovar ese mismo nivel.',
        ]);
    }
}
