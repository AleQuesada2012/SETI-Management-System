import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { appliedFilters, livewire, previewColumn, previewTotal, pick, settle, type } from '../support/qa';
import { openReport } from '../support/reports';

// CP-62 · Filtrar información de los reportes (RF_62, CU_RF_62).

test.describe('CP-62 · Filtrar información de los reportes', () => {
    test.use({ storageState: storageState('reportes') });

    test('a, b y c. filtra por un criterio, combina tres y limpia los filtros', async ({ page }) => {
        await openReport(page, 'Activos', 'assets_inventory');
        const all = await previewTotal(page);

        // a. Color de condición: Ámbar.
        await pick(page, 'Color de condición', 'Ámbar');
        await expect(appliedFilters(page)).toContainText('Color de condición: Ámbar');
        const amber = await previewColumn(page, 'Condición física');
        expect(amber.length).toBeGreaterThan(0);
        expect(new Set(amber)).toEqual(new Set(['Regular']));

        // b. + Espacio LAB-110 + Marca Dell.
        await pick(page, 'Espacio', 'LAB-110 · Laboratorio 110');
        await pick(page, 'Marca', 'Dell');
        await expect(appliedFilters(page)).toContainText('Espacio: LAB-110 · Laboratorio 110');
        await expect(appliedFilters(page)).toContainText('Marca: Dell');
        const combined = await previewTotal(page);
        expect(new Set(await previewColumn(page, 'Espacio'))).toEqual(new Set(['Laboratorio 110']));
        expect(new Set(await previewColumn(page, 'Marca'))).toEqual(new Set(['Dell']));
        expect(new Set(await previewColumn(page, 'Condición física'))).toEqual(new Set(['Regular']));
        test.info().annotations.push({ type: 'cantidades', description: `todos ${all}, ámbar ${amber.length}, ámbar+LAB-110+Dell ${combined}` });

        // c. Limpiar filtros.
        await livewire(page, () => page.getByRole('button', { name: 'Limpiar filtros' }).click());
        await expect(appliedFilters(page)).toContainText('ninguno (contiene la totalidad de los registros)');
        expect(await previewTotal(page)).toBe(all);
    });

    test('d. un rango de fechas invertido muestra el error y bloquea la vista previa y la exportación', async ({ page }) => {
        await openReport(page, 'Activos', 'assets_inventory');
        await type(page, 'Desde', '2026-06-30');
        await type(page, 'Hasta', '2026-01-01');

        const error = page.locator('[data-flux-callout]').filter({ hasNotText: 'Filtros aplicados' }).first();
        await expect(error).toBeVisible();
        test.info().annotations.push({ type: 'mensaje', description: await error.innerText() });
        await expect(page.getByTestId('export-button')).toHaveCount(0);
        await expect(page.getByRole('table')).toHaveCount(0);
    });

    test('e. una búsqueda sin coincidencias muestra el mensaje de lista vacía', async ({ page }) => {
        await openReport(page, 'Espacios', 'spaces');
        await type(page, 'Buscar', 'No existe espacio');
        await expect(page.getByText('No hay registros que cumplan los filtros.')).toBeVisible();
    });
});
