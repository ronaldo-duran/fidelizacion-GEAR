<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Ingreso propio con los textos de 1y (E10-01) y el restablecimiento
            // por correo de Filament, que necesita el correo de producción (E1-02).
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class)
            ->brandName('Club Aponte Rivera')
            // Paleta anclada para que el tono 600 (botones) sea exactamente
            // --color-brand (#1f5c4f): #226658 × 0.9 = #1f5c4f. Si cambia la marca
            // en docs/diseno/tokens, recalcular este valor.
            ->colors([
                'primary' => Color::generateV3Palette('#226658'),
                'gray' => Color::Stone,
            ])
            // La fuente se carga desde el tema (Fontsource), sin CDN.
            ->font('Figtree Variable', provider: LocalFontProvider::class)
            // Sidebar de 208 px y grupos en el orden de 1z; un grupo sin
            // recursos todavía no se muestra.
            ->sidebarWidth('13rem')
            ->navigationGroups(['Beneficios', 'Comercios', 'Niveles y reglas', 'Membresías', 'Configuración'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                static fn (): View => view('filament.auth.logo-placeholder'),
                scopes: Login::class,
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_END,
                static fn (): View => view('filament.auth.franja-niveles'),
                scopes: Login::class,
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
