import { expect, test } from '@playwright/test';
import { storageState, USERS } from '../support/accounts';
import { cancelReservation, createReservation, freeSlot, openNotifications, uniqueTitle, waitForNotification } from '../support/reservations';

// CP-47 · Notificación de reserva de espacio a Superusuario (RF_47, CU_RF_47). The message arrives in the bell.

test.describe('CP-47 · Notificación de reserva de espacio a Superusuario', () => {
    test('a. el Superusuario recibe el aviso con el espacio y la fecha reservados', async ({ browser }) => {
        const title = uniqueTitle('CP-47a');
        const slot = freeSlot();
        const reservante = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        await createReservation(reservante, slot, title);

        const page = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        const entry = await waitForNotification(page, `«${title}»`);

        await expect(entry).toContainText('Nueva reserva en');
        await expect(entry).toContainText('Laboratorio 110');
        await expect(entry).toContainText(`${USERS.reservante.name} reservó el espacio`);
        await expect(entry).toContainText(`${slot.shownDate}, de ${slot.start} a ${slot.end}`);
    });

    test('b. el Superusuario también recibe el aviso cuando la reserva se elimina', async ({ browser }) => {
        const title = uniqueTitle('CP-47b');
        const reservante = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const path = await createReservation(reservante, freeSlot(), title);
        await cancelReservation(reservante, path);

        const page = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await waitForNotification(page, 'Reserva cancelada en', `«${title}»`);
    });

    test('c. quien reserva no recibe el aviso de su propia reserva', async ({ browser }) => {
        const title = uniqueTitle('CP-47c');
        const page = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        await createReservation(page, freeSlot(), title);

        // Once the Superusuario has it, the queue has delivered this booking's notifications.
        const superusuario = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await waitForNotification(superusuario, `«${title}»`);

        const bell = await openNotifications(page);
        await expect(bell.getByText(`«${title}»`)).toHaveCount(0);
    });
});
