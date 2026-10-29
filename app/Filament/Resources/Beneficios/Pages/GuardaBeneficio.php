<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficios\Pages;

use App\Actions\Beneficios\SaveBeneficio;
use App\Models\Beneficio;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Crear y editar pasan el formulario a `SaveBeneficio`, que valida y guarda.
 * Si algo no cumple, el administrador ve el motivo y el formulario sigue abierto.
 */
trait GuardaBeneficio
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function guardar(array $data, ?Beneficio $beneficio = null): Beneficio
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        try {
            return app(SaveBeneficio::class)($user, $data, $beneficio);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('No se guardó el beneficio')
                ->body(implode(' ', $exception->validator->errors()->all()))
                ->persistent()
                ->send();

            $this->halt(true);

            throw $exception;
        }
    }
}
