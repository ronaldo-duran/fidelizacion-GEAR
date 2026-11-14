<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Credenciales\IssueCredencial;
use App\Actions\CredencialesApi\CreateCredencialApi;
use App\Actions\CredencialesApi\RevokeCredencialApi;
use App\Actions\CredencialesApi\RotateCredencialApi;
use App\Enums\AlcanceApi;
use App\Enums\PropositoCredencial;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\Regla;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Test data for the public API (E9-01). Merchants, branches and rules come
 * from compras-escenarios.json (only `acumulacion-base` stays active, so one
 * point per $1.000); credentials and customers from api-escenarios.json.
 */
final class ApiTestData
{
    public ComprasTestData $compras;

    /** @var array<string, User> keyed by fixture credential key */
    public array $credenciales = [];

    /** @var array<string, string> plain tokens, keyed by fixture credential key */
    public array $tokens = [];

    /** @var array<string, Cliente> keyed by fixture customer key */
    public array $clientes = [];

    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $path = dirname(__DIR__) . '/Fixtures/api-escenarios.json';

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * The cases of a fixture section, keyed by their description, for a Pest dataset.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function casos(string $seccion): array
    {
        $casos = [];

        foreach (self::fixture()[$seccion] as $caso) {
            $casos[$caso['_description']] = [$caso];
        }

        return $casos;
    }

    /**
     * The headers of an authentication case that revokes or rotates the
     * credential, and how many hours to travel after it.
     *
     * @param  array<string, mixed>  $caso
     * @return array{0: array<string, string>, 1: int}
     */
    public function tokenSegunCaso(array $caso): array
    {
        $credencial = $this->credenciales[$caso['credential']];
        $admin = User::factory()->create(['rol' => 'administrador_grupo']);
        $viejo = $this->headers($caso['credential']);

        if ($caso['token'] === 'revoked') {
            app(RevokeCredencialApi::class)($admin, $credencial->id);

            return [$viejo, 0];
        }

        $nuevo = app(RotateCredencialApi::class)($admin, $credencial->id);
        $headers = $caso['token'] === 'rotated' ? $viejo : ['Authorization' => 'Bearer ' . $nuevo->token];

        return [$headers, $caso['hours_after_rotation']];
    }

    /**
     * Runs the concurrency script in that many PHP processes at once against
     * the test database and counts their answers (201 or 200). A process that
     * failed answers 500 with its error.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, int> status => count
     */
    public static function enParalelo(string $modo, int $userId, array $payload, int $procesos): array
    {
        $conexion = config('database.default');
        $db = config("database.connections.{$conexion}");
        $db = is_array($db) ? $db : [];
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => (string) $conexion,
            'DB_HOST' => (string) ($db['host'] ?? ''),
            'DB_PORT' => (string) ($db['port'] ?? ''),
            'DB_DATABASE' => (string) ($db['database'] ?? ''),
            'DB_USERNAME' => (string) ($db['username'] ?? ''),
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];
        $script = __DIR__ . '/Scripts/api-concurrencia.php';
        // Todos arrancan la app primero y salen juntos tres segundos después.
        $salida = (string) ((int) floor(microtime(true) * 1000) + 3000);
        $comando = [PHP_BINARY, $script, $modo, (string) $userId, json_encode($payload, JSON_THROW_ON_ERROR), $salida];

        $resultados = Process::pool(static function (Pool $pool) use ($procesos, $comando, $env): void {
            for ($i = 0; $i < $procesos; $i++) {
                $pool->env($env)->timeout(60)->command($comando);
            }
        })->start()->wait();

        $estados = [];

        foreach ($resultados as $resultado) {
            $linea = json_decode(trim($resultado->output()), true);
            $estado = is_array($linea) && is_int($linea['estado'] ?? null) ? $linea['estado'] : 500;

            if ($estado === 500) {
                throw new RuntimeException('Proceso fallido: ' . $resultado->output() . $resultado->errorOutput());
            }

            $estados[$estado] = ($estados[$estado] ?? 0) + 1;
        }

        ksort($estados);

        return $estados;
    }

    public static function hoy(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::fixture()['today']);
    }

    public static function seed(): self
    {
        $data = app(self::class);
        $data->compras = ComprasTestData::seed();
        $otras = array_diff(array_column(ComprasTestData::fixture()['rules'], 'codigo'), ['acumulacion-base']);
        Regla::query()->whereIn('codigo', $otras)->update(['activa' => false]);

        $admin = User::factory()->create(['rol' => 'administrador_grupo']);

        foreach (self::fixture()['credentials'] as $credencial) {
            $emitida = app(CreateCredencialApi::class)(
                $admin,
                $credencial['key'],
                $data->compras->sedes[$credencial['sede_id']],
                array_map(AlcanceApi::from(...), $credencial['abilities']),
            );
            $data->credenciales[$credencial['key']] = $emitida->credencial;

            if (($credencial['activo'] ?? true) === false) {
                // Desactivada sin pasar por la revocación: el token sigue en la base.
                $emitida->credencial->forceFill(['activo' => false])->save();
            }
            $data->tokens[$credencial['key']] = $emitida->token;
        }

        foreach (self::fixture()['customers'] as $cliente) {
            $data->clientes[$cliente['key']] = $data->cliente($cliente);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function cliente(array $datos): Cliente
    {
        $cliente = $this->compras->cliente($datos);
        $cliente->forceFill([
            'nombre_completo' => $datos['nombre_completo'],
            'celular' => $datos['celular'] ?? null,
            'email' => $datos['email'] ?? null,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'nivel_efectivo_desde' => CarbonImmutable::parse($datos['nivel_efectivo_desde']),
        ])->save();

        return $cliente->refresh();
    }

    public function comercioId(int $fixtureId): int
    {
        return $this->compras->comercios[$fixtureId];
    }

    /**
     * A benefit of a fixture merchant for every level, with no conditions or limits.
     *
     * @param  array<string, mixed>  $extra
     */
    public function beneficio(int $fixtureComercioId, array $extra = []): Beneficio
    {
        return Beneficio::query()->forceCreate([
            'comercio_id' => $this->comercioId($fixtureComercioId),
            'nombre' => 'Beneficio ' . Str::random(6),
            'tipo' => 'monto_fijo',
            'valores_por_nivel' => ['bronce' => 5000, 'plata' => 8000, 'oro' => 10000, 'diamante' => 15000],
            'condiciones' => [],
            'vigente_desde' => '2026-01-01',
            'activo' => true,
            ...$extra,
        ]);
    }

    /**
     * The short code the customer shows for a benefit (E4-04).
     */
    public static function codigo(Cliente $cliente, Beneficio $beneficio): string
    {
        return app(IssueCredencial::class)(
            $cliente,
            PropositoCredencial::Beneficio,
            $beneficio->id,
            $beneficio->comercio_id,
        )->credencial->codigo_corto;
    }

    /**
     * @return array<string, string>
     */
    public function headers(string $credencial, ?string $llave = null): array
    {
        // En una prueba la app no se reinicia entre peticiones: el guard de Sanctum
        // recordaría la credencial de la petición anterior.
        Auth::forgetGuards();

        return array_filter([
            'Authorization' => 'Bearer ' . $this->tokens[$credencial],
            'Idempotency-Key' => $llave,
        ]);
    }
}
