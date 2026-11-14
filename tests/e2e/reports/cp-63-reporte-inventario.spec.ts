import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { pick, previewTotal } from '../support/qa';
import { openReport } from '../support/reports';

// CP-63 · Generar reporte de inventario de activos (RF_63, CU_RF_63).

test.describe('CP-63 · Generar reporte de inventario de activos', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. la vista previa muestra los datos de cada activo', async ({ page }) => {
        await openReport(page, 'Activos', 'assets_inventory');
        for (const column of ['Código de activo', 'Placa', 'Nombre', 'Marca', 'Modelo', 'Condición física', 'Estado del ciclo de vida', 'Espacio', 'Responsable del activo', 'Fecha de adquisición']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
    });

    test('b. filtrado por LAB-110, la cantidad coincide con la página del espacio', async ({ page }) => {
        await openReport(page, 'Activos', 'assets_inventory');
        await pick(page, 'Espacio', 'LAB-110 · Laboratorio 110');
        const inReport = await previewTotal(page);

        await page.goto('/espacios/1');
        const heading = await page.getByText(/Activos en el espacio \(\d+\)/).innerText();
        const inSpace = Number(heading.match(/\((\d+)\)/)![1]);
        test.info().annotations.push({ type: 'cantidades', description: `reporte ${inReport}, página del espacio ${inSpace}` });
        expect(inReport).toBe(inSpace);
    });

    test('c. la vista previa se consulta antes de exportar y el menú ofrece PDF y Excel (XLSX)', async ({ page }) => {
        await openReport(page, 'Activos', 'assets_inventory');
        await expect(page.getByText(/Mostrando 1 a \d+ de \d+ resultados/)).toBeVisible();
        await page.getByTestId('export-button').click();
        await expect(page.getByRole('menuitem', { name: 'Exportar PDF' })).toBeVisible();
        await expect(page.getByRole('menuitem', { name: 'Exportar Excel (XLSX)' })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('menuitem', { name: 'Exportar PDF' })).toBeHidden();
    });
});
