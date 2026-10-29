<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficios\Tables;

use App\Enums\TipoBeneficio;
use App\Models\Beneficio;
use App\Support\FormatoColombiano;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BeneficiosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('nombre')->label('Beneficio')->searchable()->wrap(),
                TextColumn::make('comercio.razon_social')->label('Comercio'),
                TextColumn::make('desde')
                    ->label('Desde')
                    ->state(static fn (Beneficio $record): string => self::desde($record)),
                TextColumn::make('vigente_hasta')->label('Hasta')->date('Y-m-d')->placeholder('Sin fin'),
                IconColumn::make('activo')->label('Activo')->boolean(),
            ])
            ->filters([TernaryFilter::make('activo')->label('Activo')])
            ->recordActions([EditAction::make()]);
    }

    /**
     * El nivel mínimo y su valor: "Oro · $18.000" o "Bronce · 10 %".
     */
    private static function desde(Beneficio $beneficio): string
    {
        $nivel = $beneficio->nivelMinimo();
        $valor = $beneficio->valorPara($nivel) ?? 0;
        $texto = $beneficio->tipo === TipoBeneficio::MontoFijo ? FormatoColombiano::pesos($valor) : "{$valor} %";

        return "{$nivel->getLabel()} · {$texto}";
    }
}
