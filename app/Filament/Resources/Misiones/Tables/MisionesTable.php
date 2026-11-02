<?php

declare(strict_types=1);

namespace App\Filament\Resources\Misiones\Tables;

use App\Models\Comercio;
use App\Models\Regla;
use App\Support\FormatoColombiano;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MisionesTable
{
    public static function configure(Table $table): Table
    {
        // Los nombres se leen una sola vez por petición, no uno por fila.
        $nombres = null;
        $comercio = static function (Regla $record) use (&$nombres): string {
            $nombres ??= Comercio::query()->toBase()->pluck('razon_social', 'id')->all();
            $comercioId = $record->definicion['comercio_id'] ?? null;
            $nombre = is_int($comercioId) ? ($nombres[$comercioId] ?? null) : null;

            return is_string($nombre) ? $nombre : '—';
        };

        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Misión')
                    ->state(static fn (Regla $record): mixed => $record->definicion['titulo'] ?? null)
                    ->description(static fn (Regla $record): string => $record->codigo),
                TextColumn::make('comercio')
                    ->label('Comercio')
                    ->state($comercio),
                TextColumn::make('puntos')
                    ->label('Puntos')
                    ->alignEnd()
                    ->state(static function (Regla $record): string {
                        $puntos = $record->definicion['puntos'] ?? 0;

                        return FormatoColombiano::puntos(is_int($puntos) ? $puntos : 0);
                    }),
                TextColumn::make('version')
                    ->label('Versión')
                    ->numeric(),
                TextColumn::make('vigente_desde')
                    ->label('Vigente desde')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->label('Nueva versión'),
            ]);
    }
}
