<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureCajero;
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

        // Webhooks de la pasarela (E6-01): un tope amplio por IP, para frenar abusos sin perder reintentos,
        // y uno global, porque detrás del proxy la IP sale de X-Forwarded-For y quien ataca puede rotarla.
        RateLimiter::for(
            'webhooks',
            static fn (Request $request): array => [
                Limit::perMinute(120)->by('ip:' . $request->ip()),
                Limit::perMinute(600)->by('global'),
            ],
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
}
