import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, tag } from '../support/assets';

// CP-05 · Asignar estado a activo (RF_05; CU_RF_05), peer review by Diego Zamora. The asset of the case (PRUEBA-DZ-01) is
// registered by the tests, in condition "Buen estado", in LAB-110 (managed by Mario Solís, administrador).

test.describe('CP-05 · Asignar estado a activo', () => {
    test.describe.configure({ mode: 'serial' });

    let asset: { id: number; code: string };

    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. asigna el color rojo con su descripción', async ({ page }) => {
            asset = await registerAsset(page, { tag: tag('05') });
            await page.goto(`/activos/${asset.id}/editar`);

            await page.getByLabel(/Condición/).selectOption({ label: 'Dañado (Rojo)' });
            await page.getByLabel('Descripción de la condición').fill('Pantalla rayada (prueba)');
            await page.getByTestId('edit-asset-submit').click();

            await expect(page.getByText(/^El activo A-\d+ quedó en condición Dañado\.$/).first()).toBeVisible();
            await expect(page.getByTestId('asset-condition')).toHaveText('Dañado');
            await expect(page.getByText('Pantalla rayada (prueba)').first()).toBeVisible();
            const color = await page.getByTestId('condition-badge').first().evaluate((el) => getComputedStyle(el).color);
            test.info().annotations.push({ type: 'color de la insignia "Dañado"', description: color });
        });

        test('b. asigna el color verde', async ({ page }) => {
            await page.goto(`/activos/${asset.id}/editar`);
            await page.getByLabel(/Condición/).selectOption({ label: 'Buen estado (Verde)' });
            await page.getByTestId('edit-asset-submit').click();

            await expect(page.getByText(/^El activo A-\d+ quedó en condición Buen estado\.$/).first()).toBeVisible();
            await expect(page.getByTestId('asset-condition')).toHaveText('Buen estado');
        });

        test('c. el inventario filtra por el color de la condición y muestra la insignia verde', async ({ page }) => {
            await page.goto('/activos');
            await page.getByTestId('asset-filters').getByLabel('Color de la condición').selectOption({ label: 'Verde' });
            await page.getByTestId('asset-search').fill(tag('05'));

            const row = page.getByRole('row').filter({ hasText: asset.code });
            await expect(row).toBeVisible();
            await expect(row).toContainText('Buen estado');
        });
    });

    test('d. un Visualizador no ve Editar y la dirección responde Acceso denegado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(`/activos/${asset.id}`);
        await expect(page.getByTestId('edit-asset-button')).toHaveCount(0);

        const response = await page.goto(`/activos/${asset.id}/editar`);
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
