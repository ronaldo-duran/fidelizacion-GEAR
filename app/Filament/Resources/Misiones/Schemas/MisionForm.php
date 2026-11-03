<?php

declare(strict_types=1);

namespace App\Filament\Resources\Misiones\Schemas;

use App\Models\Comercio;
use App\Models\ComercioPromocionLimite;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Formulario guiado de una misión. Los textos los ve el cliente; nunca se
 * evalúan (regla dura 3.8). Los límites del comercio se muestran como ayuda
 * y los hace cumplir el motor al guardar.
 */
class MisionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Misión')
                    ->columns(2)
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Código')
                            ->helperText('Identifica la misión en todas sus versiones. No cambia.')
                            ->required()
                            ->alphaDash()
                            ->maxLength(100)
                            ->disabledOn('edit'),
                        Toggle::make('activa')
                            ->label('Activa')
                            ->default(true)
                            ->visibleOn('edit'),
                        DateTimePicker::make('vigente_desde')
                            ->label('Vigente desde')
                            ->helperText('Vacío: desde que guardes. No puede ser una fecha pasada.')
                            ->seconds(false),
                    ]),
                Section::make('Definición')
                    ->statePath('definicion')
                    ->columns(2)
                    ->schema([
                        Select::make('comercio_id')
                            ->label('Comercio')
                            ->options(self::comercioOptions(...))
                            ->default(self::comercioPropio(...))
                            ->required()
                            ->live()
                            ->helperText(static fn (Get $get): string => self::limite($get('comercio_id')))
                            ->disabled(static fn (string $operation): bool => $operation === 'edit'
                                || self::comercioPropio() !== null)
                            ->dehydrated(),
                        TextInput::make('titulo')
                            ->label('Título')
                            ->helperText('Lo ve el cliente, por ejemplo «Síguenos en Instagram».')
                            ->required()
                            ->maxLength(100),
                        Textarea::make('descripcion')
                            ->label('Qué debe hacer el cliente')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('puntos')
                            ->label('Puntos por cumplimiento')
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('max_por_cliente')
                            ->label('Veces por cliente')
                            ->helperText('Cuántas veces puede cumplirla un mismo cliente.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(1)
                            ->required(),
                        Toggle::make('requiere_soporte')
                            ->label('Pide pantallazo de soporte')
                            ->default(true),
                    ]),
            ]);
    }

    /**
     * El grupo elige entre todos los comercios; el administrador de comercio
     * solo tiene el suyo.
     *
     * @return array<int, string>
     */
    private static function comercioOptions(): array
    {
        $propio = self::comercioPropio();
        $query = Comercio::query();

        if ($propio !== null) {
            $query->whereKey($propio);
        }

        return $query->get()
            ->sortBy('razon_social')
            ->mapWithKeys(static fn (Comercio $comercio): array => [$comercio->id => $comercio->razon_social])
            ->all();
    }

    private static function comercioPropio(): ?int
    {
        $user = Auth::user();

        return $user instanceof User && ! $user->esAdministradorDelGrupo() ? $user->comercio_id : null;
    }

    private static function limite(mixed $comercioId): string
    {
        if (! is_numeric($comercioId)) {
            return 'Elige el comercio dueño de la misión.';
        }

        $limite = ComercioPromocionLimite::query()->where('comercio_id', (int) $comercioId)->first();

        return $limite !== null && $limite->permite_misiones
            ? "Puede dar hasta {$limite->puntos_max_por_mision} puntos por misión."
            : 'Este comercio no tiene misiones habilitadas por el grupo.';
    }
}
