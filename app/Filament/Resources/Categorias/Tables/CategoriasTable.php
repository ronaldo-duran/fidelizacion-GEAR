<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categorias\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('comercios_count')
                    ->label('Comercios')
                    ->counts('comercios'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
