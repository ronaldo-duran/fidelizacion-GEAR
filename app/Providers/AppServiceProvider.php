<?php

declare(strict_types=1);

namespace App\Providers;

use App\Sms\ArraySmsSender;
use App\Sms\LogSmsSender;
use App\Sms\ProveedorSmsSender;
use App\Sms\SmsSender;
use App\Support\FormatoColombiano;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

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
