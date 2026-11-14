import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { cancelReservation, createReservation, freeSlot, LAB_110 } from '../support/reservations';

// CP-50 · Eliminar reserva (RF_50, CU_RF_50). The reservation is cancelled and stays in the history.

test.describe('CP-50 · Eliminar reserva', () => {
    test.describe('como Reservante', () => {
        test.use({ storageState: storageState('reservante') });

        test('a. elimina una reserva propia y el horario queda libre', async ({ page }) => {
            const slot = freeSlot();
            const path = await createReservation(page, slot, 'PRUEBA E2E · CP-50a');

            await cancelReservation(page, path, 'Se suspendió la tutoría (prueba E2E).');

            await page.goto(path);
            await expect(page.getByTestId('reservation-cancelled')).toBeVisible();
            await expect(page.getByText('Motivo de la cancelación: Se suspendió la tutoría (prueba E2E).')).toBeVisible();

            // The freed slot can be booked again.
            const again = await createReservation(page, slot, 'PRUEBA E2E · CP-50a otra vez');
            expect(again).not.toBe(path);
        });

        test('b. una reserva eliminada ya no se puede modificar ni eliminar', async ({ page }) => {
            const path = await createReservation(page, freeSlot(), 'PRUEBA E2E · CP-50b');
            await cancelReservation(page, path);

            await page.goto(path);
            await expect(page.getByTestId('reservation-cancelled')).toBeVisible();
            await expect(page.getByTestId('reservation-update')).toHaveCount(0);
            await expect(page.getByTestId('reservation-cancel')).toHaveCount(0);
        });

        test('c. "Volver" cierra el diálogo sin eliminar la reserva', async ({ page }) => {
            await createReservation(page, freeSlot(), 'PRUEBA E2E · CP-50c');

            await page.getByTestId('reservation-cancel').click();
            await page.getByRole('button', { name: 'Volver' }).click();

            await expect(page.getByText('¿Eliminar esta reserva?')).toBeHidden();
            await expect(page.getByTestId('reservation-cancelled')).toHaveCount(0);
        });
    });

    test('d. el encargado del espacio elimina la reserva de otra persona', async ({ browser }) => {
        const owner = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const path = await createReservation(owner, freeSlot(), 'PRUEBA E2E · CP-50d', LAB_110.code);

        // Mario Solís (administrador) manages LAB-110.
        const manager = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await cancelReservation(manager, path);
    });

    test('e. un rol sin permiso no ve "Eliminar"', async ({ browser }) => {
        const owner = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const path = await createReservation(owner, freeSlot(), 'PRUEBA E2E · CP-50e');

        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(path);

        await expect(page.getByRole('heading', { name: 'PRUEBA E2E · CP-50e' })).toBeVisible();
        await expect(page.getByTestId('reservation-cancel')).toHaveCount(0);
    });
});
