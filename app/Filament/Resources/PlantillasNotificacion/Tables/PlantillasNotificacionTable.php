<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlantillasNotificacion\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlantillasNotificacionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(static function (Builder $query): Builder {
                $query->getQuery()->orderBy('id');

                return $query;
            })
            ->columns([
                TextColumn::make('evento')->label('Aviso'),
                TextColumn::make('asunto')->label('Asunto')->wrap(),
                IconColumn::make('activa')->label('Activa')->boolean(),
                TextColumn::make('updated_at')->label('Última edición')->dateTime('Y-m-d H:i'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
