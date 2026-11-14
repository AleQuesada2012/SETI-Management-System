import { expect, test } from '@playwright/test';
import { storageState, USERS } from '../support/accounts';
import { createReservation, freeSlot, LAB_110 } from '../support/reservations';

// CP-51 · Examinar reserva (RF_51, CU_RF_51).

test.describe('CP-51 · Examinar reserva', () => {
    test.describe('como Reservante', () => {
        test.use({ storageState: storageState('reservante') });

        test('a. abre una reserva desde el calendario y ve todo su detalle', async ({ page }) => {
            const slot = freeSlot();
            await createReservation(page, slot, 'PRUEBA E2E · CP-51a');

            await page.goto(`/reservas?mes=${slot.date.slice(0, 7)}`);
            await page.getByTestId(`day-${slot.date}`).click();
            await page.getByTestId('day-detail').getByRole('link', { name: 'PRUEBA E2E · CP-51a' }).click();

            await expect(page).toHaveURL(/\/reservas\/\d+$/);
            const details = page.locator('dl');
            for (const term of ['Espacio', 'Fecha', 'Horario', 'Motivo', 'Cantidad de personas', 'Responsable', 'Estado actual']) {
                await expect(details.getByRole('term').filter({ hasText: new RegExp(`^${term}$`) })).toBeVisible();
            }
            await expect(details.getByText(LAB_110.label)).toBeVisible();
            await expect(details.getByText(slot.shownDate, { exact: true })).toBeVisible();
            await expect(details.getByText(`${slot.start}–${slot.end}`, { exact: true })).toBeVisible();
            await expect(details.getByText('5', { exact: true })).toBeVisible();
            await expect(details.getByText('Próxima')).toBeVisible();
        });

        test('b. abre una reserva desde el historial y tiene a mano Modificar y Eliminar', async ({ page }) => {
            const slot = freeSlot();
            await createReservation(page, slot, 'PRUEBA E2E · CP-51b');

            await page.goto('/reservas/historial');
            await page.getByTestId('filter-from').fill(slot.date);
            await page.getByTestId('filter-to').fill(slot.date);
            await page.getByRole('link', { name: 'PRUEBA E2E · CP-51b' }).click();

            await expect(page.getByRole('heading', { name: 'PRUEBA E2E · CP-51b' })).toBeVisible();
            await expect(page.getByTestId('reservation-update')).toBeVisible();
            await expect(page.getByTestId('reservation-cancel')).toBeVisible();
        });

        test('c. una reserva que no existe muestra "Página no encontrada"', async ({ page }) => {
            const response = await page.goto('/reservas/999999');

            expect(response?.status()).toBe(404);
            await expect(page.getByText('Página no encontrada')).toBeVisible();
        });
    });

    test('d. un Visualizador ve el detalle pero no las opciones de modificar o eliminar', async ({ browser }) => {
        const owner = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const path = await createReservation(owner, freeSlot(), 'PRUEBA E2E · CP-51d');

        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(path);

        await expect(page.locator('dl').getByText(USERS.reservante.name).first()).toBeVisible();
        await expect(page.getByTestId('reservation-update')).toHaveCount(0);
        await expect(page.getByTestId('reservation-cancel')).toHaveCount(0);
    });
});
