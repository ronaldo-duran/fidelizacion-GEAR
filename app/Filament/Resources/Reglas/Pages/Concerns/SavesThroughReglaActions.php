<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Pages\Concerns;

use App\Models\Regla;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Resource pages decide nothing: they delegate to the engine actions and show
 * their validation errors without saving.
 */
trait SavesThroughReglaActions
{
    /**
     * @param  Closure(User): Regla  $save
     */
    protected function saveThroughActions(Closure $save): Regla
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        try {
            return $save($user);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('No se guardó la regla')
                ->body(implode(' ', $exception->validator->errors()->all()))
                ->persistent()
                ->send();

            $this->halt(true);

            throw $exception;
        }
    }

    protected function parseVigenteDesde(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
