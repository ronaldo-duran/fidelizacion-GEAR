<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\RelationManagers;

use App\Filament\Resources\Reglas\Schemas\DefinicionAsJson;
use App\Models\Regla;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only history: every version of the rule with its validity and its
 * original definition untouched.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Historial de versiones';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextEntry::make('definicion_json')
                    ->label('Definición')
                    ->html()
                    ->state(static fn (Regla $record): string => DefinicionAsJson::html($record)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->label('Versión'),
                TextColumn::make('vigente_desde')->label('Desde')->dateTime('Y-m-d H:i'),
                TextColumn::make('vigente_hasta')->label('Hasta')->dateTime('Y-m-d H:i')->placeholder('Abierta'),
                IconColumn::make('activa')->label('Activa')->boolean(),
                TextColumn::make('creator.name')->label('Guardada por')->placeholder('Sistema'),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver definición'),
            ]);
    }
}
