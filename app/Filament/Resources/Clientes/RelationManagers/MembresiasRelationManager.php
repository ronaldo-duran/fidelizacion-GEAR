<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Enums\Nivel;
use App\Filament\Resources\Clientes\Schemas\NivelPastilla;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Los periodos de membresía del cliente (E6-01), uno por pago aprobado, de
 * solo lectura.
 */
class MembresiasRelationManager extends RelationManager
{
    protected static string $relationship = 'membresias';

    protected static ?string $title = 'Membresías';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with(['plan', 'pago']))
            ->defaultSort(static function (Builder $query): Builder {
                $query->getQuery()->orderByDesc('vigente_hasta')->orderByDesc('id');

                return $query;
            })
            ->columns([
                TextColumn::make('vigente_desde')->label('Desde')->date('Y-m-d'),
                TextColumn::make('vigente_hasta')->label('Hasta')->date('Y-m-d'),
                TextColumn::make('nivel')
                    ->label('Nivel')
                    ->html()
                    ->formatStateUsing(static fn (Nivel $state): HtmlString => NivelPastilla::html($state)),
                TextColumn::make('plan.nombre')->label('Plan'),
                TextColumn::make('pago.referencia')->label('Pago'),
            ]);
    }
}
