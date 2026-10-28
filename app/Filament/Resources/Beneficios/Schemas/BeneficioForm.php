<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficios\Schemas;

use App\Enums\Nivel;
use App\Enums\TipoBeneficio;
use App\Enums\VentanaBeneficio;
use App\Models\Comercio;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Formulario del beneficio (E5-01). Solo arma los campos: `SaveBeneficio`
 * valida los valores por nivel y las condiciones contra su esquema cerrado.
 * Un administrador de comercio solo ve su comercio.
 */
class BeneficioForm
{
    private const DIAS = [
        'lunes' => 'Lunes',
        'martes' => 'Martes',
        'miercoles' => 'Miércoles',
        'jueves' => 'Jueves',
        'viernes' => 'Viernes',
        'sabado' => 'Sábado',
        'domingo' => 'Domingo',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Beneficio')
                ->columns(2)
                ->schema([
                    Select::make('comercio_id')
                        ->label('Comercio')
                        ->options(self::comercioOptions(...))
                        ->required(),
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(160),
                    Textarea::make('condiciones_texto')
                        ->label('Condiciones que lee el cliente')
                        ->helperText('Texto para el cliente; no se evalúa.')
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Select::make('tipo')
                        ->label('Tipo')
                        ->options(TipoBeneficio::class)
                        ->default(TipoBeneficio::Porcentaje->value)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->required(),
                ]),
            Section::make('Valor por nivel')
                ->description('El nivel mínimo es el más bajo con valor. Un nivel vacío no lo recibe.')
                ->columns(4)
                ->schema(array_map(self::valorInput(...), Nivel::cases())),
            Section::make('Condiciones de uso')
                ->columns(3)
                ->schema([
                    Toggle::make('condiciones.es_cumpleanos')->label('Solo el día del cumpleaños'),
                    Toggle::make('condiciones.fin_de_semana')->label('Solo viernes, sábado y domingo'),
                    CheckboxList::make('condiciones.dia_semana')
                        ->label('Solo estos días')
                        ->options(self::DIAS)
                        ->columns(2),
                ]),
            Section::make('Límites y vigencia')
                ->columns(3)
                ->schema([
                    Select::make('max_por_cliente_ventana')
                        ->label('Límite por cliente')
                        ->options(VentanaBeneficio::class)
                        ->placeholder('Sin límite')
                        ->live(),
                    TextInput::make('max_por_cliente_cantidad')
                        ->label('Veces por ventana')
                        ->integer()
                        ->minValue(1)
                        ->required(static fn (Get $get): bool => filled($get('max_por_cliente_ventana'))),
                    TextInput::make('cupo_global')
                        ->label('Cupo global')
                        ->helperText('Redenciones entre todos los clientes. Vacío: sin cupo.')
                        ->integer()
                        ->minValue(1),
                    DatePicker::make('vigente_desde')->label('Vigente desde')->default(now())->required(),
                    DatePicker::make('vigente_hasta')->label('Vigente hasta')->helperText('Vacío: sin fin.'),
                    Toggle::make('requiere_soporte')
                        ->label('Pide soporte de la operación')
                        ->helperText('El cajero lo pide al redimir.'),
                    Toggle::make('activo')->label('Activo')->default(true),
                ]),
        ]);
    }

    private static function valorInput(Nivel $nivel): TextInput
    {
        $porcentaje = static fn (Get $get): bool => $get('tipo') !== TipoBeneficio::MontoFijo->value
            && $get('tipo') !== TipoBeneficio::MontoFijo;

        return TextInput::make("valores_por_nivel.{$nivel->value}")
            ->label($nivel->getLabel())
            ->integer()
            ->minValue(0)
            ->prefix(static fn (Get $get): ?string => $porcentaje($get) ? null : '$')
            ->suffix(static fn (Get $get): ?string => $porcentaje($get) ? '%' : null);
    }

    /**
     * @return array<int, string>
     */
    private static function comercioOptions(): array
    {
        $user = Auth::user();
        $query = Comercio::query();

        if (! $user instanceof User || ! $user->esAdministradorDelGrupo()) {
            $query->getQuery()->where('id', $user instanceof User ? ($user->comercio_id ?? 0) : 0);
        }

        return $query->get()
            ->sortBy('razon_social')
            ->mapWithKeys(static fn (Comercio $comercio): array => [$comercio->id => $comercio->razon_social])
            ->all();
    }
}
