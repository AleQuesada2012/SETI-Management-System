import { expect, type Page, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate } from '../support/dates';

// CP-57 · Consultar reservas de un espacio (RF_57, CU_RF_57). LAB-312 (id 2) has "Curso de bases de datos" in 7, 14
// and 21 days in the demo data.

async function consult(page: Page, from: string, to: string): Promise<void> {
    await page.goto('/espacios/2');
    await page.getByLabel('Desde').fill(from);
    await page.getByLabel('Hasta').fill(to);
    await page.getByRole('button', { name: 'Consultar', exact: true }).click();
}

test.describe('CP-57 · Consultar reservas de un espacio', () => {
    test.use({ storageState: storageState('reservante') });

    test('a. muestra las reservas del período con fecha, horario, responsable, personas y estado', async ({ page }) => {
        await consult(page, isoDate(6), isoDate(22));

        const reservations = page.getByRole('table').filter({ hasText: 'Curso de bases de datos' });
        for (const column of ['Fecha', 'Horario', 'Motivo', 'Responsable', 'Personas', 'Estado']) {
            await expect(reservations.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        const rows = reservations.getByRole('row').filter({ hasText: 'Curso de bases de datos' });
        await expect(rows).toHaveCount(3);
        await expect(rows.first()).toContainText('10:00–12:00');
        await expect(rows.first()).toContainText('Daniela Quirós');
        await expect(rows.first()).toContainText('20');
        await expect(rows.first()).toContainText('Próxima');
    });

    test('b. indica cuando el espacio no tiene reservas en el período', async ({ page }) => {
        await consult(page, isoDate(400), isoDate(410));

        await expect(page.getByText('El espacio no tiene reservas en ese período.')).toBeVisible();
    });

    test('c. avisa cuando la fecha final es anterior a la inicial', async ({ page }) => {
        await consult(page, isoDate(10), isoDate(1));

        await expect(page.getByText('La fecha final no puede ser anterior a la fecha inicial.')).toBeVisible();
    });
});
