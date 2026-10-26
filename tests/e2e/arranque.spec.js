import { expect, test } from '@playwright/test';

// Requiere la base sembrada: php artisan migrate:fresh --seed

test('la página de inicio carga con la marca', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveTitle(/Club Aponte Rivera/);
    await expect(page.getByRole('link', { name: 'Ingreso al sistema' })).toBeVisible();
});

test('la página de inicio no se desborda en celular', async ({ page }) => {
    await page.goto('/');

    const anchoDocumento = await page.evaluate(() => document.documentElement.scrollWidth);
    const anchoVentana = page.viewportSize()?.width ?? 0;
    expect(anchoDocumento).toBeLessThanOrEqual(anchoVentana);
});

test('un administrador entra al panel', async ({ page }) => {
    await page.goto('/admin/login');

    await page.getByLabel('Correo').fill('admin@example.com');
    await page.locator('input[type="password"]').fill('password');
    await page.getByRole('button', { name: 'Ingresar' }).click();

    await expect(page).toHaveURL(/\/admin\/?$/);
    await expect(page.getByRole('heading', { name: 'Tablero del grupo' })).toBeVisible();
});

test('credenciales inválidas no dan acceso', async ({ page }) => {
    await page.goto('/admin/login');

    await page.getByLabel('Correo').fill('admin@example.com');
    await page.locator('input[type="password"]').fill('incorrecta');
    await page.getByRole('button', { name: 'Ingresar' }).click();

    await expect(page).toHaveURL(/\/admin\/login/);
});
