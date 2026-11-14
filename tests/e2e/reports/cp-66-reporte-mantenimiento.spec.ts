import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { dates, isoDay, pick, previewColumn, unpick } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-66 · Generar reporte de mantenimiento y reparaciones (RF_66, CU_RF_66). qa-data.php finishes two preventive
// maintenances and one repair; the demo data also has a scheduled maintenance (A-0017) and a repair in progress.

test.describe('CP-66 · Generar reporte de mantenimiento y reparaciones', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. lista solo las intervenciones realizadas con sus datos', async ({ page }) => {
        await openReport(page, 'Activos', 'maintenance');
        for (const column of ['Código de activo', 'Activo', 'Fecha de realización', 'Tipo de intervención', 'Descripción', 'Realizado por', 'Registrado por']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        await expect(previewRows(page)).toHaveCount(3);
        await expect(previewRows(page).filter({ hasText: 'Limpieza de filtros' })).toHaveCount(0); // scheduled (A-0017)
        test.info().annotations.push({ type: 'filas', description: (await previewRows(page).allInnerTexts()).join('\n') });
    });

    test('b. diferencia reparaciones y mantenimientos', async ({ page }) => {
        await openReport(page, 'Activos', 'maintenance');
        await pick(page, 'Tipo de intervención', 'Reparación');
        expect(new Set(await previewColumn(page, 'Tipo de intervención'))).toEqual(new Set(['Reparación']));
        await unpick(page, 'Tipo de intervención', 'Reparación');
        await pick(page, 'Tipo de intervención', 'Mantenimiento');
        const kinds = await previewColumn(page, 'Tipo de intervención');
        expect(kinds.length).toBe(2);
        expect(new Set(kinds).has('Reparación')).toBe(false);
    });

    test('c. filtra por activo y rango de fechas (ambas incluidas)', async ({ page }) => {
        await openReport(page, 'Activos', 'maintenance');
        await pick(page, 'Activo', 'A-0002 · Computadora de escritorio Dell OptiPlex 7010');
        expect(await previewRows(page).count()).toBe(2);
        await dates(page, isoDay(-1), isoDay(-1)); // the repair, done yesterday
        await expect(previewRows(page)).toHaveCount(1);
        expect(await previewColumn(page, 'Código de activo')).toEqual(['A-0002']);
    });
});
