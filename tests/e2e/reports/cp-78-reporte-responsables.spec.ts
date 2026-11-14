import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { livewire, pick, previewColumn } from '../support/qa';
import { openReport } from '../support/reports';

// CP-78 · Generar reporte de responsables de activos (RF_78, CU_RF_78).

test.describe('CP-78 · Generar reporte de responsables de activos', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. muestra el activo, el responsable, su correo y el espacio', async ({ page }) => {
        await openReport(page, 'Activos', 'custodians');
        for (const column of ['Código de activo', 'Placa', 'Nombre', 'Responsable del activo', 'Correo del responsable', 'Espacio']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
    });

    test('b. filtra por un responsable', async ({ page }) => {
        await openReport(page, 'Activos', 'custodians');
        await pick(page, 'Responsable del activo', 'Mario Solís');
        expect(new Set(await previewColumn(page, 'Responsable del activo'))).toEqual(new Set(['Mario Solís']));
        expect(new Set(await previewColumn(page, 'Correo del responsable'))).toEqual(new Set(['admin@seti.test']));
    });

    test('c. "Sin responsable" y después también un responsable', async ({ page }) => {
        await openReport(page, 'Activos', 'custodians');
        await pick(page, 'Responsable del activo');
        await livewire(page, () => page.getByRole('checkbox', { name: 'Sin responsable', exact: true }).check());
        const without = await previewColumn(page, 'Responsable del activo');
        expect(new Set(without)).toEqual(new Set(['Sin responsable']));
        await pick(page, 'Responsable del activo', 'Mario Solís');
        const both = new Set(await previewColumn(page, 'Responsable del activo'));
        expect(both).toEqual(new Set(['Sin responsable', 'Mario Solís']));
    });
});
