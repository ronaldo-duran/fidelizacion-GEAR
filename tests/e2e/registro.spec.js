import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { codigoDelSms, completarRegistro, nuevoCliente, sinDesborde } from './ayudas.js';

// Registro, verificación, ingreso y recuperación del PIN del cliente (E8-02) a 360 px.
// En el CI el SMS va al log (SMS_DRIVER=log con APP_ENV=local): de ahí se lee el código.
// Solo en el proyecto móvil: cada registro manda un SMS y el tope es de 10 por IP y hora.

test.use({ viewport: { width: 360, height: 800 } });
test.skip(({ isMobile }) => !isMobile, 'Pantallas del cliente: solo en celular.');

const registrar = async (page, cliente) => {
    await page.goto('/registro');
    await completarRegistro(page, cliente);
};

test('el cliente se registra, verifica su celular y queda con sesión', async ({ page }) => {
    await registrar(page, nuevoCliente());

    await expect(page).toHaveURL(/\/inicio$/);
});

test('el cliente ingresa con su documento y su PIN', async ({ page }) => {
    const cliente = nuevoCliente();
    await registrar(page, cliente);
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();

    await expect(page.getByRole('heading', { name: 'Ingresa al Club' })).toBeVisible();
    await sinDesborde(page);
    await page.getByLabel('Número de documento').fill(cliente.documento);
    await page.getByLabel('PIN').fill('0000');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page.getByText('El documento o el PIN no coinciden.')).toBeVisible();

    await page.getByLabel('PIN').fill(cliente.pin);
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page.getByRole('heading', { name: `Hola, ${cliente.nombre}` })).toBeVisible();
});

test('el cliente recupera su PIN con el código del SMS', async ({ page }) => {
    // Entre dos SMS al mismo celular hay una espera de 60 s (E8-06, sin importar el propósito)
    // y la recuperación la calla para no delatar el documento: se espera a que pase.
    test.setTimeout(150_000);
    const cliente = nuevoCliente();
    await registrar(page, cliente);
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();
    await page.waitForTimeout(61_000);

    await page.getByRole('link', { name: 'Olvidé mi PIN' }).click();
    await expect(page.getByRole('heading', { name: 'Recupera tu PIN' })).toBeVisible();
    await sinDesborde(page);
    await page.getByLabel('Número de documento').fill(cliente.documento);
    await page.getByRole('button', { name: 'Enviarme el código' }).click();

    await expect(page.getByText('Si los datos coinciden, te enviamos un código')).toBeVisible();
    // El código de recuperación es el último SMS a ese celular (el del registro ya se usó).
    await expect
        .poll(
            () =>
                readFileSync('storage/logs/laravel.log', 'utf8')
                    .split('\n')
                    .filter((linea) => linea.includes(`SMS para ${cliente.celular}`)).length,
        )
        .toBe(2);
    await page.getByLabel('Código del SMS').fill(await codigoDelSms(cliente.celular));
    await page.getByLabel('PIN nuevo de 4 números').fill('7351');
    await page.getByLabel('Repite el PIN nuevo').fill('7351');
    await page.getByRole('button', { name: 'Guardar PIN nuevo' }).click();

    await expect(page.getByRole('heading', { name: `Hola, ${cliente.nombre}` })).toBeVisible();
});
