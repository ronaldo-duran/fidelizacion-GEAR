<?php

declare(strict_types=1);

namespace App\Puntos;

/**
 * Lo que pinta el historial del cliente (E8-05): el título con la vigencia,
 * el saldo que cuenta para el nivel, el aviso de vencimiento y las filas
 * agrupadas por mes, del más nuevo al más viejo.
 */
final class HistorialDelCliente
{
    public readonly string $titulo;

    public readonly int $saldo;

    /** @var array{0: string, 1: string}|null la frase destacada y el detalle */
    public readonly ?array $avisoVencimiento;

    /** @var array<string, list<FilaHistorial>> mes => filas */
    public readonly array $meses;

    /**
     * @param  array{0: string, 1: string}|null  $avisoVencimiento
     * @param  array<string, list<FilaHistorial>>  $meses
     */
    public function __construct(string $titulo, int $saldo, ?array $avisoVencimiento, array $meses)
    {
        $this->titulo = $titulo;
        $this->saldo = $saldo;
        $this->avisoVencimiento = $avisoVencimiento;
        $this->meses = $meses;
    }
}
