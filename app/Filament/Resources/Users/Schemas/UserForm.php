<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RolUsuario;
use App\Models\Comercio;
use App\Models\Sede;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * El comercio y la sede se eligen de una lista según el rol: el administrador
 * del grupo no lleva comercio, el administrador de comercio lleva su comercio
 * y el cajero, su comercio y su sede. La sede debe ser de ese comercio.
 */
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
                    ->required()
                    ->live(),
                Select::make('comercio_id')
                    ->label('Comercio')
                    ->options(self::comercioOptions(...))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(self::selectSoleSede(...))
                    ->visible(static fn (Get $get): bool => self::rol($get) !== RolUsuario::AdministradorGrupo)
                    ->required()
                    ->rule(Rule::exists('comercios', 'id')),
                Select::make('sede_id')
                    ->label('Sede')
                    ->options(static fn (Get $get): array => self::sedeOptions($get('comercio_id')))
                    // Lista sin buscador (pocas sedes por comercio): la de buscador no muestra
                    // la sede que el formulario elige solo cuando el comercio tiene una.
                    ->native()
                    ->helperText('Solo las sedes del comercio elegido.')
                    ->visible(static fn (Get $get): bool => self::rol($get) === RolUsuario::Cajero)
                    ->required()
                    ->rule(static fn (Get $get): Exists => Rule::exists('sedes', 'id')
                        ->where('comercio_id', is_numeric($get('comercio_id')) ? (int) $get('comercio_id') : 0))
                    ->validationMessages(['exists' => 'Elige una sede del comercio seleccionado.']),
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
                Toggle::make('edita_reglas_json')
                    ->label('Puede usar el editor JSON de reglas')
                    ->helperText('Solo aplica al administrador del grupo.')
                    ->default(false),
            ]);
    }

    private static function rol(Get $get): ?RolUsuario
    {
        $rol = $get('rol');

        return $rol instanceof RolUsuario ? $rol : RolUsuario::tryFrom(is_string($rol) ? $rol : '');
    }

    /**
     * Si el comercio tiene una sola sede, queda elegida.
     */
    private static function selectSoleSede(Set $set, mixed $state): void
    {
        $sedes = self::sedeOptions($state);
        $set('sede_id', count($sedes) === 1 ? array_key_first($sedes) : null);
    }

    /**
     * @return array<int, string>
     */
    private static function comercioOptions(): array
    {
        return Comercio::query()->get()
            ->sortBy('razon_social')
            ->mapWithKeys(static fn (Comercio $comercio): array => [$comercio->id => $comercio->razon_social])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function sedeOptions(mixed $comercioId): array
    {
        if (! is_numeric($comercioId)) {
            return [];
        }

        return Sede::query()->where('comercio_id', (int) $comercioId)->get()
            ->sortBy('nombre')
            ->mapWithKeys(static fn (Sede $sede): array => [$sede->id => $sede->nombre])
            ->all();
    }
}
