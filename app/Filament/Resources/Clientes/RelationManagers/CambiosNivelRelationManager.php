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
 * Los cambios del nivel efectivo del cliente, de solo lectura. Los ven los
 * dos roles de administrador.
 */
class CambiosNivelRelationManager extends RelationManager
{
    protected static string $relationship = 'cambiosNivel';

    protected static ?string $title = 'Cambios de nivel';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort(static function (Builder $query): Builder {
                $query->getQuery()->orderByDesc('created_at')->orderByDesc('id');

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('Y-m-d H:i'),
                TextColumn::make('nivel_anterior')
                    ->label('Antes')
                    ->html()
                    ->formatStateUsing(static fn (Nivel $state): HtmlString => NivelPastilla::html($state)),
                TextColumn::make('nivel_nuevo')
                    ->label('Ahora')
                    ->html()
                    ->formatStateUsing(static fn (Nivel $state): HtmlString => NivelPastilla::html($state)),
                TextColumn::make('motivo')->label('Motivo')->badge(),
            ]);
    }
}
