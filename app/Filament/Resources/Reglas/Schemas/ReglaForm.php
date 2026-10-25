<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reglas\Schemas;

use App\Enums\TipoRegla;
use App\Models\Comercio;
use App\Models\Regla;
use App\Models\Sede;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\StateCasts\BooleanStateCast;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Guided form per rule type, with the advanced JSON editor only for users who
 * hold the separate permission. The field and operator whitelists are the
 * ones in `resources/schemas/regla.json`.
 */
class ReglaForm
{
    private const NIVELES = ['bronce', 'plata', 'oro', 'diamante'];

    private const FIELDS = [
        'monto' => 'Monto (pesos)',
        'categoria_slug' => 'Categoría',
        'comercio_id' => 'Comercio',
        'sede_id' => 'Sede',
        'dia_semana' => 'Día de la semana',
        'hora' => 'Hora del día',
    ];

    private const OPERATORS = [
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'mayor' => 'Mayor que',
        'mayor_igual' => 'Mayor o igual que',
        'menor' => 'Menor que',
        'menor_igual' => 'Menor o igual que',
        'en' => 'Está en la lista',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Regla')
                    ->columns(2)
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Código')
                            ->helperText('Identifica la regla en todas sus versiones. No cambia.')
                            ->required()
                            ->alphaDash()
                            ->maxLength(100)
                            ->disabledOn('edit'),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoRegla::class)
                            ->required()
                            ->live()
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
                ...self::definicionComponents(),
            ]);
    }

    /**
     * The definition editors, shown by the `tipo` and `use_json_editor`
     * state. The rule simulator reuses them for its draft.
     *
     * @return list<Component>
     */
    public static function definicionComponents(): array
    {
        return [
            self::modoDefinicion(),
            Section::make('Definición en JSON')
                ->visible(self::usesJson(...))
                ->schema([
                    CodeEditor::make('definicion_json')
                        ->hiddenLabel()
                        ->language(Language::Json)
                        ->required(),
                ]),
            self::acumulacion(),
            self::niveles(),
            self::bonificacion(),
        ];
    }

    /**
     * Pestañas de la definición: "Vista" (el formulario guiado) y "Modo
     * desarrollo" (el JSON). Solo las ve quien tiene el permiso del editor
     * JSON; los demás siempre usan el formulario guiado.
     */
    private static function modoDefinicion(): ToggleButtons
    {
        return ToggleButtons::make('use_json_editor')
            ->label('Definición')
            ->options([0 => 'Vista', 1 => 'Modo desarrollo'])
            ->icons([0 => Heroicon::OutlinedEye, 1 => Heroicon::OutlinedCodeBracket])
            ->grouped()
            ->default(0)
            ->stateCast(app(BooleanStateCast::class, ['isStoredAsInt' => true]))
            ->live()
            ->dehydrated()
            ->visible(static fn (): bool => Gate::allows('editJson', Regla::class));
    }

    private static function acumulacion(): Group
    {
        return self::guidedSection(TipoRegla::Acumulacion, [
            self::integerInput('moneda_por_punto', 'Pesos por punto base', 1),
            Repeater::make('tasas_por_categoria')
                ->label('Tasas por categoría')
                ->minItems(1)
                ->columns(2)
                ->schema([
                    Select::make('categoria_slug')
                        ->label('Categoría')
                        ->options(OpcionesCondicion::categorias(...))
                        ->required()
                        ->distinct(),
                    self::integerInput('puntos_por_unidad', 'Puntos por cada unidad', 0),
                ]),
            Fieldset::make('Topes (vacío = sin tope)')
                ->statePath('topes')
                ->columns(3)
                ->schema([
                    self::integerInput('puntos_por_transaccion', 'Por transacción', 1, required: false),
                    self::integerInput('puntos_por_dia', 'Por día', 1, required: false),
                    self::integerInput('puntos_por_mes', 'Por mes', 1, required: false),
                ]),
            self::integerInput('vigencia_puntos_meses', 'Vigencia de los puntos (meses)', 1),
        ]);
    }

    private static function niveles(): Group
    {
        return self::guidedSection(TipoRegla::Niveles, [
            Repeater::make('umbrales')
                ->label('Umbrales de nivel')
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->columns(2)
                ->default(array_map(
                    static fn (string $nivel): array => ['nivel' => $nivel, 'umbral_puntos' => 0],
                    self::NIVELES,
                ))
                ->schema([
                    Select::make('nivel')
                        ->label('Nivel')
                        ->options(array_combine(self::NIVELES, array_map(ucfirst(...), self::NIVELES)))
                        ->disabled()
                        ->dehydrated(),
                    self::integerInput('umbral_puntos', 'Puntos mínimos', 0),
                ]),
            self::integerInput('proteccion_meses', 'Meses de protección antes de bajar', 0),
            Repeater::make('avisos_dias_antes')
                ->label('Avisos de descenso (días antes, de mayor a menor)')
                ->minItems(1)
                ->simple(self::integerInput('dias', 'Días antes', 0)),
        ]);
    }

    private static function bonificacion(): Group
    {
        return self::guidedSection(TipoRegla::Bonificacion, [
            self::integerInput('multiplicador', 'Multiplicador de puntos (2 a 10)', 2)->maxValue(10),
            Repeater::make('condiciones')
                ->label('Condiciones (se deben cumplir todas)')
                ->minItems(1)
                ->maxItems(5)
                ->columns(3)
                ->schema([
                    Select::make('campo')->label('Campo')->options(self::FIELDS)->required()->live(),
                    Select::make('operador')->label('Operador')->options(self::OPERATORS)->required()->live(),
                    ...self::valorComponents(),
                ]),
        ]);
    }

    /**
     * El valor de una condición según su campo: el monto se escribe; el
     * comercio, la sede, la categoría, el día y la hora se eligen de una lista
     * (varios a la vez con «Está en la lista»). `DefinicionFormMapper` guarda
     * en la definición los mismos ids, slugs y números de siempre.
     *
     * @return list<Component>
     */
    private static function valorComponents(): array
    {
        return [
            TextInput::make('valor')
                ->label('Valor')
                ->helperText('Para «Está en la lista», separa los valores con comas.')
                ->required()
                ->visible(static fn (Get $get): bool => ! DefinicionFormMapper::isSelectable($get('campo'))),
            Select::make('valor_opcion')
                ->label('Valor')
                ->options(static fn (Get $get): array => OpcionesCondicion::para($get('campo')))
                ->searchable()
                ->required()
                ->visible(static fn (Get $get): bool => DefinicionFormMapper::isSelectable($get('campo'))
                    && $get('operador') !== 'en'),
            Select::make('valor_opciones')
                ->label('Valores')
                ->options(static fn (Get $get): array => OpcionesCondicion::para($get('campo')))
                ->multiple()
                ->searchable()
                ->required()
                ->visible(static fn (Get $get): bool => DefinicionFormMapper::isSelectable($get('campo'))
                    && $get('operador') === 'en'),
        ];
    }

    /**
     * @param  array<int, Component>  $components
     */
    private static function guidedSection(TipoRegla $tipo, array $components): Group
    {
        return Group::make([
            Section::make("Definición: {$tipo->getLabel()}")
                ->statePath('definicion')
                ->schema($components),
        ])->visible(
            static fn (Get $get): bool => ! self::usesJson($get) && self::currentTipo($get) === $tipo,
        );
    }

    /**
     * El estado del selector llega como 0/1 (o booleano, tras deshidratar).
     */
    private static function usesJson(Get $get): bool
    {
        return in_array($get('use_json_editor'), [true, 1, '1'], true);
    }

    /**
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") `required` describes the field, not an execution mode.
     */
    private static function integerInput(string $name, string $label, int $min, bool $required = true): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->integer()
            ->minValue($min)
            ->required($required);
    }

    private static function currentTipo(Get $get): ?TipoRegla
    {
        $tipo = $get('tipo');

        if ($tipo instanceof TipoRegla) {
            return $tipo;
        }

        return is_string($tipo) ? TipoRegla::tryFrom($tipo) : null;
    }
}
