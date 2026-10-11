<?php

declare(strict_types=1);

namespace App\Actions\Reglas;

use App\Enums\DefinicionSource;
use App\Enums\TipoRegla;
use App\Models\Regla;
use App\Models\User;
use App\Reglas\ReglaAuditLogger;
use App\Reglas\ReglaDefinitionValidator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates version 1 of a new rule (E3-01).
 */
class CreateRegla
{
    private readonly ReglaDefinitionValidator $validator;

    private readonly ReglaAuditLogger $auditLogger;

    public function __construct(ReglaDefinitionValidator $validator, ReglaAuditLogger $auditLogger)
    {
        $this->validator = $validator;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param  array<string, mixed>  $definicion
     *
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        string $codigo,
        TipoRegla $tipo,
        array $definicion,
        DefinicionSource $source = DefinicionSource::Form,
        ?CarbonInterface $vigenteDesde = null,
    ): Regla {
        Gate::forUser($user)->authorize('create', Regla::class);

        if ($source === DefinicionSource::JsonEditor) {
            Gate::forUser($user)->authorize('editJson', Regla::class);
        }

        $this->validator->validate($tipo, $definicion);
        $start = $this->resolveStart($vigenteDesde);
        $codigo = mb_strtolower(trim($codigo));

        try {
            return DB::transaction(function () use ($user, $codigo, $tipo, $definicion, $start): Regla {
                if (Regla::query()->where('codigo', $codigo)->toBase()->exists()) {
                    throw $this->duplicateCodigo();
                }

                $regla = Regla::query()->create([
                    'codigo' => $codigo,
                    'tipo' => $tipo,
                    'version' => 1,
                    'vigente_desde' => $start,
                    'activa' => true,
                    'definicion' => $definicion,
                    'creada_por' => $user->id,
                ]);

                $this->auditLogger->log($user, 'regla_creada', null, $regla);

                return $regla;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateCodigo();
        }
    }

    /**
     * Validity starts now or in the future, never in the past: changing a
     * rule affects what comes next, not what already happened (hard rule 3.3).
     */
    private function resolveStart(?CarbonInterface $vigenteDesde): CarbonImmutable
    {
        $now = CarbonImmutable::now()->startOfSecond();
        $start = $vigenteDesde === null ? $now : CarbonImmutable::instance($vigenteDesde)->startOfSecond();

        if ($start->lt($now)) {
            throw ValidationException::withMessages([
                'vigente_desde' => 'La vigencia no puede empezar en el pasado.',
            ]);
        }

        return $start;
    }

    private function duplicateCodigo(): ValidationException
    {
        return ValidationException::withMessages([
            'codigo' => 'Ya existe una regla con ese código. Guarda una versión nueva de esa regla.',
        ]);
    }
}
