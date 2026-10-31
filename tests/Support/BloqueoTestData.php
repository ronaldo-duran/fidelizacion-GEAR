<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Ayudas de las pruebas de concurrencia: una segunda conexión a la misma base
 * y el tiempo máximo de espera de un bloqueo, con la sentencia de cada motor
 * (solo aquí, nunca en el código de la aplicación).
 */
final class BloqueoTestData
{
    private const CONEXION = 'segunda';

    public static function segundaConexion(): Connection
    {
        $default = config('database.default');
        config(['database.connections.' . self::CONEXION => config("database.connections.{$default}")]);

        return DB::connection(self::CONEXION);
    }

    /**
     * Cuánto espera un bloqueo la conexión antes de fallar; null vuelve al valor del motor.
     */
    public static function limitarEspera(Connection $conexion, ?int $segundos): void
    {
        match ($conexion->getDriverName()) {
            'pgsql' => $conexion->statement('SET lock_timeout = ' . ($segundos === null ? '0' : "'{$segundos}s'")),
            'mysql', 'mariadb' => $conexion->statement('SET SESSION innodb_lock_wait_timeout = ' . ($segundos ?? 50)),
            'sqlsrv' => $conexion->statement('SET LOCK_TIMEOUT ' . ($segundos === null ? -1 : $segundos * 1000)),
            default => throw new RuntimeException("Unsupported driver: {$conexion->getDriverName()}"),
        };
    }

    /**
     * Suelta la segunda conexión y borra lo que la prueba dejó guardado.
     */
    public static function vaciar(): void
    {
        $segunda = DB::connection(self::CONEXION);

        while ($segunda->transactionLevel() > 0) {
            $segunda->rollBack();
        }

        DB::purge(self::CONEXION);
        self::limitarEspera(DB::connection(), null);

        Schema::withoutForeignKeyConstraints(static function (): void {
            foreach (Schema::getTables() as $tabla) {
                if ($tabla['name'] !== 'migrations') {
                    DB::table($tabla['name'])->truncate();
                }
            }
        });
    }
}
