<?php

declare(strict_types=1);

namespace App\Filament\Resources\CumplimientosMision\Tables;

use App\Enums\EstadoCumplimiento;
use App\Models\CumplimientoMision;
use Closure;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Los más viejos primero: se revisan en el orden en que llegaron.
 */
class CumplimientosMisionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Enviado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->state(static fn (CumplimientoMision $record): string => $record->cliente === null
                        ? '—'
                        : "{$record->cliente->tipo_documento->value} {$record->cliente->documento}")
                    ->description(
                        static fn (CumplimientoMision $record): ?string => $record->cliente?->nombre_completo,
                    ),
                TextColumn::make('mision')
                    ->label('Misión')
                    ->state(self::definicion('titulo'))
                    ->description(static fn (CumplimientoMision $record): string => "Versión {$record->regla_version}"),
                TextColumn::make('puntos')
                    ->label('Puntos')
                    ->alignEnd()
                    ->state(self::definicion('puntos')),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(static fn (EstadoCumplimiento $state): string => match ($state) {
                        EstadoCumplimiento::Pendiente => 'warning',
                        EstadoCumplimiento::Aprobado => 'success',
                        EstadoCumplimiento::Rechazado => 'danger',
                    }),
                TextColumn::make('revisor.name')
                    ->label('Revisado por')
                    ->placeholder('—'),
                TextColumn::make('motivo_rechazo')
                    ->label('Motivo del rechazo')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoCumplimiento::class)
                    ->default(EstadoCumplimiento::Pendiente->value),
            ])
            ->recordActions(AccionesCumplimiento::all());
    }

    /**
     * Un dato de la versión de la misión con que el cliente lo envió.
     *
     * @return Closure(CumplimientoMision): mixed
     */
    private static function definicion(string $clave): Closure
    {
        return static fn (CumplimientoMision $record): mixed => $record->regla?->definicion[$clave] ?? null;
    }
}
