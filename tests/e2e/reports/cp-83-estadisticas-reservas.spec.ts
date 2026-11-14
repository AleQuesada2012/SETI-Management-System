import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { settle, type } from '../support/qa';
import { isoDate } from '../support/dates';
import { cancelReservation, createReservation, freeSlot } from '../support/reservations';

// CP-83 · Generar estadísticas de reservas (RF_83, CU_RF_83).

const cell = (page: import('@playwright/test').Page, status: string) =>
    page.getByRole('table', { name: 'Reservas por estado' }).getByRole('row').filter({ has: page.getByRole('cell', { name: status, exact: true }) }).getByRole('cell').nth(1);

test.describe('CP-83 · Generar estadísticas de reservas', () => {
    test('a. total del período, reservas por estado y por espacio', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/reportes/estadisticas/reservas');
        await expect(page.getByText(/^Reservas en el período: \d+$/)).toBeVisible();
        for (const status of ['Próxima', 'En curso', 'Finalizada', 'Cancelada']) {
            await expect(cell(page, status)).toHaveText(/^\d+$/);
        }
        await expect(page.getByRole('table', { name: 'Reservas por espacio' })).toBeVisible();
    });

    test('b. con Desde y Hasta de la semana actual solo cuenta las reservas que empiezan en ella', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/reportes/estadisticas/reservas');
        const all = Number((await page.getByText(/^Reservas en el período: \d+$/).innerText()).match(/\d+/)![0]);
        const now = new Date();
        const monday = -((now.getDay() + 6) % 7);
        await type(page, 'Desde', isoDate(monday));
        await type(page, 'Hasta', isoDate(monday + 6));
        const week = Number((await page.getByText(/^Reservas en el período: \d+$/).innerText()).match(/\d+/)![0]);
        test.info().annotations.push({ type: 'cantidades', description: `todas ${all}, semana ${isoDate(monday)}–${isoDate(monday + 6)}: ${week}` });
        expect(week).toBeLessThanOrEqual(all);
        expect(week).toBeGreaterThan(0);
    });

    test('c. una reserva cancelada en otra pestaña pasa a Cancelada sin recargar', async ({ browser }) => {
        const owner = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const path = await createReservation(owner, freeSlot(), 'PRUEBA QA · CP-83c');

        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/reportes/estadisticas/reservas');
        const before = Number(await cell(page, 'Cancelada').innerText());
        let navigations = 0;
        page.on('framenavigated', (frame) => frame === page.mainFrame() && navigations++);

        await cancelReservation(owner, path, 'Prueba de estadísticas (QA).');
        await expect(cell(page, 'Cancelada')).toHaveText(String(before + 1), { timeout: 20_000 });
        expect(navigations).toBe(0);
    });
});
