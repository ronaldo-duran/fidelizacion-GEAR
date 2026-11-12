<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sedes\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SedeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // El administrador de comercio solo puede elegir el suyo, y una sede ya creada
                // no cambia de comercio: sus compras y redenciones quedaron con ese comercio.
                // `CreateSede` y `EditSede` lo vuelven a exigir en el servidor.
                Select::make('comercio_id')
                    ->label('Comercio')
                    ->relationship('comercio', 'razon_social', self::comerciosDelUsuario(...))
                    ->default(static fn (): ?int => self::comercioFijo())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('direccion')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(255),
                Toggle::make('activa')
                    ->label('Activa')
                    ->default(true),
            ]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function comerciosDelUsuario(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->esAdministradorDelGrupo()) {
            $query->whereKey($user instanceof User ? $user->comercio_id ?? 0 : 0);
        }

        return $query;
    }

    /**
     * El comercio del administrador de comercio; el grupo elige.
     */
    private static function comercioFijo(): ?int
    {
        $user = Auth::user();

        return $user instanceof User && ! $user->esAdministradorDelGrupo() ? $user->comercio_id : null;
    }
}
