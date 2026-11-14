import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { cancelReservation, createReservation, freeSlot } from '../support/reservations';

// CP-52 · Historial de reservas (RF_52, CU_RF_52). Daniela Quirós (profesora) has five reservations in the demo data:
// "Práctica de redes" a week ago, "Torneo de programación" today and "Curso de bases de datos" in 7, 14 and 21 days.

test.describe('CP-52 · Historial de reservas', () => {
    test.use({ storageState: storageState('profesora') });

    test('a. lista las reservas con espacio, fecha, horario, estado y motivo', async ({ page }) => {
        await page.goto('/reservas/historial');

        for (const column of ['Fecha', 'Horario', 'Espacio', 'Motivo', 'Responsable', 'Estado']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        const past = page.getByRole('row').filter({ hasText: 'Práctica de redes' });
        await expect(past).toContainText(shownDate(isoDate(-7)));
        await expect(past).toContainText('08:00–10:00');
        await expect(past).toContainText('LAB-110 · Laboratorio 110');
        await expect(past).toContainText('Finalizada');
    });

    test('b. ordena de la más reciente a la más antigua y al revés', async ({ page }) => {
        await page.goto('/reservas/historial');
        const firstRow = page.getByRole('row').nth(1);

        await expect(firstRow).toContainText(shownDate(isoDate(21)));

        await page.getByTestId('order').selectOption({ label: 'Más antiguas primero' });
        await expect(firstRow).toContainText(shownDate(isoDate(-7)));
    });

    test('c. filtra por estado e incluye las reservas canceladas', async ({ page }) => {
        const path = await createReservation(page, freeSlot(), 'PRUEBA E2E · CP-52c');
        await cancelReservation(page, path);

        await page.goto('/reservas/historial');
        await page.getByTestId('filter-status').getByRole('checkbox', { name: 'Cancelada' }).check();

        await expect(page.getByText('1 reserva', { exact: true })).toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'PRUEBA E2E · CP-52c' })).toContainText('Cancelada');
    });

    test('d. filtra por rango de fechas y "Limpiar filtros" restaura la lista', async ({ page }) => {
        await page.goto('/reservas/historial');
        const total = await page.getByText(/^\d+ reservas$/).textContent();

        await page.getByTestId('filter-from').fill(isoDate(6));
        await page.getByTestId('filter-to').fill(isoDate(22));

        await expect(page.getByText('3 reservas', { exact: true })).toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'Curso de bases de datos' })).toHaveCount(3);

        await page.getByRole('button', { name: 'Limpiar filtros' }).click();
        await expect(page.getByText(total!, { exact: true })).toBeVisible();
    });

    test('e. avisa cuando la fecha final es anterior a la inicial', async ({ page }) => {
        await page.goto('/reservas/historial');

        await page.getByTestId('filter-from').fill(isoDate(10));
        await page.getByTestId('filter-to').fill(isoDate(1));

        await expect(page.getByTestId('filters-invalid')).toContainText('Los filtros no son válidos');
        await expect(page.getByTestId('filters-invalid')).toContainText('La fecha final no puede ser anterior a la fecha inicial.');
    });
});
