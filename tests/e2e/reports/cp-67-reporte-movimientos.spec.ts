import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { dates, isoDay, livewire, pick, previewColumn } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-67 · Generar reporte de movimientos de inventario (RF_67, CU_RF_67). Mario Solís manages LAB-110 and LAB-312.

test.describe('CP-67 · Generar reporte de movimientos de inventario', () => {
    test('a. cada fila muestra activo, fecha y hora, tipo, origen, destino, usuario y motivo', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Activos', 'movements');
        for (const column of ['Código de activo', 'Activo', 'Fecha del movimiento', 'Tipo de movimiento', 'Espacio de origen', 'Espacio de destino', 'Realizado por', 'Motivo']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        const when = await previewColumn(page, 'Fecha del movimiento');
        expect(when[0]).toMatch(/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
    });

    test('b y c. el traslado aparece en el historial del activo y en el filtro de traslados de hoy', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await admin.goto('/activos/6/movimiento');
        await admin.getByLabel('Espacio destino').selectOption({ label: 'LAB-312 · Laboratorio 312' });
        await admin.getByLabel('Motivo').fill('Reubicación de prueba QA');
        await admin.getByRole('button', { name: 'Registrar movimiento' }).click();
        await expect(admin).toHaveURL(/\/activos\/6$/);

        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Activos', 'movements');
        await pick(page, 'Activo', 'A-0006 · Computadora de escritorio Dell OptiPlex 7010');
        const types = await previewColumn(page, 'Tipo de movimiento');
        test.info().annotations.push({ type: 'historial de A-0006', description: (await previewRows(page).allInnerTexts()).join('\n') });
        expect(types).toContain('Ingreso');
        expect(types).toContain('Traslado');
        const order = types.indexOf('Ingreso') < types.indexOf('Traslado') ? 'ascendente' : 'descendente';
        test.info().annotations.push({ type: 'orden', description: order });

        // c. Traslado + today (another asset: the filter on A-0006 is cleared first).
        await livewire(page, () => page.getByRole('button', { name: 'Limpiar filtros' }).click());
        await pick(page, 'Tipo de movimiento', 'Traslado');
        await dates(page, isoDay(), isoDay());
        expect(new Set(await previewColumn(page, 'Tipo de movimiento'))).toEqual(new Set(['Traslado']));
        await expect(previewRows(page).filter({ hasText: 'Reubicación de prueba QA' })).toHaveCount(1);
    });
});
