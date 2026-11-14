<?php

declare(strict_types=1);

namespace App\Clientes;

use App\Models\Cliente;
use App\Models\PreferenciaAviso;
use App\Support\FormatoColombiano;
use Illuminate\Support\Facades\Route;

/**
 * Lo que pinta el perfil del cliente (E8-05, reglas 7 a 12): el encabezado,
 * los datos enmascarados, los avisos por correo con su estado y los
 * documentos del programa.
 */
final class PerfilCliente
{
    private const OCULTO = '••••';

    public readonly string $nivel;

    public readonly ?string $iniciales;

    public readonly string $nombre;

    public readonly ?string $nivelDesde;

    public readonly string $documento;

    public readonly ?string $celular;

    public readonly ?string $email;

    /** "12 de abril de 1995"; null si no la ha agregado. */
    public readonly ?string $fechaNacimiento;

    public readonly bool $tieneCorreo;

    /** @var array<string, array{texto: string, encendido: bool}> evento => interruptor */
    public readonly array $avisos;

    /** @var array<string, string> texto => dirección; vacía si todavía no está */
    public readonly array $documentos;

    public readonly ?string $misDatos;

    public function __construct(Cliente $cliente)
    {
        $this->nivel = $cliente->nivel_efectivo->value;
        $this->iniciales = self::iniciales((string) $cliente->nombre_completo);
        $this->nombre = trim((string) $cliente->nombre_completo);
        $desde = $cliente->nivel_efectivo_desde;
        $this->nivelDesde = $desde === null ? null : $cliente->nivel_efectivo->getLabel()
            . ' desde ' . FormatoColombiano::mes($desde) . ' ' . $desde->format('Y');
        $this->documento = $cliente->tipo_documento->value . ' ' . self::enmascarar($cliente->documento);
        $this->celular = self::celular($cliente->celular);
        $this->email = trim((string) $cliente->email) === '' ? null : $cliente->email;
        $this->fechaNacimiento = $cliente->fecha_nacimiento === null
            ? null
            : FormatoColombiano::fechaConAnio($cliente->fecha_nacimiento);
        $this->tieneCorreo = $this->email !== null;
        $this->avisos = self::avisos($cliente->id, $this->tieneCorreo);
        $this->documentos = [
            'Reglamento del programa' => self::config('perfil.reglamento_url'),
            'Política de tratamiento de datos' => self::config('perfil.politica_datos_url'),
        ];
        // Consultar o eliminar los datos (E10-03): enlaza solo cuando exista su pantalla.
        $this->misDatos = Route::has('cliente.mis-datos') ? route('cliente.mis-datos') : null;
    }

    /**
     * "MG" de "Marcela Gómez Ríos": las dos primeras palabras. Sin nombre, null.
     */
    public static function iniciales(string $nombre): ?string
    {
        $palabras = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);
        $palabras = is_array($palabras) ? array_slice($palabras, 0, 2) : [];

        if ($palabras === []) {
            return null;
        }

        return implode('', array_map(
            static fn (string $palabra): string => mb_strtoupper(mb_substr($palabra, 0, 1)),
            $palabras,
        ));
    }

    /**
     * "•••• 6789": solo los cuatro últimos caracteres. Con cuatro o menos, todo oculto.
     */
    public static function enmascarar(string $documento): string
    {
        return mb_strlen($documento) <= 4 ? self::OCULTO : self::OCULTO . ' ' . mb_substr($documento, -4);
    }

    /**
     * "300 123 4567". Un número que no tiene 10 dígitos se muestra como está.
     */
    public static function celular(?string $celular): ?string
    {
        if ($celular === null || $celular === '') {
            return null;
        }

        return preg_match('/^(\d{3})(\d{3})(\d{4})$/', $celular, $partes) === 1
            ? "{$partes[1]} {$partes[2]} {$partes[3]}"
            : $celular;
    }

    /**
     * La dirección de la política de datos, también para el registro (regla 11).
     */
    public static function politicaDatos(): string
    {
        return self::config('perfil.politica_datos_url');
    }

    /**
     * @return array<string, array{texto: string, encendido: bool}>
     */
    private static function avisos(int $clienteId, bool $tieneCorreo): array
    {
        $apagados = PreferenciaAviso::query()->toBase()
            ->where('cliente_id', $clienteId)
            ->where('correo', false)
            ->pluck('evento')
            ->all();
        $avisos = [];

        foreach (PreferenciaAviso::interruptores() as $evento => $texto) {
            $avisos[$evento] = ['texto' => $texto, 'encendido' => $tieneCorreo && ! in_array($evento, $apagados, true)];
        }

        return $avisos;
    }

    private static function config(string $clave): string
    {
        $valor = config($clave);

        return is_string($valor) ? trim($valor) : '';
    }
}
