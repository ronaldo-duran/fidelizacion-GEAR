import { expect, test } from '@playwright/test';

// El kit solo existe fuera de producción; en el CI el E2E corre con APP_ENV=local.

test('el kit de interfaz carga en celular sin desbordarse', async ({ page }) => {
    await page.goto('/_ui');

    await expect(page.getByRole('heading', { name: 'Niveles' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Crear mi cuenta' }).first()).toBeVisible();

    const anchoDocumento = await page.evaluate(() => document.documentElement.scrollWidth);
    const anchoVentana = page.viewportSize()?.width ?? 0;
    expect(anchoDocumento).toBeLessThanOrEqual(anchoVentana);
});

test('la PWA enlaza su manifest', async ({ page }) => {
    await page.goto('/_ui');

    await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', /manifest\.webmanifest/);
});
