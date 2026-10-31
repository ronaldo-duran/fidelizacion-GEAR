<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Beneficios\RedeemBeneficio;
use App\Actions\Credenciales\IssueCredencial;
use App\Beneficios\EntregaBeneficio;
use App\Credenciales\CredencialEmitida;
use App\Enums\Nivel;
use App\Enums\PropositoCredencial;
use App\Enums\TipoDocumento;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\Redencion;
use App\Models\Sede;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Lee tests/Fixtures/redencion-escenarios.json (E5-02) y arma el catálogo de
 * E5-01 con los nombres de los comercios, una "Sede principal" y un cajero
 * por comercio, y los clientes del fixture.
 */
final class RedencionTestData
{
    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/redencion-escenarios.json'), true);

        return is_array($fixture) ? $fixture : [];
    }

    /**
     * Una sección del fixture como dataset de Pest, con un nombre por caso.
     *
     * @param  callable(array<string, mixed>): string  $nombre
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function dataset(string $seccion, callable $nombre): array
    {
        $casos = [];

        foreach (self::fixture()[$seccion] as $i => $caso) {
            $casos[($i + 1) . '. ' . $nombre($caso)] = [$caso];
        }

        return $casos;
    }

    /**
     * El catálogo sembrado, cada comercio con su nombre y su sede principal.
     */
    public static function seed(): void
    {
        BeneficiosTestData::seed();

        foreach (BeneficiosTestData::catalogo()['beneficios'] as $beneficio) {
            $comercio = Comercio::query()->where('nit', $beneficio['comercio_nit'])->sole();
            $comercio->forceFill(['razon_social' => $beneficio['comercio']])->save();
            Sede::query()->firstOrCreate(
                ['comercio_id' => $comercio->id, 'nombre' => 'Sede principal'],
                ['direccion' => 'Calle 1 # 2-3', 'activa' => true],
            );
        }
    }

    public static function cajero(string $nit): User
    {
        $comercio = Comercio::query()->where('nit', $nit)->sole();
        $sede = Sede::query()->where('comercio_id', $comercio->id)->where('nombre', 'Sede principal')->sole();

        return User::factory()->cajero($comercio->id, $sede->id)->create();
    }

    /**
     * @param  array<string, mixed>  $datos  un cliente del fixture
     */
    public static function cliente(array $datos): Cliente
    {
        $cliente = Cliente::query()->create([
            'tipo_documento' => TipoDocumento::from($datos['tipo_documento']),
            'documento' => $datos['documento'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
        ]);
        $cliente->forceFill([
            'nombre_completo' => $datos['nombre_completo'] ?? null,
            'nivel_efectivo' => Nivel::from($datos['nivel_efectivo']),
            'saldo_puntos' => $datos['saldo_puntos'] ?? 0,
        ])->save();

        return $cliente->refresh();
    }

    public static function emitir(Cliente $cliente, Beneficio $beneficio): CredencialEmitida
    {
        return app(IssueCredencial::class)(
            $cliente,
            PropositoCredencial::Beneficio,
            $beneficio->id,
            $beneficio->comercio_id,
        );
    }

    public static function entregar(
        User $cajero,
        Cliente $cliente,
        Beneficio $beneficio,
        ?int $monto,
        ?int $credencialId = null,
        ?string $soporte = null,
        ?string $referencia = null,
    ): Redencion {
        return app(RedeemBeneficio::class)($cajero, new EntregaBeneficio(
            $cliente->id,
            $beneficio->id,
            $credencialId,
            $monto,
            $soporte,
            $referencia ?? (string) Str::uuid(),
        ));
    }

    public static function redencionPrevia(
        User $cajero,
        Cliente $cliente,
        Beneficio $beneficio,
        string $fecha,
    ): Redencion {
        return Redencion::query()->forceCreate([
            'cliente_id' => $cliente->id,
            'beneficio_id' => $beneficio->id,
            'comercio_id' => $beneficio->comercio_id,
            'sede_id' => $cajero->sede_id,
            'valor_otorgado_cop' => 10000,
            'validado_por' => $cajero->id,
            'referencia' => (string) Str::uuid(),
            'created_at' => CarbonImmutable::parse($fecha),
        ]);
    }
}
