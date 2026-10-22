<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Reglas\SimulateReglas;
use App\Enums\TipoRegla;
use App\Filament\Pages\Simulador\SimuladorForm;
use App\Filament\Pages\Simulador\SimuladorPresenter;
use App\Filament\Resources\Reglas\Schemas\DefinicionFormMapper;
use App\Models\Comercio;
use App\Models\Regla;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Tries a rule change before saving it. The page only collects the draft and
 * the sample purchases and shows the outcome; the calculation and every
 * check live in `SimulateReglas`. Nothing is saved here: saving stays in the
 * "Nueva versión" page of the rule.
 *
 * @property-read Schema $form
 */
class SimuladorReglas extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Simulador';

    protected static string|null|UnitEnum $navigationGroup = 'Niveles y reglas';

    protected static ?string $title = 'Simulador de reglas';

    protected static ?string $slug = 'simulador-reglas';

    protected string $view = 'filament.pages.simulador-reglas';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var list<string> */
    public array $errores = [];

    /** @var array{lineas: list<array<string, mixed>>, total_vigente: int, total_borrador: int}|null */
    public ?array $resultado = null;

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Regla::class);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return SimuladorForm::configure($schema->statePath('data'));
    }

    public function simulate(): void
    {
        $state = $this->form->getState();
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $tipo = TipoRegla::tryFrom(is_string($state['tipo'] ?? null) ? $state['tipo'] : '');
        $comercio = Comercio::query()->with('categoria')->find($state['comercio_id'] ?? null);
        $categoria = $comercio instanceof Comercio ? $comercio->categoria : null;

        if ($tipo === null || ! $comercio instanceof Comercio || $categoria === null) {
            $this->showErrors(['Elige la regla y el comercio.']);

            return;
        }

        try {
            [$definicion, $source] = DefinicionFormMapper::fromForm($tipo, $state);
            $resultado = app(SimulateReglas::class)(
                $user,
                is_string($state['codigo'] ?? null) ? $state['codigo'] : '',
                $tipo,
                $definicion,
                SimuladorForm::compras($comercio, $categoria->slug, $state['compras'] ?? null),
                $source,
            );
        } catch (ValidationException $exception) {
            $this->showErrors(array_values($exception->validator->errors()->all()));

            return;
        }

        $this->errores = [];
        $this->resultado = SimuladorPresenter::present($resultado);
    }

    /**
     * @param  list<string>  $errores
     */
    private function showErrors(array $errores): void
    {
        $this->errores = $errores;
        $this->resultado = null;
    }
}
