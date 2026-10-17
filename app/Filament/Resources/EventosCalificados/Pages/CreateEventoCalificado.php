<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventosCalificados\Pages;

use App\Actions\Niveles\RegisterEventoCalificado;
use App\Enums\TipoEventoCalificado;
use App\Filament\Resources\EventosCalificados\EventoCalificadoResource;
use App\Models\Cliente;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The page only finds the customer by document and hands the form to
 * RegisterEventoCalificado, which checks and saves everything.
 */
class CreateEventoCalificado extends CreateRecord
{
    protected static string $resource = EventoCalificadoResource::class;

    protected static ?string $title = 'Registrar evento calificado';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $hasta = self::text($data, 'vigente_hasta');

        try {
            return app(RegisterEventoCalificado::class)(
                $user,
                $this->clienteId(self::text($data, 'documento')),
                (int) self::text($data, 'comercio_id'),
                TipoEventoCalificado::from(self::text($data, 'tipo')),
                CarbonImmutable::parse(self::text($data, 'vigente_desde')),
                $hasta === '' ? null : CarbonImmutable::parse($hasta),
                self::text($data, 'soporte_path'),
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('No se registró el evento')
                ->body(implode(' ', $exception->validator->errors()->all()))
                ->persistent()
                ->send();

            $this->halt(true);

            throw $exception;
        }
    }

    /**
     * @throws ValidationException
     */
    private function clienteId(string $documento): int
    {
        $cliente = Cliente::query()->where('documento', str_replace(' ', '', trim($documento)))->first();

        if (! $cliente instanceof Cliente) {
            throw ValidationException::withMessages(['documento' => 'No hay un cliente con ese documento.']);
        }

        return $cliente->id;
    }

    /**
     * Form values arrive as text, an enum or an integer id.
     *
     * @param  array<string, mixed>  $data
     */
    private static function text(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return match (true) {
            $value instanceof TipoEventoCalificado => $value->value,
            is_string($value), is_int($value) => (string) $value,
            default => '',
        };
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }
}
