import { expect, test } from '@playwright/test';
import { storageState, USERS } from '../support/accounts';
import { createReservation, fillReservationForm, freeSlot, LAB_110 } from '../support/reservations';

// CP-46 · Reservar espacio (RF_46, CU_RF_46).

test.describe('CP-46 · Reservar espacio', () => {
    test.describe('como Reservante', () => {
        test.use({ storageState: storageState('reservante') });

        test('a. reserva un espacio disponible con sus datos', async ({ page }) => {
            const slot = freeSlot();
            await createReservation(page, slot, 'PRUEBA E2E · CP-46a');

            await expect(page.getByText(`Se reservó ${LAB_110.label.split(' · ')[1]}.`)).toBeVisible();
            await expect(page.getByRole('heading', { name: 'PRUEBA E2E · CP-46a' })).toBeVisible();
            const details = page.locator('dl');
            await expect(details.getByText(LAB_110.label)).toBeVisible();
            await expect(details.getByText(slot.shownDate, { exact: true })).toBeVisible();
            await expect(details.getByText(`${slot.start}–${slot.end}`, { exact: true })).toBeVisible();
            await expect(details.getByText(USERS.reservante.name).first()).toBeVisible();
        });

        test('b. no permite un horario que choca con otra reserva del mismo espacio', async ({ page }) => {
            const slot = freeSlot();
            await createReservation(page, slot, 'PRUEBA E2E · CP-46b primera');

            await fillReservationForm(page, slot, { title: 'PRUEBA E2E · CP-46b choque' });
            await page.getByTestId('reservation-submit').click();

            await expect(page.getByText('El espacio ya está reservado en ese horario.')).toBeVisible();
            await expect(page).toHaveURL(/\/reservas\/nueva/);
        });

        test('c. no permite más personas que la capacidad del espacio', async ({ page }) => {
            const capacity = await fillReservationForm(page, freeSlot(), { title: 'PRUEBA E2E · CP-46c' });

            await page.getByLabel('Cantidad de personas').fill(String(capacity + 1));
            await page.getByTestId('reservation-submit').click();

            await expect(
                page.getByText(`La cantidad de personas supera la capacidad del espacio (${capacity}).`),
            ).toBeVisible();
            await expect(page).toHaveURL(/\/reservas\/nueva/);
        });

        test('d. no ofrece espacios que no se pueden reservar', async ({ page }) => {
            await page.goto('/reservas/nueva');

            // LAB-316 is "En mantenimiento" in the demo data.
            await expect(page.getByTestId('reservation-space').locator('option', { hasText: 'LAB-316' })).toHaveCount(0);
        });
    });

    test.describe('como Visualizador', () => {
        test.use({ storageState: storageState('visualizador') });

        test('e. un rol sin permiso no ve la opción ni abre la página', async ({ page }) => {
            await page.goto('/reservas');
            await expect(page.getByRole('link', { name: 'Historial' })).toBeVisible();
            await expect(page.getByRole('link', { name: 'Reservar espacio' })).toHaveCount(0);

            const response = await page.goto('/reservas/nueva');
            expect(response?.status()).toBe(403);
            await expect(page.getByText('Acceso denegado')).toBeVisible();
        });
    });
});
