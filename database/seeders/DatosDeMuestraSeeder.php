<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Compras\AnnulCompra;
use App\Actions\Compras\RegisterCompra;
use App\Compras\NuevaCompra;
use App\Enums\CanalCompra;
use App\Enums\TipoDocumento;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Clientes y compras de ejemplo para presentar el programa con información:
 * historia del mes anterior y del mes en curso, en varios comercios y sedes,
 * con compras pendientes, una anulada, clientes en los cuatro niveles y
 * comercios que dejaron de registrar. Las fechas son relativas al día en que
 * se siembra, así el tablero siempre compara "este mes" con "el anterior".
 *
 * Las compras pasan por las Actions reales (`RegisterCompra`, `AnnulCompra`):
 * el libro, los saldos y los niveles quedan como en producción. Lo llama
 * `DatabaseSeeder` solo con `SEMBRAR_DATOS_DE_MUESTRA=true` y nunca en
 * producción; las reglas base ya deben regir desde el mes anterior.
 */
class DatosDeMuestraSeeder extends Seeder
{
    /**
     * Documento del cliente => meses antes del mes anterior en que se inscribió
     * (0 = en el mes anterior, -1 = en el mes en curso) y día.
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private const CLIENTES = [
        '900000001' => [3, 12],
        '900000002' => [2, 3],
        '900000003' => [2, 21],
        '900000004' => [1, 2],
        '900000005' => [1, 15],
        '900000006' => [1, 28],
        '900000007' => [0, 4],
        '900000008' => [0, 17],
        '900000009' => [0, 26],
        '900000010' => [-1, 1],
        '900000011' => [-1, 3],
        '900000012' => [-1, 5],
    ];

    /**
     * Compras: [mes ('anterior' | 'actual'), día, hora, NIT del comercio, sede,
     * documento, monto]. En el mes en curso, un día que todavía no llega se
     * corre a hoy. El colegio y Polo Norte solo compran a comienzos del mes
     * anterior: quedan como comercios sin registros recientes.
     *
     * @var list<array{0: string, 1: int, 2: string, 3: string, 4: string, 5: string, 6: int}>
     */
    private const COMPRAS = [
        ['anterior', 2, '12:30', '800100100', 'Sede principal', '900000001', 85000],
        ['anterior', 3, '19:10', '800200200', 'Sede principal', '900000002', 64000],
        ['anterior', 4, '09:00', '800300300', 'Sede principal', '900000001', 450000],
        ['anterior', 5, '13:00', '800900900', 'Sede principal', '900000003', 120000],
        ['anterior', 6, '16:30', '800800800', 'Sede principal', '900000007', 22000],
        ['anterior', 7, '15:20', '800700700', 'Sede principal', '900000004', 18000],
        ['anterior', 8, '10:00', '800300300', 'Sede principal', '900000007', 380000],
        ['anterior', 9, '20:00', '801101101', 'Sede principal', '900000005', 72000],
        ['anterior', 10, '17:00', '800800800', 'Sede principal', '900000004', 15000],
        ['anterior', 11, '12:45', '800100100', 'Sede principal', '900000006', 96000],
        ['anterior', 16, '19:40', '800200200', 'Sede Norte', '900000003', 58000],
        ['anterior', 18, '13:15', '801001001', 'Sede principal', '900000002', 110000],
        ['anterior', 22, '12:00', '800900900', 'Sede principal', '900000005', 135000],
        ['anterior', 24, '20:30', '801101101', 'Sede principal', '900000008', 66000],
        ['anterior', 26, '14:10', '800700700', 'Sede principal', '900000009', 21000],
        ['anterior', 28, '13:30', '800100100', 'Sede principal', '900000002', 79000],
        ['actual', 1, '12:20', '800100100', 'Sede principal', '900000001', 92000],
        ['actual', 2, '19:30', '800200200', 'Sede principal', '900000002', 71000],
        ['actual', 3, '13:10', '800900900', 'Sede principal', '900000003', 145000],
        ['actual', 4, '16:00', '800700700', 'Sede principal', '900000004', 24000],
        ['actual', 5, '20:15', '801101101', 'Sede principal', '900000005', 88000],
        ['actual', 6, '12:40', '801001001', 'Sede principal', '900000006', 118000],
        ['actual', 7, '18:50', '800200200', 'Sede Norte', '900000007', 69000],
        ['actual', 8, '13:00', '800100100', 'Sede principal', '900000008', 101000],
        ['actual', 9, '11:30', '800400400', 'Sede principal', '900000001', 3300000],
        ['actual', 10, '15:00', '801201201', 'Sede principal', '900000009', 45000],
        ['actual', 11, '19:20', '800200200', 'Sede principal', '900000010', 83000],
        ['actual', 12, '12:50', '800900900', 'Sede principal', '900000002', 1700000],
        ['actual', 13, '17:10', '800700700', 'Sede principal', '900000011', 19000],
        ['actual', 14, '20:40', '801101101', 'Sede principal', '900000003', 600000],
        ['actual', 15, '13:20', '800100100', 'Sede principal', '900000004', 87000],
        ['actual', 16, '16:30', '801201201', 'Sede principal', '900000005', 52000],
        ['actual', 17, '12:10', '801001001', 'Sede principal', '900000006', 520000],
        ['actual', 18, '19:00', '800200200', 'Sede Norte', '900000012', 77000],
        ['actual', 19, '13:40', '800900900', 'Sede principal', '900000007', 128000],
        ['actual', 20, '18:20', '800100100', 'Sede principal', '900000010', 94000],
        ['actual', 21, '20:00', '801101101', 'Sede principal', '900000011', 73000],
        ['actual', 22, '12:30', '800700700', 'Sede principal', '900000008', 26000],
        ['actual', 23, '19:45', '800200200', 'Sede principal', '900000009', 81000],
        ['actual', 24, '13:15', '800100100', 'Sede principal', '900000012', 99000],
        // Pendientes: documentos que aún no se registran.
        ['actual', 16, '20:10', '800200200', 'Sede principal', '1023456700', 65000],
        ['actual', 22, '13:05', '800900900', 'Sede principal', '1023456701', 112000],
    ];

    /**
     * La compra que se anula después, el mismo día (índice en COMPRAS).
     */
    private const ANULADA = 30;

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@example.com')->sole();
        $bbc = Comercio::query()->where('nit', '800200200')->sole();
        Sede::query()->firstOrCreate(
            ['comercio_id' => $bbc->id, 'nombre' => 'Sede Norte'],
            ['direccion' => 'Por confirmar'],
        );

        try {
            $this->sembrarClientes();
            $this->sembrarCompras($admin);
        } finally {
            self::reloj(null);
        }
    }

    /**
     * El primer día del mes anterior a hoy: desde ahí rigen las reglas base de
     * la muestra y empieza la historia de compras.
     */
    public static function inicio(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }

    /**
     * Fija (o libera, con null) el reloj de la aplicación para registrar cada
     * compra en su fecha, como si hubiera pasado ese día.
     */
    public static function reloj(?CarbonImmutable $momento): void
    {
        Carbon::setTestNow($momento);
        CarbonImmutable::setTestNow($momento);
    }

    private function sembrarClientes(): void
    {
        $inicio = self::inicio();
        $hoy = CarbonImmutable::now();

        foreach (self::CLIENTES as $clave => [$mesesAntes, $dia]) {
            // PHP convierte en entero una clave que solo tiene dígitos.
            $documento = (string) $clave;
            $fecha = $mesesAntes >= 0
                ? $inicio->subMonthsNoOverflow($mesesAntes)->addDays($dia - 1)->setTime(10, 0)
                : self::enElMesActual($hoy, $dia, '10:00');
            self::reloj($fecha);
            Cliente::query()->firstOrCreate(
                ['tipo_documento' => TipoDocumento::CC->value, 'documento' => $documento],
                ['celular' => '31' . substr($documento, -8)],
            );
        }

        self::reloj(null);
    }

    private function sembrarCompras(User $admin): void
    {
        $inicio = self::inicio();
        $hoy = CarbonImmutable::now();
        $sedes = Sede::query()->with('comercio')->get();

        foreach (self::COMPRAS as $i => [$mes, $dia, $hora, $nit, $sedeNombre, $documento, $monto]) {
            $fecha = $mes === 'anterior'
                ? $inicio->addDays($dia - 1)->setTimeFromTimeString($hora)
                : self::enElMesActual($hoy, $dia, $hora);
            $sede = $sedes->first(
                static fn (Sede $sede): bool => $sede->comercio?->nit === $nit && $sede->nombre === $sedeNombre,
            );

            if ($sede === null) {
                continue;
            }

            self::reloj($fecha);
            $registro = app(RegisterCompra::class)($admin, new NuevaCompra(
                $sede->comercio_id,
                $sede->id,
                TipoDocumento::CC,
                $documento,
                $monto,
                'muestra-' . $i,
                CanalCompra::Cajero,
            ));

            if ($i === self::ANULADA) {
                self::reloj($fecha->addMinutes(20));
                app(AnnulCompra::class)($admin, $registro->transaccion->id, 'Compra de ejemplo anulada en caja');
            }
        }
    }

    /**
     * Un día del mes en curso a esa hora; si todavía no llega, hoy, unos
     * minutos antes de ahora.
     */
    private static function enElMesActual(CarbonImmutable $hoy, int $dia, string $hora): CarbonImmutable
    {
        $fecha = $hoy->startOfMonth()->addDays(min($dia, $hoy->day) - 1)->setTimeFromTimeString($hora);

        return $fecha->gt($hoy) ? $hoy->subMinutes(30 - min($dia, 29)) : $fecha;
    }
}
