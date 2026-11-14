import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { appliedFilters, previewTotal } from '../support/qa';

// CP-81 · Generar estadísticas de inventario por categoría (RF_81, CU_RF_81).

test.describe('CP-81 · Generar estadísticas de inventario por categoría', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. muestra la cantidad de activos de cada categoría', async ({ page }) => {
        await page.goto('/reportes/estadisticas');
        const table = page.getByRole('table', { name: 'Categoría' });
        for (const category of ['Cómputo', 'Mobiliario', 'Audiovisual', 'Redes', 'Otros']) {
            await expect(table.getByRole('row').filter({ hasText: category })).toHaveCount(1);
        }
    });

    test('b. el nombre de una categoría abre el inventario filtrado con la misma cantidad', async ({ page }) => {
        await page.goto('/reportes/estadisticas');
        const row = page.getByRole('table', { name: 'Categoría' }).getByRole('row').filter({ hasText: 'Cómputo' });
        const expected = Number(await row.getByRole('cell').nth(1).innerText());
        await row.getByRole('link', { name: 'Cómputo' }).click();
        await expect(page).toHaveURL(/\/reportes\/assets_inventory\?/);
        await expect(appliedFilters(page)).toContainText('Categoría: Cómputo');
        expect(await previewTotal(page)).toBe(expected);
    });
});
