import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { pick, previewColumn, settle, type } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-64 · Generar reporte de espacios (RF_64, CU_RF_64). Local equivalent of LAB-305: LAB-316 (Edificio B, en
// mantenimiento). Mario Solís manages LAB-110.

test.describe('CP-64 · Generar reporte de espacios', () => {
    test('a. muestra nombre, ubicación, capacidad, tipo, estado operativo y encargados', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Espacios', 'spaces');
        for (const column of ['Nombre', 'Ubicación', 'Capacidad', 'Tipo', 'Estado operativo', 'Encargados del espacio']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
    });

    test('b. filtra por tipo, estado operativo y edificio', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Espacios', 'spaces');
        await pick(page, 'Tipo de espacio', 'Laboratorio');
        await pick(page, 'Estado operativo', 'En mantenimiento');
        await pick(page, 'Edificio', 'Edificio B');
        await expect(previewRows(page)).toHaveCount(1);
        await expect(previewRows(page).first()).toContainText('Laboratorio 316');
    });

    test('c. el reporte muestra la capacidad nueva después de editar el espacio', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await admin.goto('/espacios/1');
        await admin.getByRole('button', { name: 'Editar' }).click();
        const dialog = admin.getByRole('dialog');
        await dialog.getByLabel('Capacidad').fill('33');
        await dialog.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(admin.getByText('33 personas')).toBeVisible();

        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Espacios', 'spaces');
        await type(page, 'Buscar', 'Laboratorio 110');
        expect(await previewColumn(page, 'Capacidad')).toEqual(['33']);
    });
});
