<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlantillasNotificacion\Schemas;

use App\Models\PlantillaNotificacion;
use App\Notificaciones\EjemplosPlantilla;
use App\Notificaciones\RenderPlantilla;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Asunto, cuerpo y estado de una plantilla, con las variables que admite
 * su evento y una vista previa con datos de ejemplo. Validar y guardar lo
 * hace `UpdatePlantillaNotificacion`.
 */
class PlantillaNotificacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(static fn (?PlantillaNotificacion $record): string => $record?->evento->getLabel() ?? 'Aviso')
                ->description('Los puntos son estatus: no escribas que se canjean o se gastan.')
                ->schema([
                    TextEntry::make('variables')
                        ->label('Variables que puedes usar')
                        ->state(static fn (?PlantillaNotificacion $record): string => self::variables($record)),
                    TextInput::make('asunto')->label('Asunto')->required()->maxLength(255)->live(onBlur: true),
                    Textarea::make('cuerpo')
                        ->label('Cuerpo')
                        ->helperText('Deja una línea en blanco entre párrafos.')
                        ->required()
                        ->rows(10)
                        ->live(debounce: 500),
                    Toggle::make('activa')->label('Activa')->helperText('Si la desactivas, este aviso no se envía.'),
                ]),
            Section::make('Vista previa')
                ->description('Con datos de ejemplo.')
                ->schema([
                    TextEntry::make('vista_previa')
                        ->hiddenLabel()
                        ->state(static fn (Get $get, ?PlantillaNotificacion $record): HtmlString => self::vistaPrevia(
                            $get,
                            $record,
                        ))
                        ->html(),
                ]),
        ]);
    }

    private static function variables(?PlantillaNotificacion $record): string
    {
        if ($record === null) {
            return '';
        }

        return implode(' ', array_map(
            static fn (string $variable): string => '{' . $variable . '}',
            $record->evento->variables(),
        ));
    }

    private static function vistaPrevia(Get $get, ?PlantillaNotificacion $record): HtmlString
    {
        if ($record === null) {
            return new HtmlString('');
        }

        $render = app(RenderPlantilla::class);
        $valores = EjemplosPlantilla::para($record->evento);
        $asunto = $get('asunto');
        $cuerpo = $get('cuerpo');

        return new HtmlString(
            '<p><strong>' . e($render->texto(is_string($asunto) ? $asunto : '', $valores)) . '</strong></p>'
            . $render->html($render->texto(is_string($cuerpo) ? $cuerpo : '', $valores)),
        );
    }
}
