import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { exportReport, filterDates, openReport, previewRows } from '../support/reports';

// CP-65 · Generar reporte de reservaciones (RF_65, CU_RF_65). A week ago the demo data has one reservation:
// "Práctica de redes" in LAB-110; "Revisión de proyecto" (LAB-322) is cancelled.

test.describe('CP-65 · Generar reporte de reservaciones', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. muestra espacio, fecha, horario, motivo, personas, responsable y estado', async ({ page }) => {
        await openReport(page, 'Espacios', 'reservations');

        for (const column of ['Espacio', 'Fecha', 'Horario', 'Motivo', 'Cantidad de personas', 'Responsable', 'Estado']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        await expect(page.getByText('ninguno (contiene la totalidad de los registros)')).toBeVisible();
    });

    test('b. filtra por rango de fechas', async ({ page }) => {
        await openReport(page, 'Espacios', 'reservations');
        await filterDates(page, isoDate(-7), isoDate(-7));

        await expect(previewRows(page)).toHaveCount(1);
        const row = previewRows(page).first();
        await expect(row).toContainText('LAB-110 · Laboratorio 110');
        await expect(row).toContainText(shownDate(isoDate(-7)));
        await expect(row).toContainText('Práctica de redes');
        await expect(row).toContainText('Finalizada');
    });

    test('c. filtra por estado e incluye las reservas canceladas', async ({ page }) => {
        await openReport(page, 'Espacios', 'reservations');
        await page.getByText('Estado de la reserva', { exact: true }).click();
        await page.getByRole('checkbox', { name: 'Cancelada', exact: true }).check();

        await expect(page.getByText('Estado de la reserva: Cancelada')).toBeVisible();
        await expect(previewRows(page).filter({ hasText: 'Revisión de proyecto' })).toBeVisible();
        for (const row of await previewRows(page).all()) {
            await expect(row).toContainText('Cancelada');
        }
    });

    test('d. exporta el reporte filtrado en PDF', async ({ page }) => {
        await openReport(page, 'Espacios', 'reservations');
        await filterDates(page, isoDate(-7), isoDate(1));

        const download = await exportReport(page, 'PDF');

        expect(download.suggestedFilename()).toMatch(/\.pdf$/);
        await expect(page.getByText('El reporte se generó correctamente.')).toBeVisible();
    });

    test('e. un rol sin permiso de reportes recibe "Acceso denegado"', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/reportes/reservations');

        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado')).toBeVisible();
    });
});
