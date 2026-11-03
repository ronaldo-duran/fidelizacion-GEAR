import { expect, test } from '@playwright/test';
import { completarRegistro, nuevoCliente, sinDesborde } from './ayudas.js';

// Misiones del cliente (E3-06) a 360 px: un cliente recién registrado abre la misión sembrada de
// El Rancho de Javi, adjunta el pantallazo y la deja en revisión del comercio.
// Solo en el proyecto móvil: el registro manda un SMS y el tope es de 10 por IP y hora.

test.use({ viewport: { width: 360, height: 800 } });
test.skip(({ isMobile }) => !isMobile, 'Pantallas del cliente: solo en celular.');

// Un PNG real de 1×1 como pantallazo.
const pantallazo = {
    name: 'pantallazo.png',
    mimeType: 'image/png',
    buffer: Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        'base64',
    ),
};

test('el cliente envía el pantallazo de una misión y queda en revisión', async ({ page }) => {
    await page.goto('/registro');
    await completarRegistro(page, nuevoCliente());

    await page.getByRole('link', { name: /Misiones/ }).click();
    await expect(page.getByRole('heading', { name: 'Misiones', exact: true })).toBeVisible();
    await sinDesborde(page);

    const mision = page.locator('article', { hasText: 'Síguenos en Instagram' });
    await expect(mision.getByText('El Rancho de Javi', { exact: true })).toBeVisible();
    await expect(mision.getByText('+10 pts')).toBeVisible();

    await mision.getByRole('button', { name: 'Cumplir misión' }).click();
    await mision.getByRole('button', { name: 'Enviar a revisión' }).click();
    await expect(mision.getByText('Adjunta el pantallazo que pide la misión.')).toBeVisible();

    await mision.getByLabel('Pantallazo').setInputFiles(pantallazo);
    await expect(mision.getByRole('button', { name: 'Enviar a revisión' })).toBeVisible();
    await mision.getByRole('button', { name: 'Enviar a revisión' }).click();

    await expect(page.getByText('Enviamos tu misión a revisión.')).toBeVisible();
    await expect(mision.getByText('En revisión.')).toBeVisible();
    await expect(mision.getByRole('button', { name: 'Cumplir misión' })).toHaveCount(0);
    await sinDesborde(page);

    // Al volver, la misión sigue en revisión: el envío quedó guardado.
    await page.reload();
    await expect(page.locator('article', { hasText: 'Síguenos en Instagram' }).getByText('En revisión.')).toBeVisible();
});
