import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { appliedFilters, isoDay, settle, shown, type } from '../support/qa';

// CP-82 · Generar estadísticas de mantenimiento (RF_82, CU_RF_82). qa-data.php: preventive on A-0001 (3 days ago) and
// on A-0002 (20 days ago), repair on A-0002 (yesterday).

test.describe('CP-82 · Generar estadísticas de mantenimiento', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. separa Mantenimiento y Reparación y lista los activos con más intervenciones', async ({ page }) => {
        await page.goto('/reportes/estadisticas/mantenimiento');
        await expect(page.getByText('Intervenciones realizadas: 3')).toBeVisible();
        const kinds = page.getByRole('table', { name: 'Mantenimientos y reparaciones' });
        await expect(kinds.getByRole('row').filter({ hasText: 'Mantenimiento' })).toContainText('2');
        await expect(kinds.getByRole('row').filter({ hasText: 'Reparación' })).toContainText('1');
        await expect(page.getByText(/Activos con más intervenciones \(hasta 10\)/).first()).toBeVisible();
        test.info().annotations.push({ type: 'tablas', description: (await page.getByRole('table').allInnerTexts()).join('\n---\n') });
    });

    test('b. cuenta solo las intervenciones del período, ambas fechas incluidas', async ({ page }) => {
        await page.goto('/reportes/estadisticas/mantenimiento');
        await type(page, 'Realizados desde', isoDay(-3));
        await type(page, 'Realizados hasta', isoDay(-1));
        await expect(page.getByText('Intervenciones realizadas: 2')).toBeVisible();
    });

    test('c. el nombre de un activo abre el reporte filtrado al activo y al período', async ({ page }) => {
        await page.goto('/reportes/estadisticas/mantenimiento');
        await type(page, 'Realizados desde', isoDay(-30));
        await type(page, 'Realizados hasta', isoDay());
        await page.getByRole('link', { name: /A-0002/ }).first().click();
        await expect(page).toHaveURL(/\/reportes\/maintenance\?/);
        await expect(appliedFilters(page)).toContainText('A-0002');
        await expect(appliedFilters(page)).toContainText(`Desde ${shown(isoDay(-30))}`);
        await expect(appliedFilters(page)).toContainText(`Hasta ${shown(isoDay())}`);
    });

    test('d. una fecha final anterior a la inicial se indica en lugar de las estadísticas', async ({ page }) => {
        await page.goto('/reportes/estadisticas/mantenimiento');
        await type(page, 'Realizados desde', isoDay());
        await type(page, 'Realizados hasta', isoDay(-10));
        await expect(page.getByText(/Intervenciones realizadas:/)).toHaveCount(0, { timeout: 5_000 });
        test.info().annotations.push({ type: 'mensaje', description: await page.locator('body').innerText() });
    });
});
