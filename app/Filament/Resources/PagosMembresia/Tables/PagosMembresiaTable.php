<?php

declare(strict_types=1);

namespace App\Filament\Resources\PagosMembresia\Tables;

use App\Enums\EstadoPago;
use App\Models\PagoMembresia;
use App\Support\FormatoColombiano;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PagosMembresiaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(static function (Builder $query): Builder {
                $query->getQuery()->orderByDesc('id');

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('Y-m-d H:i'),
                TextColumn::make('cliente.documento')
                    ->label('Cliente')
                    ->searchable()
                    ->formatStateUsing(static fn (string $state, PagoMembresia $record): string => trim(
                        "{$record->cliente->tipo_documento->value} {$state} · {$record->cliente->nombre_completo}",
                        ' ·',
                    )),
                TextColumn::make('plan.nombre')->label('Plan'),
                TextColumn::make('monto_cop')
                    ->label('Monto')
                    ->formatStateUsing(static fn (int $state): string => FormatoColombiano::pesos($state)),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(static fn (EstadoPago $state): string => match ($state) {
                        EstadoPago::Aprobado => 'success',
                        EstadoPago::Pendiente => 'warning',
                        EstadoPago::Rechazado, EstadoPago::Anulado => 'danger',
                    }),
                TextColumn::make('aprobado_at')->label('Aprobado')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextColumn::make('referencia')->label('Referencia')->searchable()->copyable(),
                TextColumn::make('pasarela')->label('Pasarela')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('id_externo')
                    ->label('Id en la pasarela')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(EstadoPago::class),
            ]);
    }
}
