import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { exportAndRead, fileText, QA_USERS, signIn } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-84 · Consultar reportes previamente generados (RF_84, CU_RF_84). Reportes generados needs reports.history, which
// only the Superusuario has among the system roles.

test.describe('CP-84 · Consultar reportes previamente generados', () => {
    test('a y b. historial con lo más reciente primero; "Descargar original" trae el archivo del día', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await openReport(page, 'Activos', 'assets_inventory');
        const original = await exportAndRead(page, 'PDF');
        expect(original.text).toContain('A-0009');
        await openReport(page, 'Usuarios', 'users');
        await exportAndRead(page, 'Excel (XLSX)');

        await page.goto('/reportes');
        await page.getByRole('link', { name: 'Reportes generados' }).click();
        await expect(page).toHaveURL(/\/reportes\/generados$/);
        const rows = previewRows(page);
        await expect(rows.first()).toContainText('Usuarios');
        await expect(rows.first()).toContainText('XLSX');
        await expect(rows.nth(1)).toContainText('Inventario de activos');
        await expect(rows.nth(1)).toContainText('PDF');
        test.info().annotations.push({ type: 'columnas', description: (await page.getByRole('columnheader').allInnerTexts()).join(' | ') });
        test.info().annotations.push({ type: 'primeras filas', description: (await rows.allInnerTexts()).slice(0, 2).join('\n') });

        // b. Change an asset that is in the PDF, then download the original.
        await page.goto('/activos/9/editar');
        await page.getByLabel('Nombre', { exact: true }).fill('Nombre cambiado después del reporte');
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(page).toHaveURL(/\/activos\/9$/);

        await page.goto('/reportes/generados');
        const download = page.waitForEvent('download');
        await previewRows(page).nth(1).getByRole('link', { name: 'Descargar original' }).click();
        const text = await fileText(await download);
        expect(text).not.toContain('Nombre cambiado después del reporte');
        expect(text.replace(/\s+/g, ' ')).toBe(original.text.replace(/\s+/g, ' '));
    });

    test('c. quien exporta pero no consulta el historial no ve los reportes de los demás', async ({ browser }) => {
        const page = await signIn(browser, QA_USERS.exporta);
        await page.goto('/reportes');
        await expect(page.getByRole('link', { name: 'Reportes generados' })).toHaveCount(0);
        const response = await page.goto('/reportes/generados');
        expect(response?.status()).toBe(403);
        await expect(page.getByRole('heading', { name: 'Acceso denegado' })).toBeVisible();
    });
});
