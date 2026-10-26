<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Ingreso al panel con los textos del diseño (E10-01, pantalla 1y). El
 * placeholder del logo y la franja de niveles se agregan con render hooks en
 * `AdminPanelProvider`. Un cajero o una cuenta inactiva reciben el mismo error
 * que una contraseña equivocada (lo resuelve la página de Filament).
 */
class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication) ? parent::getHeading() : 'Ingreso al sistema';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Administradores del Grupo Aponte Rivera';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Correo')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Contraseña')
            ->hint(filament()->hasPasswordReset()
                ? new HtmlString(Blade::render(
                    '<x-filament::link :href="filament()->getRequestPasswordResetUrl()" tabindex="-1">'
                    . 'Olvidé mi contraseña</x-filament::link>',
                ))
                : null)
            ->password()
            ->revealable()
            ->autocomplete('current-password')
            ->required();
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')->label('Mantener la sesión en este celular');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Ingresar');
    }
}
