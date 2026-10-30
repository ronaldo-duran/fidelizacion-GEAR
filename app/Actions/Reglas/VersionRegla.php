<?php

declare(strict_types=1);

namespace App\Actions\Reglas;

use App\Enums\DefinicionSource;
use App\Enums\TipoRegla;
use App\Models\Regla;
use App\Models\User;
use App\Reglas\BonoRegistroVigente;
use App\Reglas\ReglaAuditLogger;
use App\Reglas\ReglaDefinitionValidator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Saves a rule change as `version + 1` and closes the previous version at the
 * same instant. It never touches an existing definition (hard rule 3.2).
 *
 * Concurrency: the latest version is read with a lock and must be the one the
 * administrator edited; the unique constraint on (codigo, version) is the
 * database safety net.
 */
class VersionRegla
{
    private readonly ReglaDefinitionValidator $validator;

    private readonly ReglaAuditLogger $auditLogger;

    private readonly BonoRegistroVigente $bonos;

    public function __construct(
        ReglaDefinitionValidator $validator,
        ReglaAuditLogger $auditLogger,
        BonoRegistroVigente $bonos,
    ) {
        $this->validator = $validator;
        $this->auditLogger = $auditLogger;
        $this->bonos = $bonos;
    }

    /**
     * @param  Regla  $base  version the change was made on
     * @param  array<string, mixed>  $definicion
     *
     * @throws ValidationException
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") `activa` is data of the new version, not an execution mode.
     */
    public function __invoke(
        User $user,
        Regla $base,
        array $definicion,
        bool $activa = true,
        DefinicionSource $source = DefinicionSource::Form,
        ?CarbonInterface $vigenteDesde = null,
    ): Regla {
        Gate::forUser($user)->authorize('update', $base);

        if ($source === DefinicionSource::JsonEditor) {
            Gate::forUser($user)->authorize('editJson', Regla::class);
        }

        $this->validator->validate($base->tipo, $definicion);
        $start = $this->resolveStart($vigenteDesde);

        try {
            return DB::transaction(function () use ($user, $base, $definicion, $activa, $start): Regla {
                $query = Regla::query()->where('codigo', $base->codigo);
                $query->getQuery()->orderByDesc('version')->lockForUpdate();
                $latest = $query->firstOrFail();

                if ($latest->version !== $base->version) {
                    throw $this->staleVersion($latest);
                }

                if ($latest->vigente_hasta !== null || $start->lte($latest->vigente_desde)) {
                    throw ValidationException::withMessages([
                        'vigente_desde' => sprintf(
                            'La vigencia se solapa con la versión %d, que rige desde el %s.',
                            $latest->version,
                            $latest->vigente_desde->format('Y-m-d H:i'),
                        ),
                    ]);
                }

                if ($latest->tipo === TipoRegla::BonoRegistro && $activa) {
                    $this->bonos->assertNingunOtro($latest->codigo, $start);
                }

                $latest->vigente_hasta = $start;
                $latest->save();

                $new = Regla::query()->create([
                    'codigo' => $latest->codigo,
                    'tipo' => $latest->tipo,
                    'version' => $latest->version + 1,
                    'vigente_desde' => $start,
                    'activa' => $activa,
                    'definicion' => $definicion,
                    'creada_por' => $user->id,
                ]);

                $this->auditLogger->log($user, 'regla_versionada', $latest, $new);

                return $new;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->staleVersion($base);
        }
    }

    /**
     * Validity starts now or in the future, never in the past (hard rule 3.3).
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

    private function staleVersion(Regla $latest): ValidationException
    {
        return ValidationException::withMessages([
            'definicion' => "La regla cambió mientras la editabas (ya va en la versión {$latest->version}). "
                . 'Recarga y vuelve a intentarlo.',
        ]);
    }
}
