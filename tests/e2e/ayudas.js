import { readFileSync } from 'node:fs';
import { expect } from '@playwright/test';

// Ayudas compartidas por las pruebas E2E del cliente y del cajero.

export const aleatorio = (digitos) => String(Math.floor(Math.random() * 10 ** digitos)).padStart(digitos, '0');

export const nuevoCliente = () => ({
    documento: `10${aleatorio(8)}`,
    celular: `31${aleatorio(8)}`,
    nombre: 'Marcela Gómez',
    pin: '4826',
});

export const codigoDelSms = async (celular) => {
    await expect
        .poll(() => readFileSync('storage/logs/laravel.log', 'utf8').includes(`SMS para ${celular}`))
        .toBe(true);
    const lineas = readFileSync('storage/logs/laravel.log', 'utf8')
        .split('\n')
        .filter((linea) => linea.includes(`SMS para ${celular}`));

    return lineas.at(-1).match(/es (\d{6})\./)[1];
};

// Mide con las fuentes del diseño ya cargadas: con la de respaldo del sistema, más ancha, las
// pestañas pasan de 360 px por un instante y la prueba falla según cuánto tarde la carga.
export const sinDesborde = async (page) => {
    const ancho = await page.evaluate(async () => {
        await document.fonts.ready;

        return document.documentElement.scrollWidth;
    });
    expect(ancho).toBeLessThanOrEqual(page.viewportSize()?.width ?? 0);
};

// Completa el registro desde la pantalla "Únete al Club" ya abierta (por /registro o por el QR de la caja).
export const completarRegistro = async (page, cliente) => {
    await expect(page.getByRole('heading', { name: 'Únete al Club' })).toBeVisible();
    await sinDesborde(page);

    await page.getByLabel('Número de documento').fill(cliente.documento);
    await page.getByLabel('Nombre completo').fill(cliente.nombre);
    await page.getByLabel('Fecha de nacimiento').fill('1995-04-12');
    await page.getByLabel('Celular').fill(cliente.celular);
    await page.getByLabel('PIN de 4 números').fill(cliente.pin);
    await page.getByLabel('Repite el PIN').fill(cliente.pin);

    const crear = page.getByRole('button', { name: 'Crear mi cuenta' });
    await expect(crear).toBeDisabled();
    await expect(page.getByText('Marca la autorización para continuar')).toBeVisible();

    await page.getByRole('checkbox').check({ force: true });
    await expect(crear).toBeEnabled();
    await crear.click();

    await expect(page.getByRole('heading', { name: 'Verifica tu celular' })).toBeVisible();
    await sinDesborde(page);
    await page.getByLabel('Código del SMS').fill(await codigoDelSms(cliente.celular));
    await page.getByRole('button', { name: 'Verificar y crear mi cuenta' }).click();

    await enElInicio(page, cliente);
};

// Cierra la sesión del cliente desde su perfil (E8-05): el ícono del encabezado lleva ahí.
export const cerrarSesion = async (page) => {
    await page.getByRole('link', { name: 'Mi perfil' }).click();
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();
};

// El inicio del cliente (E8-03): saludo con el primer nombre y el nivel como título.
export const enElInicio = async (page, cliente) => {
    await expect(page.getByText(`${cliente.nombre.split(' ')[0]}, eres`)).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(/Bronce|Plata|Oro|Diamante/);
};
