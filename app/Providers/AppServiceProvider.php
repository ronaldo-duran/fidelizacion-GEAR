<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureCajero;
use App\Models\User;
use App\Sms\ArraySmsSender;
use App\Sms\LogSmsSender;
use App\Sms\ProveedorSmsSender;
use App\Sms\SmsSender;
use App\Support\FormatoColombiano;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, static fn (Application $app): SmsSender => $app->make(
            match (config('sms.driver')) {
                'log' => LogSmsSender::class,
                'proveedor' => ProveedorSmsSender::class,
                default => ArraySmsSender::class,
            },
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Las acciones de la caja también pasan por el middleware: un cajero desactivado no registra más.
        Livewire::addPersistentMiddleware([EnsureCajero::class]);

        // Webhooks de la pasarela (E6-01): un tope amplio por IP, para frenar abusos sin perder reintentos.
        // Sin cupo global: lo agotaría tráfico con firma falsa y dejaría por fuera los webhooks de Wompi.
        RateLimiter::for(
            'webhooks',
            static fn (Request $request): Limit => Limit::perMinute(120)->by((string) $request->ip()),
        );

        // API pública (E9-01, regla 12): un tope general y uno más bajo para consultar documentos.
        RateLimiter::for('api', static fn (Request $request): Limit => self::topeApi($request, 'requests_per_minute'));
        RateLimiter::for(
            'api-consultas',
            static fn (Request $request): Limit => self::topeApi($request, 'customer_lookups_per_minute'),
        );

        $formato = '\\' . FormatoColombiano::class;

        Blade::directive(
            'pesos',
            static fn (string $expression): string => "<?php echo {$formato}::pesos({$expression}); ?>",
        );

        Blade::directive(
            'puntos',
            static fn (string $expression): string => "<?php echo {$formato}::puntos({$expression}); ?>",
        );

        Blade::directive(
            'hora',
            static fn (string $expression): string => "<?php echo {$formato}::hora({$expression}); ?>",
        );
    }

    /**
     * El tope de la API por token: rotar una credencial le da al token nuevo su propia cuenta.
     */
    private static function topeApi(Request $request, string $clave): Limit
    {
        $tope = config("api.{$clave}");
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        $llave = $token instanceof PersonalAccessToken ? "token:{$token->id}" : 'ip:' . $request->ip();

        return Limit::perMinute(is_int($tope) ? $tope : 0)->by("{$clave}:{$llave}");
    }
}
