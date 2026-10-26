<?php

declare(strict_types=1);

namespace App\Filament\Pages\Simulador;

use App\Enums\TipoRegla;
use App\Filament\Resources\Reglas\Schemas\DefinicionFormMapper;
use App\Filament\Resources\Reglas\Schemas\ReglaForm;
use App\Models\Comercio;
use App\Models\Regla;
use App\Models\Sede;
use App\Reglas\CompraData;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Form of the rule simulator: the draft, edited with the same guided form
 * (or JSON editor) as a new rule version, and the sample purchases.
 */
class SimuladorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Borrador')
                ->description('Elige la regla que quieres cambiar y ajusta su definición. No se guarda nada.')
                ->columns(2)
                ->schema([
                    Select::make('regla_id')
                        ->label('Regla')
                        ->options(self::reglaOptions(...))
                        ->required()
                        ->live()
                        ->afterStateUpdated(self::fillDraft(...)),
                    Hidden::make('codigo'),
                    Hidden::make('tipo'),
                ]),
            ...ReglaForm::definicionComponents(),
            Section::make('Compras de ejemplo')
                ->description('Se simulan en orden, como de un mismo cliente, para los topes por día y por mes.')
                ->schema([
                    Select::make('comercio_id')
                        ->label('Comercio')
                        ->options(self::comercioOptions(...))
                        ->required()
                        ->live(),
                    Repeater::make('compras')
                        ->label('Compras')
                        ->minItems(1)
                        ->maxItems(20)
                        ->columns(3)
                        ->default([['monto' => null, 'fecha' => null, 'sede_id' => null]])
                        ->schema([
                            TextInput::make('monto')
                                ->label('Monto (pesos)')
                                ->integer()
                                ->minValue(1)
                                ->required(),
                            DateTimePicker::make('fecha')
                                ->label('Fecha y hora')
                                ->seconds(false)
                                ->required(),
                            Select::make('sede_id')
                                ->label('Sede (opcional)')
                                ->options(static fn (Get $get): array => self::sedeOptions($get('../../comercio_id'))),
                        ]),
                ]),
        ]);
    }

    /**
     * The sample purchases of the form state, all of the chosen merchant.
     *
     * @return list<CompraData>
     */
    public static function compras(Comercio $comercio, string $categoriaSlug, mixed $compras): array
    {
        $result = [];

        foreach (is_array($compras) ? $compras : [] as $compra) {
            $monto = is_array($compra) ? ($compra['monto'] ?? null) : null;
            $fecha = is_array($compra) ? ($compra['fecha'] ?? null) : null;
            $sedeId = is_array($compra) ? ($compra['sede_id'] ?? null) : null;

            $result[] = new CompraData(
                is_numeric($monto) ? (int) $monto : 0,
                $categoriaSlug,
                $comercio->id,
                is_numeric($sedeId) ? (int) $sedeId : null,
                CarbonImmutable::parse(is_string($fecha) ? $fecha : 'now'),
            );
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private static function reglaOptions(): array
    {
        return Regla::inForceAt(CarbonImmutable::now())->get()
            ->filter(static fn (Regla $regla): bool => $regla->tipo !== TipoRegla::Niveles)
            ->sortBy('codigo')
            ->mapWithKeys(static fn (Regla $regla): array => [
                $regla->id => "{$regla->codigo} ({$regla->tipo->getLabel()}, v{$regla->version})",
            ])
            ->all();
    }

    /**
     * The chosen rule's version in force fills the draft.
     */
    private static function fillDraft(mixed $state, Set $set): void
    {
        $regla = Regla::query()->find($state);

        if (! $regla instanceof Regla) {
            return;
        }

        $form = DefinicionFormMapper::toForm($regla);

        foreach (['codigo', 'tipo', 'definicion', 'definicion_json'] as $key) {
            $set($key, $form[$key]);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function comercioOptions(): array
    {
        return Comercio::query()->get()
            ->filter(static fn (Comercio $comercio): bool => $comercio->activo)
            ->sortBy('razon_social')
            ->mapWithKeys(static fn (Comercio $comercio): array => [$comercio->id => $comercio->razon_social])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function sedeOptions(mixed $comercioId): array
    {
        $query = Sede::query();
        $query->getQuery()->where('comercio_id', is_numeric($comercioId) ? (int) $comercioId : 0);

        return $query->get()
            ->sortBy('nombre')
            ->mapWithKeys(static fn (Sede $sede): array => [$sede->id => $sede->nombre])
            ->all();
    }
}
