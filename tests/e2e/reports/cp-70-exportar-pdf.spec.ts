import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { exportAndRead, pick, previewColumn, QA_USERS, signIn } from '../support/qa';
import { openReport } from '../support/reports';

// CP-70 · Exportar reportes en formato PDF (RF_70, CU_RF_70). The preview shows 50 rows a page; no space of the demo
// data (nor of the site's sample data) has more than 50 assets, so a) checks that every asset of the filter is in the
// PDF, and the export of the whole inventory (46 assets, one page) as the closest case.

test.describe('CP-70 · Exportar reportes en formato PDF', () => {
    test('a y b. el PDF trae todos los activos del filtro, la fecha y hora, el generador y los filtros', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Activos', 'assets_inventory');
        await pick(page, 'Espacio', 'LAB-110 · Laboratorio 110');
        const codes = await previewColumn(page, 'Código de activo');

        const { download, text } = await exportAndRead(page, 'PDF');
        expect(download.suggestedFilename()).toMatch(/\.pdf$/);
        for (const code of codes) {
            expect(text, `${code} in the PDF`).toContain(code);
        }
        expect(text).toMatch(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
        expect(text).toContain('Andrés Mora');
        expect(text).toContain('LAB-110 · Laboratorio 110');
        test.info().annotations.push({ type: 'encabezado del PDF', description: text.split('\n').slice(0, 14).join('\n') });
    });

    test('c. un rol que consulta pero no exporta no ve "Exportar"', async ({ browser }) => {
        const page = await signIn(browser, QA_USERS.consulta);
        await openReport(page, 'Activos', 'assets_inventory');
        await expect(page.getByRole('columnheader', { name: 'Código de activo' })).toBeVisible();
        await expect(page.getByTestId('export-button')).toHaveCount(0);
    });
});
