<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Los eventos que avisan algo al cliente (E7-01). Su categoría, su lista
 * blanca de variables y sus textos iniciales viven en
 * `config/notificaciones.php`, espejo del fixture.
 */
enum EventoNotificacion: string implements HasLabel
{
    case Bienvenida = 'bienvenida';
    case SubidaNivel = 'subida_nivel';
    case AvisoDescenso = 'aviso_descenso';
    case MembresiaConfirmada = 'membresia_confirmada';
    case MembresiaPorVencer = 'membresia_por_vencer';
    case BeneficioUsado = 'beneficio_usado';

    public function esMercadeo(): bool
    {
        return $this->config('categoria') === 'mercadeo';
    }

    /**
     * @return list<string>
     */
    public function variables(): array
    {
        $variables = $this->config('variables');

        return is_array($variables) ? array_values(array_filter($variables, is_string(...))) : [];
    }

    public function asuntoInicial(): string
    {
        $asunto = $this->config('asunto');

        return is_string($asunto) ? $asunto : '';
    }

    public function cuerpoInicial(): string
    {
        $cuerpo = $this->config('cuerpo');

        return is_string($cuerpo) ? $cuerpo : '';
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Bienvenida => 'Bienvenida',
            self::SubidaNivel => 'Subida de nivel',
            self::AvisoDescenso => 'Aviso de descenso',
            self::MembresiaConfirmada => 'Membresía confirmada',
            self::MembresiaPorVencer => 'Membresía por vencer',
            self::BeneficioUsado => 'Beneficio usado',
        };
    }

    private function config(string $clave): mixed
    {
        return config('notificaciones.eventos.' . $this->value . '.' . $clave);
    }
}
