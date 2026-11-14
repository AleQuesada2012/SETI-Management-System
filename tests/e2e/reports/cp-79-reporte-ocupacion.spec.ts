import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate } from '../support/dates';
import { exportReport, filterDates, openReport, previewRows } from '../support/reports';

// CP-79 · Generar reporte de ocupación de espacios (RF_79, CU_RF_79). Between a week ago and tomorrow the demo data
// has two reservations in LAB-110 and one each in LAIMI and LAB-312.

test.describe('CP-79 · Generar reporte de ocupación de espacios', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. muestra la cantidad de reservas de cada espacio en el rango de fechas', async ({ page }) => {
        await openReport(page, 'Espacios', 'occupancy');
        for (const column of ['Espacio', 'Reservas', 'Tiempo reservado']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }

        await filterDates(page, isoDate(-7), isoDate(1));

        await expect(previewRows(page)).toHaveCount(3);
        const lab110 = previewRows(page).filter({ hasText: 'LAB-110 · Laboratorio 110' });
        await expect(lab110.getByRole('cell').nth(1)).toHaveText('2');
        await expect(lab110.getByRole('cell').nth(2)).toHaveText('3 h 30 min');
    });

    test('b. identifica el espacio con más y con menos reservas', async ({ page }) => {
        await openReport(page, 'Espacios', 'occupancy');
        await filterDates(page, isoDate(-7), isoDate(1));

        await expect(previewRows(page).first()).toContainText('LAB-110');
        await expect(previewRows(page).first().getByRole('cell').nth(1)).toHaveText('2');
        await expect(previewRows(page).last().getByRole('cell').nth(1)).toHaveText('1');
    });

    test('c. un período sin reservas lo indica', async ({ page }) => {
        await openReport(page, 'Espacios', 'occupancy');
        await filterDates(page, isoDate(400), isoDate(410));

        await expect(previewRows(page)).toHaveCount(0);
    });

    test('d. exporta el reporte en Excel (XLSX)', async ({ page }) => {
        await openReport(page, 'Espacios', 'occupancy');
        await filterDates(page, isoDate(-7), isoDate(1));

        const download = await exportReport(page, 'Excel (XLSX)');

        expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
        await expect(page.getByText('El reporte se generó correctamente.')).toBeVisible();
    });

    test('e. un rol sin permiso de reportes recibe "Acceso denegado"', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const response = await page.goto('/reportes/occupancy');

        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado')).toBeVisible();
    });
});
