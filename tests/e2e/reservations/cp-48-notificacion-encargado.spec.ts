import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { createReservation, freeSlot, openNotifications, uniqueTitle, waitForNotification } from '../support/reservations';

// CP-48 · Notificación de reserva de espacio a Usuario Administrador asignado (RF_48, CU_RF_48).
// Mario Solís (administrador) manages LAB-110; Lucía Campos (administrador2) does not.

test.describe('CP-48 · Notificación de reserva de espacio a Usuario Administrador asignado', () => {
    test('a. el encargado del espacio recibe el aviso con el espacio y la fecha', async ({ browser }) => {
        const title = uniqueTitle('CP-48a');
        const slot = freeSlot();
        const reservante = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        await createReservation(reservante, slot, title);

        const page = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        const entry = await waitForNotification(page, `«${title}»`);

        await expect(entry).toContainText('Nueva reserva en');
        await expect(entry).toContainText('Laboratorio 110');
        await expect(entry).toContainText(`${slot.shownDate}, de ${slot.start} a ${slot.end}`);
    });

    test('b. un Administrador que no es encargado del espacio no recibe el aviso', async ({ browser }) => {
        const title = uniqueTitle('CP-48b');
        const reservante = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        await createReservation(reservante, freeSlot(), title);

        // Once the space manager has it, the queue has delivered this booking's notifications.
        const manager = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await waitForNotification(manager, `«${title}»`);

        const page = await (await browser.newContext({ storageState: storageState('administrador2') })).newPage();
        const bell = await openNotifications(page);

        await expect(bell.getByText(`«${title}»`)).toHaveCount(0);
    });

    test('c. el encargado recibe el aviso cuando la reserva cambia de horario', async ({ browser }) => {
        const title = uniqueTitle('CP-48c');
        const reservante = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        await createReservation(reservante, freeSlot(), title);
        const target = freeSlot();

        await reservante.getByTestId('reservation-update').click();
        await reservante.getByLabel('Fecha').fill(target.date);
        await reservante.getByTestId('reservation-start').selectOption(target.start);
        await reservante.getByTestId('reservation-end').selectOption(target.end);
        await reservante.getByTestId('reservation-save').click();
        await expect(reservante).toHaveURL(/\/reservas\/\d+$/);

        const page = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        const entry = await waitForNotification(page, 'Reserva modificada en', `«${title}»`);

        await expect(entry).toContainText(`${target.shownDate}, de ${target.start} a ${target.end}`);
    });
});
