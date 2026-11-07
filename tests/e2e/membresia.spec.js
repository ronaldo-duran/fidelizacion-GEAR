import { expect, test } from '@playwright/test';
import { completarRegistro, nuevoCliente, sinDesborde } from './ayudas.js';

// Compra de la suscripción Diamante (E6-01) a 360 px con la pasarela simulada (PAGOS_SIMULADA en el CI).
// Solo en el proyecto móvil: el registro manda un SMS y el tope es de 10 por IP y hora.

test.use({ viewport: { width: 360, height: 800 } });
test.skip(({ isMobile }) => !isMobile, 'Pantallas del cliente: solo en celular.');

test('el cliente compra la suscripción Diamante y queda Diamante al confirmarse el pago', async ({ page }) => {
    await page.goto('/registro');
    await completarRegistro(page, nuevoCliente());

    await page.getByRole('link', { name: /Suscripción Diamante/ }).click();
    await expect(page.getByRole('heading', { name: 'Suscripción Diamante' })).toBeVisible();
    await expect(page.getByText('puntos siguen contando')).toBeVisible();
    await sinDesborde(page);

    await page.getByText('Anual', { exact: true }).click();
    await page.getByRole('button', { name: 'Continuar al pago · $499.000' }).click();

    await expect(page.getByText('Pasarela de prueba. Aquí no se cobra nada.')).toBeVisible();
    await page.getByRole('button', { name: 'Aprobar pago' }).click();

    await expect(page.getByRole('heading', { name: 'Ya eres Diamante' })).toBeVisible();
    await expect(page.getByText('Recibimos tu pago de $499.000.')).toBeVisible();
    await sinDesborde(page);
});
