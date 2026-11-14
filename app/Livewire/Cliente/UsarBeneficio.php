<?php

declare(strict_types=1);

namespace App\Livewire\Cliente;

use App\Actions\Credenciales\IssueBeneficioCredencial;
use App\Beneficios\BeneficioParaCliente;
use App\Beneficios\BeneficiosParaCliente;
use App\Beneficios\CatalogoCliente;
use App\Beneficios\RevisionBeneficio;
use App\Credenciales\QrRenderer;
use App\Enums\EstadoBeneficio;
use App\Models\Cliente;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * Detalle y uso de un beneficio (E8-04, pantallas 1o y 1p). Solo se abre un
 * beneficio que `BeneficiosParaCliente` devuelve para el cliente (regla 7), y
 * solo se emite un código si está disponible (regla 8). El código lo emite
 * `IssueBeneficioCredencial` (E4-04, con tope por minuto) y al componente le llegan solo el SVG, el código
 * corto y el vencimiento: el token no queda en ninguna propiedad.
 */
#[Layout('components.layouts.cliente')]
#[Title('Usar beneficio')]
class UsarBeneficio extends Component
{
    #[Locked]
    public int $beneficioId;

    public ?string $imagenQr = null;

    public ?string $codigo = null;

    public ?int $venceMs = null;

    public bool $error = false;

    public function mount(string $beneficio, BeneficiosParaCliente $beneficios): void
    {
        abort_unless(ctype_digit($beneficio), 404);
        abort_if($beneficios->deBeneficio(self::cliente(), (int) $beneficio) === null, 404);

        $this->beneficioId = (int) $beneficio;
    }

    /**
     * Pide un código nuevo si el beneficio sigue disponible. Si dejó de estarlo
     * (por ejemplo, el cajero acaba de entregarlo), borra el código y la
     * pantalla pasa al estado nuevo.
     */
    public function renovar(
        BeneficiosParaCliente $beneficios,
        IssueBeneficioCredencial $issue,
        QrRenderer $renderer,
    ): void {
        $cliente = self::cliente();
        $estado = $beneficios->deBeneficio($cliente, $this->beneficioId);
        $this->imagenQr = $this->codigo = $this->venceMs = null;
        $this->error = false;

        if ($estado === null || $estado->estado !== EstadoBeneficio::Disponible) {
            return;
        }

        try {
            $emitida = $issue($cliente, $estado->beneficio);
            $this->imagenQr = $renderer->svg($emitida->token);
            $this->codigo = $emitida->codigoCorto;
            $this->venceMs = $emitida->venceAt->getTimestampMs();
        } catch (RuntimeException) {
            $this->error = true;
        }
    }

    public function render(
        BeneficiosParaCliente $beneficios,
        CatalogoCliente $catalogo,
        RevisionBeneficio $revision,
    ): View {
        $cliente = self::cliente();
        $estado = $beneficios->deBeneficio($cliente, $this->beneficioId);
        $hoy = CarbonImmutable::now()->startOfDay();
        $disponible = $estado?->estado === EstadoBeneficio::Disponible;

        return view('livewire.cliente.usar-beneficio', [
            'estado' => $estado,
            'cliente' => $cliente,
            'meta' => $estado === null ? '' : CatalogoCliente::meta($estado, $hoy),
            'usado' => self::usado($estado, $cliente, $revision),
            'vuelve' => $estado?->vuelve === null ? null : CatalogoCliente::fecha($estado->vuelve, $hoy),
            'otros' => $disponible || $estado === null ? [] : $catalogo->otrosParaHoy($cliente, $this->beneficioId),
            'servidorMs' => CarbonImmutable::now()->getTimestampMs(),
            'refrescoMs' => self::segundos('refresh_seconds') * 1000,
        ]);
    }

    /**
     * "3 de noviembre · Restaurante Sabor Caribe · Sede principal": el último uso, si lo usó.
     */
    private static function usado(?BeneficioParaCliente $estado, Cliente $cliente, RevisionBeneficio $revision): ?string
    {
        if ($estado?->estado !== EstadoBeneficio::Usado) {
            return null;
        }

        $uso = $revision->ultimoUso($cliente->id, $estado->beneficio->id);

        return $uso === null ? null : implode(' · ', array_filter([
            CatalogoCliente::fecha($uso->created_at->startOfDay(), CarbonImmutable::now()),
            $uso->comercio->razon_social,
            $uso->sede?->nombre,
        ], static fn (?string $parte): bool => $parte !== null && $parte !== ''));
    }

    private static function cliente(): Cliente
    {
        $cliente = auth('cliente')->user();

        abort_unless($cliente instanceof Cliente, 403);

        return $cliente;
    }

    private static function segundos(string $clave): int
    {
        $valor = config("credenciales.{$clave}");

        return is_int($valor) ? $valor : 0;
    }
}
