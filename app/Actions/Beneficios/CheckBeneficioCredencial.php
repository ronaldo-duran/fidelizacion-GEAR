<?php

declare(strict_types=1);

namespace App\Actions\Beneficios;

use App\Actions\Credenciales\ValidateCredencial;
use App\Beneficios\ChequeoBeneficio;
use App\Beneficios\RevisionBeneficio;
use App\Enums\PropositoCredencial;
use App\Enums\ResultadoBeneficio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Lo que el cajero escaneó o digitó en "Validar beneficio" (E5-02): valida
 * el código de beneficio (E4-04) sin gastarlo y dice si el beneficio está
 * disponible hoy para ese cliente. No escribe nada: la entrega la hace
 * `RedeemBeneficio`.
 */
class CheckBeneficioCredencial
{
    private readonly ValidateCredencial $validate;

    private readonly RevisionBeneficio $revision;

    public function __construct(ValidateCredencial $validate, RevisionBeneficio $revision)
    {
        $this->validate = $validate;
        $this->revision = $revision;
    }

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $cajero, string $entrada): ChequeoBeneficio
    {
        $validacion = ($this->validate)($cajero, $entrada, PropositoCredencial::Beneficio);
        $credencial = $validacion->credencial;

        if ($validacion->esValida() && $validacion->cliente !== null && $credencial?->beneficio_id !== null) {
            return $this->revision->revisar($validacion->cliente, $credencial->beneficio_id, $credencial->id);
        }

        if ($credencial !== null && $credencial->usado_at !== null) {
            return $this->revision->usada($credencial);
        }

        return ChequeoBeneficio::rechazado(ResultadoBeneficio::fromCredencial($validacion->resultado));
    }
}
