<?php

declare(strict_types=1);

namespace App\Actions\Niveles;

use App\Enums\MotivoCambioNivel;
use App\Enums\TipoEventoCalificado;
use App\Models\Comercio;
use App\Models\EventoCalificado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Registers a qualifying event with its support file and promotes the
 * customer in the same transaction (hard rule 3.6). A property purchase
 * lasts the configured months unless an end date is given; a school
 * enrollment always brings the end date the school sets.
 */
class RegisterEventoCalificado
{
    private readonly RaiseNivelEfectivo $raise;

    public function __construct(RaiseNivelEfectivo $raise)
    {
        $this->raise = $raise;
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        int $clienteId,
        int $comercioId,
        TipoEventoCalificado $tipo,
        CarbonInterface $vigenteDesde,
        ?CarbonInterface $vigenteHasta,
        string $soportePath,
    ): EventoCalificado {
        Gate::forUser($user)->authorize('register', [EventoCalificado::class, $tipo, $comercioId]);

        $desde = CarbonImmutable::instance($vigenteDesde)->startOfDay();
        $hasta = $this->vigenteHasta($tipo, $desde, $vigenteHasta);
        $this->validate($tipo, $comercioId, $desde, $hasta, $soportePath);

        return DB::transaction(function () use ($user, $clienteId, $comercioId, $tipo, $desde, $hasta, $soportePath) {
            $evento = EventoCalificado::query()->create([
                'cliente_id' => $clienteId,
                'comercio_id' => $comercioId,
                'tipo' => $tipo,
                'vigente_desde' => $desde,
                'vigente_hasta' => $hasta,
                'soporte_path' => $soportePath,
                'registrado_por' => $user->id,
            ]);

            ($this->raise)($clienteId, MotivoCambioNivel::AscensoEvento);

            return $evento;
        });
    }

    /**
     * @throws ValidationException
     */
    private function vigenteHasta(
        TipoEventoCalificado $tipo,
        CarbonImmutable $desde,
        ?CarbonInterface $vigenteHasta,
    ): CarbonImmutable {
        if ($vigenteHasta !== null) {
            return CarbonImmutable::instance($vigenteHasta)->startOfDay();
        }

        if ($tipo === TipoEventoCalificado::MatriculaColegio) {
            throw ValidationException::withMessages([
                'vigente_hasta' => 'Escribe hasta cuándo va la matrícula.',
            ]);
        }

        $meses = config('niveles.event_rules.compra_inmueble.default_validity_months');

        return $desde->addMonths(is_int($meses) ? $meses : 12);
    }

    /**
     * @throws ValidationException
     */
    private function validate(
        TipoEventoCalificado $tipo,
        int $comercioId,
        CarbonImmutable $desde,
        CarbonImmutable $hasta,
        string $soportePath,
    ): void {
        $errors = [];

        if (trim($soportePath) === '') {
            $errors['soporte_path'] = 'Adjunta el soporte del evento.';
        }

        if ($hasta->lt($desde)) {
            $errors['vigente_hasta'] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        $comercio = Comercio::query()->with('categoria')->find($comercioId);

        if ($tipo === TipoEventoCalificado::MatriculaColegio && $comercio?->categoria?->slug !== 'colegio') {
            $errors['comercio_id'] = 'Las matrículas solo se registran en un colegio.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
