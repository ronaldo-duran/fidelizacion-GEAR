import { expect, test } from '@playwright/test';

// Panel administrativo (E10-01): el ingreso a 360 px y el tablero a 1024 px.
// Usa el administrador del grupo que siembra DatabaseSeeder (contraseña de la factory).

const sinDesborde = async (page) => {
    const ancho = await page.evaluate(() => document.documentElement.scrollWidth);
    expect(ancho).toBeLessThanOrEqual(page.viewportSize()?.width ?? 0);
};

test.describe('ingreso a 360 px', () => {
    test.use({ viewport: { width: 360, height: 760 } });
    test.skip(({ isMobile }) => !isMobile, 'El ingreso se prueba en celular.');

    test('el ingreso del panel muestra el diseño y no se desborda', async ({ page }) => {
        await page.goto('/admin/login');

        await expect(page.getByRole('heading', { name: 'Ingreso al sistema' })).toBeVisible();
        await expect(page.getByText('logo del grupo')).toBeVisible();
        await expect(page.getByText('Mantener la sesión en este celular')).toBeVisible();
        await expect(page.getByText('Olvidé mi contraseña')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Ingresar' })).toBeVisible();
        await sinDesborde(page);
    });
});

test.describe('tablero a 1024 px', () => {
    test.use({ viewport: { width: 1024, height: 800 } });
    test.skip(({ isMobile }) => isMobile, 'El tablero se prueba en escritorio.');

    test('el administrador del grupo entra y ve el tablero del mes', async ({ page }) => {
        await page.goto('/admin/login');
        await page.getByLabel('Correo').fill('admin@example.com');
        await page.locator('input[type="password"]').fill('password');
        await page.getByRole('button', { name: 'Ingresar' }).click();

        await expect(page.getByRole('heading', { name: 'Tablero del grupo' })).toBeVisible();
        await expect(page.getByText('Clientes inscritos')).toBeVisible();
        await expect(page.getByText('Clientes por nivel')).toBeVisible();
        await expect(page.getByText('Comercios por actividad')).toBeVisible();
        await sinDesborde(page);
    });
});
