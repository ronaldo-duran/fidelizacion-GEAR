<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * "Olvidé mi contraseña" (E10-01, regla 2): responde igual exista o no el
 * correo, para no revelar qué cuentas existen. Filament avisa "no encontramos
 * un usuario con ese correo"; aquí ese caso recibe la misma respuesta de
 * enlace enviado, y solo el correo existente recibe el enlace.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    private const RESPUESTA = 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña.';

    protected function getFailureNotification(string $status): ?Notification
    {
        return $status === Password::INVALID_USER
            ? $this->getSentNotification(Password::RESET_LINK_SENT)
            : parent::getFailureNotification($status);
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return parent::getSentNotification($status)?->title(self::RESPUESTA)->body(null);
    }
}
