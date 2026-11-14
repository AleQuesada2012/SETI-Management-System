import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { exportAndRead, isoDay, pick, previewColumn, unpick } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-75 · Generar reporte de garantías (RF_75, CU_RF_75). qa-data.php: A-0004's warranty ended 10 days ago and A-0005's
// ends today. Chairs, tables and the safe have no warranty.

test.describe('CP-75 · Generar reporte de garantías', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. muestra activo, proveedor, inicio, fin y vigencia; los activos sin garantía no aparecen', async ({ page }) => {
        await openReport(page, 'Activos', 'warranties');
        for (const column of ['Código de activo', 'Nombre', 'Proveedor', 'Inicio de la garantía', 'Fin de la garantía', 'Vigencia de la garantía']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        for (const without of ['Silla', 'Mesa', 'Caja fuerte']) {
            await expect(previewRows(page).filter({ hasText: without })).toHaveCount(0);
        }
    });

    test('b. Vigente y Vencida; la que termina hoy cuenta como vigente', async ({ page }) => {
        await openReport(page, 'Activos', 'warranties');
        await pick(page, 'Vigencia de la garantía', 'Vigente');
        expect(new Set(await previewColumn(page, 'Vigencia de la garantía'))).toEqual(new Set(['Vigente']));
        expect(await previewColumn(page, 'Código de activo')).toContain('A-0005');
        await unpick(page, 'Vigencia de la garantía', 'Vigente');
        await pick(page, 'Vigencia de la garantía', 'Vencida');
        expect(new Set(await previewColumn(page, 'Vigencia de la garantía'))).toEqual(new Set(['Vencida']));
        const expired = await previewColumn(page, 'Código de activo');
        expect(expired).toContain('A-0004');
        expect(expired).not.toContain('A-0005');
        // Every "Fin de la garantía" of the expired ones is before today.
        const today = new Date(`${isoDay()}T00:00:00`);
        for (const end of await previewColumn(page, 'Fin de la garantía')) {
            const [d, m, y] = end.split('/').map(Number);
            expect(new Date(y, m - 1, d).getTime()).toBeLessThan(today.getTime());
        }
    });

    test('c. PDF y XLSX con Vencida traen solo las vencidas y el filtro', async ({ page }) => {
        await openReport(page, 'Activos', 'warranties');
        await pick(page, 'Vigencia de la garantía', 'Vencida');
        const expired = await previewColumn(page, 'Código de activo');
        for (const format of ['PDF', 'Excel (XLSX)'] as const) {
            const { text } = await exportAndRead(page, format);
            for (const code of expired) {
                expect(text).toContain(code);
            }
            expect(text).not.toContain('A-0005');
            expect(text).not.toMatch(/\bVigente\b/);
            test.info().annotations.push({ type: `filtros en ${format}`, description: text.split('\n').filter((l) => /Vencida|Filtros|Vigencia/.test(l)).slice(0, 6).join(' / ') });
            expect(text).toMatch(/Vigencia de la garantía[:\s]+Vencida/);
        }
    });
});
