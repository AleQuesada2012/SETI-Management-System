import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';

// CP-30 · Consultar activos por responsable (RF_30; CU_RF_30), peer review by Diego Zamora. In the local demo data the
// assets have as responsible Mario Solís, Lucía Campos or nobody; Esteban Castro (Reservante) has none.

test.describe('CP-30 · Consultar activos por responsable', () => {
    test.use({ storageState: storageState('visualizador') });

    const responsible = (page: import('@playwright/test').Page) => page.getByTestId('asset-filters').getByLabel('Responsable');
    const columns = async (page: import('@playwright/test').Page) =>
        page.getByRole('row').evaluateAll((els) => [...new Set(els.slice(1).map((el) => el.children[5]?.textContent?.trim()))]);

    test('a. activos de un responsable', async ({ page }) => {
        await page.goto('/activos');
        await responsible(page).selectOption({ label: 'Mario Solís' });
        await expect.poll(() => columns(page)).toEqual(['Mario Solís']);
    });

    test('b. activos sin responsable', async ({ page }) => {
        await page.goto('/activos');
        await responsible(page).selectOption({ label: 'Sin responsable' });
        await expect.poll(() => columns(page)).toEqual(['—']);
    });

    test('c. un responsable sin activos', async ({ page }) => {
        await page.goto('/activos');
        await responsible(page).selectOption({ label: 'Esteban Castro' });
        await expect(page.getByTestId('empty-inventory')).toContainText('El responsable seleccionado no tiene activos asociados.');
    });
});
