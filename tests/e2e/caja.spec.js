import { readFileSync } from 'node:fs';
import { devices, expect, test } from '@playwright/test';
import { completarRegistro, nuevoCliente, sinDesborde } from './ayudas.js';

// Caja del comercio (E4-02) a 360 px: ingreso, teclado, documento no registrado con sus
// salidas, registro del cliente con el QR de la caja, compra registrada y anulación.
// Usa el cajero sembrado (DatabaseSeeder) y manda un SMS: solo en el proyecto móvil.

test.use({ viewport: { width: 360, height: 800 } });
test.skip(({ isMobile }) => !isMobile, 'Pantallas del cajero: solo en celular.');

const fixture = JSON.parse(readFileSync('tests/Fixtures/cajero-escenarios.json', 'utf8'));
const pesos = (monto) => `$${new Intl.NumberFormat('es-CO').format(monto)}`;

const ingresar = async (page) => {
    await page.goto('/caja');
    await expect(page.getByRole('heading', { name: 'Ingreso a la caja' })).toBeVisible();
    await sinDesborde(page);
    await page.getByLabel('Correo').fill('cajero@clubaponterivera.co');
    await page.getByLabel('Contraseña', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page.getByText('Monto de la compra')).toBeVisible();
};

const teclear = async (page, teclas) => {
    for (const tecla of teclas) {
        if (tecla === 'back') {
            await page.getByRole('button', { name: 'Borrar' }).click();
        } else {
            await page.getByRole('button', { name: tecla, exact: true }).click();
        }
    }
};

const identificar = async (page, monto, documento) => {
    await teclear(page, String(monto).split(''));
    await expect(page.locator('[data-monto]')).toHaveText(pesos(monto));
    await page.getByRole('button', { name: 'Continuar' }).click();
    await expect(page.getByText('¿Quién es el cliente?')).toBeVisible();
    await sinDesborde(page);
    await expect(page.getByLabel('Tipo de documento')).toHaveValue('CC');
    await page.getByLabel('Número de documento').fill(documento);
    await page.getByRole('button', { name: 'Buscar cliente' }).click();
};

test('el teclado arma el monto sin pasarse del máximo y sin mostrar puntos', async ({ page }) => {
    await ingresar(page);
    await expect(page.getByText(/pts|Suma/)).toHaveCount(0);

    for (const escenario of fixture.keypad) {
        await teclear(page, escenario.keys);
        await expect(page.locator('[data-monto]')).toHaveText(pesos(escenario.expected_amount));
        await expect(page.getByRole('button', { name: 'Continuar' })).toBeEnabled({
            enabled: escenario.expected_amount > 0,
        });

        while ((await page.locator('[data-monto]').textContent()) !== '$0') {
            await page.getByRole('button', { name: 'Borrar' }).click();
        }
    }
});

test('el cajero guarda la compra pendiente, registra al cliente con el QR y anula una compra', async ({
    page,
    browser,
}) => {
    test.setTimeout(90_000);
    const cliente = nuevoCliente();
    await ingresar(page);

    // Documento no registrado: el monto queda guardado y se guarda pendiente.
    await identificar(page, 85000, cliente.documento);
    await expect(page.getByText('No está registrada')).toBeVisible();
    await expect(page.getByText('Guardado')).toBeVisible();
    await sinDesborde(page);
    await page.getByRole('button', { name: /Guardar pendiente y seguir/ }).click();
    await expect(page.getByText('Compra pendiente guardada')).toBeVisible();

    // Corregir cédula vuelve al número sin perder el monto.
    await identificar(page, 85000, cliente.documento);
    await page.getByRole('button', { name: 'Corregir cédula' }).click();
    await expect(page.getByLabel('Número de documento')).toHaveValue(cliente.documento);
    await expect(page.getByText(pesos(85000))).toBeVisible();
    await page.getByRole('button', { name: 'Buscar cliente' }).click();

    // Registrar cliente ahora: el cliente escanea el QR y se registra desde su celular.
    await page.getByRole('button', { name: /Registrar cliente ahora/ }).click();
    await expect(page.getByText('Que el cliente escanee')).toBeVisible();
    await sinDesborde(page);
    const enlace = await page.locator('[data-qr-registro]').getAttribute('data-qr-registro');
    expect(enlace).toContain(`documento=${cliente.documento}`);

    const celular = await browser.newContext({ ...devices['Pixel 7'], viewport: { width: 360, height: 800 } });
    const paginaCliente = await celular.newPage();
    await paginaCliente.goto(enlace);
    await expect(paginaCliente.getByLabel('Número de documento')).toHaveValue(cliente.documento);
    await completarRegistro(paginaCliente, cliente);
    await celular.close();

    // Ya registrado: la compra llega a la pantalla completa y se anula desde ahí.
    await page.getByRole('button', { name: 'Atender al siguiente' }).click();
    await identificar(page, 50000, cliente.documento);
    await expect(page.getByText(cliente.nombre)).toBeVisible();
    await page.getByRole('button', { name: 'Registrar compra' }).last().click();
    await expect(page.getByText('Compra registrada')).toBeVisible();
    await expect(page.getByText('+50')).toBeVisible();
    await sinDesborde(page);

    page.once('dialog', (dialogo) => dialogo.accept());
    await page.getByRole('button', { name: 'Anular esta compra' }).click();
    await expect(page.getByText('Compra anulada.')).toBeVisible();
});
