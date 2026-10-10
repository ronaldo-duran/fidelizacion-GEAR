<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RolUsuario;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('rol')
                    ->label('Rol')
                    ->options(RolUsuario::class)
                    ->default(RolUsuario::Cajero->value)
                    ->required(),
                TextInput::make('comercio_id')
                    ->label('Comercio')
                    ->numeric()
                    ->helperText('Vacío para el administrador del grupo.'),
                TextInput::make('sede_id')
                    ->label('Sede')
                    ->numeric()
                    ->helperText('Solo aplica a cajeros.'),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->required(static fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(static fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(static fn (string $state): string => Hash::make($state)),
                Toggle::make('activo')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
