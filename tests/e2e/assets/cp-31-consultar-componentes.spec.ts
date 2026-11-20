import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, registerEvent, tag } from '../support/assets';

// CP-31 · Consultar componentes de un activo (RF_31; CU_RF_31), peer review by Diego Zamora. The assets of the case
// (PRUEBA-DZ-01 with "Memoria RAM", PRUEBA-DZ-08 decommissioned with a component) are prepared by the tests as Mario Solís.

test.describe('CP-31 · Consultar componentes de un activo', () => {
    test.describe.configure({ mode: 'serial' });

    let withComponent: { id: number; code: string };
    let decommissioned: { id: number; code: string };
    let without: { id: number; code: string };

    test.beforeAll(async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        for (const suffix of ['31a', '31c']) {
            const asset = await registerAsset(page, { tag: tag(suffix) });
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('add-component-button').click();
            await page.getByTestId('component-name').fill('Memoria RAM');
            await page.getByLabel('Especificaciones').fill('16 GB DDR4 3200');
            await page.getByLabel('Número de serie').fill('RAM-0001');
            await page.getByTestId('component-quantity').fill('2');
            await page.getByLabel('Notas').fill('Dos módulos');
            await page.getByTestId('save-component').click();
            await expect(page.getByTestId('component-row')).toHaveCount(1);
            if (suffix === '31a') {
                withComponent = asset;
            } else {
                await registerEvent(page, asset, 'Baja', 'Daño irreparable (prueba)');
                decommissioned = asset;
            }
        }
        without = await registerAsset(page, { tag: tag('31b') });
    });

    test.use({ storageState: storageState('visualizador') });

    test('a. consulta los componentes', async ({ page }) => {
        await page.goto(`/activos/${withComponent.id}`);
        const card = page.getByTestId('asset-components');
        for (const column of ['Nombre', 'Especificaciones', 'Número de serie', 'Cantidad', 'Notas']) {
            await expect(card.getByRole('columnheader', { name: column })).toBeVisible();
        }
        const row = card.getByTestId('component-row');
        for (const value of ['Memoria RAM', '16 GB DDR4 3200', 'RAM-0001', '2', 'Dos módulos']) {
            await expect(row).toContainText(value);
        }
    });

    test('b. un activo sin componentes', async ({ page }) => {
        await page.goto(`/activos/${without.id}`);
        await expect(page.getByTestId('no-components')).toHaveText('Este activo no tiene componentes asociados.');
    });

    test('c. un activo dado de baja se consulta sin botones para modificar', async ({ page }) => {
        await page.goto(`/activos/${decommissioned.id}`);
        const card = page.getByTestId('asset-components');
        await expect(card.getByTestId('component-row')).toContainText('Memoria RAM');
        for (const id of ['add-component-button', 'edit-component-button', 'remove-component-button']) {
            await expect(card.getByTestId(id)).toHaveCount(0);
        }
    });
});
