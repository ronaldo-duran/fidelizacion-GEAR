<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes\Tables;

use App\Enums\Nivel;
use App\Enums\TipoDocumento;
use App\Filament\Resources\Clientes\Schemas\NivelPastilla;
use App\Models\Cliente;
use App\Panel\BusquedaClientes;
use App\Support\FormatoColombiano;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->searchPlaceholder('Documento, nombre o celular')
            ->searchUsing(static fn (Builder $query, string $search): Builder => BusquedaClientes::aplicar(
                $query,
                $search,
            ))
            ->columns([
                TextColumn::make('documento')
                    ->label('Documento')
                    ->state(static fn (Cliente $record): string => "{$record->tipo_documento->value} "
                        . $record->documento),
                TextColumn::make('nombre_completo')->label('Nombre')->placeholder('Sin nombre'),
                TextColumn::make('celular')->label('Celular')->placeholder('—'),
                TextColumn::make('nivel_efectivo')
                    ->label('Nivel')
                    ->html()
                    ->formatStateUsing(static fn (Nivel $state): HtmlString => NivelPastilla::html($state)),
                TextColumn::make('saldo_puntos')
                    ->label('Saldo')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(static fn (int $state): string => FormatoColombiano::puntos($state)),
                TextColumn::make('created_at')->label('Registro')->date('Y-m-d')->sortable(),
            ])
            ->filters([
                // El tipo de documento va con el número (CLAUDE.md 3.10), con CC primero.
                SelectFilter::make('tipo_documento')
                    ->label('Tipo de documento')
                    ->options(TipoDocumento::class),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
