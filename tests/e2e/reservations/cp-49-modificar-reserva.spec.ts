import { expect, test } from '@playwright/test';
import { storageState, USERS } from '../support/accounts';
import { createReservation, freeSlot, LAB_312, selectSpace } from '../support/reservations';

// CP-49 · Modificar reserva (RF_49, CU_RF_49).

test.describe('CP-49 · Modificar reserva', () => {
    test.describe('como Reservante', () => {
        test.use({ storageState: storageState('reservante') });

        test('a. cambia la fecha y el horario de una reserva propia y guarda el motivo', async ({ page }) => {
            const detail = await createReservation(page, freeSlot(), 'PRUEBA E2E · CP-49a');
            const target = freeSlot();

            await page.getByTestId('reservation-update').click();
            await expect(page).toHaveURL(/\/editar$/);
            await page.getByLabel('Fecha').fill(target.date);
            await page.getByTestId('reservation-start').selectOption(target.start);
            await page.getByTestId('reservation-end').selectOption(target.end);
            await page.getByTestId('modification-reason').fill('Se movió la tutoría (prueba E2E).');
            await page.getByTestId('reservation-save').click();

            await expect(page).toHaveURL(detail);
            const details = page.locator('dl');
            await expect(details.getByText(target.shownDate, { exact: true })).toBeVisible();
            await expect(details.getByText(`${target.start}–${target.end}`, { exact: true })).toBeVisible();
            await expect(page.getByText('Modificada').first()).toBeVisible();
            await expect(page.getByTestId('modification-reason')).toHaveText('Se movió la tutoría (prueba E2E).');
        });

        test('b. cambia el espacio y la persona responsable', async ({ page }) => {
            const detail = await createReservation(page, freeSlot(), 'PRUEBA E2E · CP-49b');

            await page.getByTestId('reservation-update').click();
            await selectSpace(page.getByTestId('reservation-space'), LAB_312.code);
            await page.getByLabel('Responsable').selectOption({ label: USERS.visualizador.name });
            await page.getByTestId('reservation-save').click();

            await expect(page).toHaveURL(detail);
            const details = page.locator('dl');
            await expect(details.getByText(LAB_312.label)).toBeVisible();
            await expect(details.getByText(USERS.visualizador.name, { exact: true })).toBeVisible();
        });

        test('c. avisa cuando se guarda sin cambios', async ({ page }) => {
            await createReservation(page, freeSlot(), 'PRUEBA E2E · CP-49c');

            await page.getByTestId('reservation-update').click();
            await page.getByTestId('reservation-save').click();

            await expect(page.getByText('No hiciste ningún cambio en la reserva.')).toBeVisible();
            await expect(page).toHaveURL(/\/editar$/);
        });
    });

    test('d. un rol sin permiso no ve "Modificar" ni abre la edición', async ({ browser }) => {
        const owner = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const path = await createReservation(owner, freeSlot(), 'PRUEBA E2E · CP-49d');

        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(path);
        await expect(page.getByRole('heading', { name: 'PRUEBA E2E · CP-49d' })).toBeVisible();
        await expect(page.getByTestId('reservation-update')).toHaveCount(0);

        const response = await page.goto(`${path}/editar`);
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado')).toBeVisible();
    });
});
