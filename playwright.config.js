import { defineConfig, devices } from '@playwright/test';

const puerto = process.env.E2E_PUERTO ?? '8000';
const urlBase = `http://127.0.0.1:${puerto}`;

// En sesiones de Claude Code en la nube el navegador ya viene instalado en otra ruta.
const ejecutable = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: urlBase,
        locale: 'es-CO',
        timezoneId: 'America/Bogota',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions: ejecutable ? { executablePath: ejecutable } : {},
    },
    projects: [
        // Cliente y cajero: celular (360-390 px).
        { name: 'movil', use: { ...devices['Pixel 7'] } },
        // Panel administrativo: escritorio.
        { name: 'escritorio', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } } },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${puerto}`,
        url: `${urlBase}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});
