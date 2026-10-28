<?php

declare(strict_types=1);

namespace App\Actions\Beneficios;

use App\Beneficios\BeneficioValidator;
use App\Models\Beneficio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Crea o edita un beneficio (E5-01). Valida los campos, los valores por nivel
 * y las condiciones contra su esquema cerrado (regla dura 3.8) antes de
 * guardar. Editar cambia las próximas redenciones; las pasadas guardan su
 * valor otorgado (regla dura 3.11). Quien lo guarda debe alcanzar el comercio
 * de antes y el de después.
 */
class SaveBeneficio
{
    private readonly BeneficioValidator $validator;

    public function __construct(BeneficioValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $datos, ?Beneficio $beneficio = null): Beneficio
    {
        if ($beneficio !== null) {
            Gate::forUser($user)->authorize('update', $beneficio);
        }

        $validados = $this->validator->validate($datos);
        $comercioId = $validados['comercio_id'];

        Gate::forUser($user)->authorize('save', [Beneficio::class, is_numeric($comercioId) ? (int) $comercioId : 0]);

        return DB::transaction(static function () use ($validados, $beneficio): Beneficio {
            if ($beneficio === null) {
                return Beneficio::query()->create($validados);
            }

            $beneficio->fill($validados)->save();

            return $beneficio;
        });
    }
}
