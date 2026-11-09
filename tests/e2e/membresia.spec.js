import { expect, test } from '@playwright/test';
import { completarRegistro, nuevoCliente, sinDesborde } from './ayudas.js';

// Inicio y niveles (E8-03) y compra de la suscripción Diamante (E6-01) a 360 px, con la pasarela
// simulada (PAGOS_SIMULADA en el CI). Van en una sola prueba para registrar un solo cliente.
// Solo en el proyecto móvil: el registro manda un SMS y el tope es de 10 por IP y hora.

test.use({ viewport: { width: 360, height: 800 } });
test.skip(({ isMobile }) => !isMobile, 'Pantallas del cliente: solo en celular.');

test('el cliente ve su nivel, abre Niveles, compra la suscripción y su inicio queda en Diamante', async ({ page }) => {
    await page.goto('/registro');
    await completarRegistro(page, nuevoCliente());

    // Inicio: el nivel, lo que falta para el siguiente y "Para usar hoy" (carga diferida).
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Bronce');
    await expect(page.getByText('puntos más para')).toBeVisible();
    await expect(page.getByRole('progressbar')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Para usar hoy' })).toBeVisible();
    await expect(page.getByLabel('Cargando tus beneficios')).toHaveCount(0);
    await sinDesborde(page);

    // Niveles: las cuatro tarjetas, la del cliente marcada y la entrada a la suscripción.
    await page.getByRole('link', { name: 'Niveles', exact: true }).click();
    await expect(page.getByText('Los beneficios no gastan puntos.')).toBeVisible();
    await expect(page.getByText('TU NIVEL')).toHaveCount(1);
    await expect(page.getByText('Tres formas de llegar')).toBeVisible();
    await sinDesborde(page);

    await page.getByRole('link', { name: /Suscripción desde/ }).click();
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

    // El inicio ya está en Diamante y dice de dónde sale el nivel.
    await page.goto('/inicio');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Diamante');
    await expect(page.getByText(/Por tu suscripción, hasta el/)).toBeVisible();
    await expect(page.getByText('Estás en el nivel más alto del Club.')).toBeVisible();
    await sinDesborde(page);
});
