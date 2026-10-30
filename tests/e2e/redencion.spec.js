import { devices, expect, test } from '@playwright/test';
import { completarRegistro, nuevoCliente, sinDesborde } from './ayudas.js';

// Validar beneficio en la caja (E5-02) a 360 px: por documento, el cajero sembrado (El Rancho de Javi)
// entrega el kilo de carne a un cliente recién registrado y una segunda búsqueda lo muestra ya usado.
// El kilo de carne es de una vez por cliente y tiene cupo global: corre sobre la base recién sembrada.

test.use({ viewport: { width: 360, height: 800 } });
test.skip(({ isMobile }) => !isMobile, 'Pantallas del cajero: solo en celular.');

const buscarPorDocumento = async (page, documento) => {
    await page.getByRole('button', { name: 'Digitar código' }).click();
    await page.getByRole('button', { name: 'Buscar por documento' }).click();
    await expect(page.getByLabel('Tipo de documento')).toHaveValue('CC');
    await page.getByLabel('Número de documento').fill(documento);
    await page.getByRole('button', { name: 'Ver beneficios' }).click();
};

test('el cajero entrega un beneficio por documento y queda registrado como usado', async ({ page, browser }) => {
    test.setTimeout(90_000);
    const cliente = nuevoCliente();

    const celular = await browser.newContext({ ...devices['Pixel 7'], viewport: { width: 360, height: 800 } });
    const paginaCliente = await celular.newPage();
    await paginaCliente.goto('/registro');
    await completarRegistro(paginaCliente, cliente);
    await celular.close();

    await page.goto('/caja');
    await page.getByLabel('Correo').fill('cajero@clubaponterivera.co');
    await page.getByLabel('Contraseña', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page.getByText('Monto de la compra')).toBeVisible();

    // 1j: el selector lleva a validar beneficio; un solo botón y sin linterna.
    await page.getByRole('link', { name: 'Validar beneficio' }).click();
    await expect(page.getByRole('button', { name: 'Digitar código' })).toBeVisible();
    await expect(page.getByText(/linterna/i)).toHaveCount(0);
    await sinDesborde(page);

    await buscarPorDocumento(page, cliente.documento);
    await expect(page.getByText(cliente.nombre)).toBeVisible();
    await sinDesborde(page);
    await page.getByRole('button', { name: /Kilo de carne en El Rancho de Javi/ }).click();

    // 1k: VÁLIDO a pantalla completa, con el cliente y su nivel.
    await expect(page.getByText('VÁLIDO', { exact: true })).toBeVisible();
    await expect(page.getByText('10 % de descuento')).toBeVisible();
    await sinDesborde(page);
    await page.getByRole('button', { name: 'Confirmar entrega' }).click();

    // Monto de la cuenta con el teclado: el descuento se ve antes de entregar.
    await expect(page.getByText('Monto de la cuenta')).toBeVisible();
    for (const tecla of ['1', '2', '000']) {
        await page.getByRole('button', { name: tecla, exact: true }).click();
    }
    await expect(page.locator('[data-monto]')).toHaveText('$12.000');
    await expect(page.locator('[data-descuento]')).toHaveText('$1.200');
    await page.getByRole('button', { name: 'Entregar beneficio' }).click();

    await expect(page.getByText('Beneficio entregado')).toBeVisible();
    await expect(page.getByText('$1.200')).toBeVisible();
    await page.getByRole('button', { name: 'Escanear otro' }).click();

    // Una segunda búsqueda: el beneficio ya no se puede elegir.
    await buscarPorDocumento(page, cliente.documento);
    await expect(page.getByText('Ya lo usó')).toBeVisible();
    await expect(page.getByRole('button', { name: /Kilo de carne en El Rancho de Javi/ })).toHaveCount(0);
});
