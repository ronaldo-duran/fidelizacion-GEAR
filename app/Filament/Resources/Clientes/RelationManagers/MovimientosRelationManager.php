<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clientes\RelationManagers;

use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;
use App\Models\User;
use App\Panel\AlcancePanel;
use App\Support\FormatoColombiano;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * El libro de puntos del cliente: solo lectura (regla dura 3.4), cada
 * movimiento con la regla y la versión que lo calcularon (regla dura 3.3). El
 * administrador de comercio ve solo los de su comercio.
 */
class MovimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'movimientos';

    protected static ?string $title = 'Libro de puntos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static function (Builder $query): Builder {
                $user = Auth::user();

                abort_unless($user instanceof User, 403);

                return AlcancePanel::delComercio($query->with(['comercio', 'transaccion', 'regla', 'usuario']), $user);
            })
            ->defaultSort(static function (Builder $query): Builder {
                $query->getQuery()->orderByDesc('created_at')->orderByDesc('id');

                return $query;
            })
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('Y-m-d H:i'),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('puntos')
                    ->label('Puntos')
                    ->alignEnd()
                    ->color(static fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(static fn (int $state): string => ($state > 0 ? '+' : '−')
                        . FormatoColombiano::puntos(abs($state))),
                TextColumn::make('comercio.razon_social')->label('Comercio')->placeholder('—'),
                TextColumn::make('transaccion.referencia_externa')->label('Compra')->placeholder('—'),
                TextColumn::make('regla_aplicada')
                    ->label('Regla')
                    ->state(static fn (MovimientoPuntos $record): string => self::regla($record)),
                TextColumn::make('vigencia_hasta')->label('Vence')->date('Y-m-d')->placeholder('—'),
                TextColumn::make('motivo')->label('Motivo')->placeholder('—')->wrap(),
                TextColumn::make('usuario.name')->label('Usuario')->placeholder('Sistema'),
            ]);
    }

    /**
     * Un ajuste manual no tiene regla y lo dice (regla 4 de la spec).
     */
    private static function regla(MovimientoPuntos $movimiento): string
    {
        if ($movimiento->tipo === TipoMovimiento::AjusteManual) {
            return 'Ajuste manual';
        }

        $regla = $movimiento->regla;

        return $regla === null ? 'Sin regla' : "{$regla->codigo} v{$movimiento->regla_version}";
    }
}
