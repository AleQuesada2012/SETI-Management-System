import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { exportAndRead, isoDay, pick, dates } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-00-4 · Auditar generaciones (RF_00_4, CU_RF_00_4).

test.describe('CP-00-4 · Auditar generaciones', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. cada exportación (PDF y XLSX) queda en la auditoría y en Reportes generados', async ({ page, browser }) => {
        await openReport(page, 'Activos', 'assets_inventory');
        const pdf = await exportAndRead(page, 'PDF');
        expect(pdf.download.suggestedFilename()).toMatch(/\.pdf$/);
        const xlsx = await exportAndRead(page, 'Excel (XLSX)');
        expect(xlsx.download.suggestedFilename()).toMatch(/\.xlsx$/);

        await page.goto('/reportes/audit');
        await pick(page, 'Tipo de operación', 'Generó');
        await dates(page, isoDay(), isoDay());
        const generated = previewRows(page).filter({ hasText: 'Inventario de activos' });
        await expect(generated.filter({ hasText: 'PDF' }).first()).toBeVisible();
        await expect(generated.filter({ hasText: 'XLSX' }).first()).toBeVisible();
        await expect(generated.first()).toContainText('Andrés Mora');

        // Reportes generados needs reports.history: the Reportes role does not have it (403), the Superusuario does.
        const history = await page.goto('/reportes/generados');
        test.info().annotations.push({ type: 'Reportes generados con rol Reportes', description: `HTTP ${history?.status()}` });
        const superuser = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await superuser.goto('/reportes/generados');
        const rows = previewRows(superuser).filter({ hasText: 'Andrés Mora' }).filter({ hasText: 'Inventario de activos' });
        await expect(rows.filter({ hasText: 'PDF' }).first()).toBeVisible();
        await expect(rows.filter({ hasText: 'XLSX' }).first()).toBeVisible();
    });
});
