import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, tag } from '../support/assets';

// CP-07 · Búsqueda de activos (RF_07; CU_RF_07), peer review by Diego Zamora. The demo data of the local site has the Dell
// Latitude 5420 laptops in LAB-312 (the deployed site has them in LAB-204) and the Cisco routers and switches in LAIMI.

test.describe('CP-07 · Búsqueda de activos', () => {
    test.use({ storageState: storageState('visualizador') });

    test('a. una coincidencia parcial, sin distinguir mayúsculas', async ({ page }) => {
        await page.goto('/activos');
        await page.getByTestId('asset-search').fill('latit');

        // Other tests of the peer review also register Latitude laptops, so the rows are matched by the word, not the model.
        const rows = page.getByRole('row').filter({ hasText: 'Latitude' });
        await expect(rows.first()).toBeVisible();
        const total = await page.getByRole('row').count();
        expect(await rows.count(), 'todas las filas son Latitude').toBe(total - 1);
        await expect(page.getByText(/^\d+ activos$/)).toBeVisible();
        await page.getByTestId('asset-search').fill('LATIT');
        await expect(rows.first()).toBeVisible();
    });

    test('b. busca por placa y por código', async ({ browser, page }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        const asset = await registerAsset(admin, { tag: tag('07b') });

        await page.goto('/activos');
        await page.getByTestId('asset-search').fill(tag('07b'));
        await expect(page.getByRole('link', { name: asset.code, exact: true })).toBeVisible();
        await expect(page.getByRole('row')).toHaveCount(2);

        await page.getByTestId('asset-search').fill(asset.code);
        await expect(page.getByRole('link', { name: asset.code, exact: true })).toBeVisible();
        await expect(page.getByRole('row')).toHaveCount(2);
    });

    test('c. un activo que no existe', async ({ page }) => {
        await page.goto('/activos');
        await page.getByTestId('asset-search').fill('Activo inexistente');
        await expect(page.getByTestId('empty-inventory')).toContainText('El activo buscado no existe: ningún activo coincide con «Activo inexistente».');
    });
});
