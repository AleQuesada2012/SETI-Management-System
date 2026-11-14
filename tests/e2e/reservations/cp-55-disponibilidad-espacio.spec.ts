import { expect, type Page, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { createReservation, freeSlot, LAB_110, type Slot } from '../support/reservations';

// CP-55 · Consultar disponibilidad de un espacio (RF_55, CU_RF_55). The "Disponibilidad" card of a space's page.

async function checkAvailability(page: Page, spaceId: number, slot: Pick<Slot, 'date' | 'start' | 'end'>): Promise<void> {
    await page.goto(`/espacios/${spaceId}`);
    const form = page.locator('form').filter({ has: page.getByTestId('availability-submit') });

    await form.getByLabel('Fecha').fill(slot.date);
    await form.getByLabel('Hora de inicio').selectOption(slot.start);
    await form.getByLabel('Hora de fin').selectOption(slot.end);
    await page.getByTestId('availability-submit').click();
}

test.describe('CP-55 · Consultar disponibilidad de un espacio', () => {
    test.use({ storageState: storageState('reservante') });

    test('a. indica que el espacio está disponible y permite reservar ese horario', async ({ page }) => {
        const slot = freeSlot();
        await checkAvailability(page, LAB_110.id, slot);

        const free = page.getByTestId('availability-free');
        await expect(free).toContainText('Disponible');
        await expect(free).toContainText(`${slot.shownDate}, de ${slot.start} a ${slot.end}`);

        await free.getByRole('link', { name: 'Reservar este horario' }).click();
        await expect(page).toHaveURL(/\/reservas\/nueva\?/);
        await expect(page.getByLabel('Fecha')).toHaveValue(slot.date);
        await expect(page.getByTestId('reservation-start')).toHaveValue(slot.start);
        await expect(page.getByTestId('reservation-end')).toHaveValue(slot.end);
    });

    test('b. indica la reserva que impide usar el espacio en ese período', async ({ page }) => {
        const slot = freeSlot();
        await createReservation(page, slot, 'PRUEBA E2E · CP-55b');

        await checkAvailability(page, LAB_110.id, slot);

        const taken = page.getByTestId('availability-taken');
        await expect(taken).toContainText('No disponible');
        await expect(taken).toContainText('Estas reservas ocupan el espacio en ese horario:');
        await expect(taken).toContainText('PRUEBA E2E · CP-55b');
    });

    test('c. avisa cuando la hora de fin no es posterior a la de inicio', async ({ page }) => {
        const slot = freeSlot();
        await checkAvailability(page, LAB_110.id, { date: slot.date, start: '10:00', end: '09:00' });

        await expect(page.getByText('La hora de fin debe ser posterior a la hora de inicio.')).toBeVisible();
        await expect(page.getByTestId('availability-free')).toHaveCount(0);
    });

    test('d. un espacio que no se puede reservar lo indica', async ({ page }) => {
        // LAB-316 (id 3) is "En mantenimiento" in the demo data.
        await page.goto('/espacios/3');

        await expect(page.getByTestId('availability-not-reservable')).toContainText(
            'Este espacio no se puede reservar por su tipo o su estado actual.',
        );
    });
});
