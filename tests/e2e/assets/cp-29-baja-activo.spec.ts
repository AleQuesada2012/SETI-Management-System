import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, registerEvent, tag } from '../support/assets';

// CP-29 · Dar de baja un activo (RF_29; CU_RF_29), peer review by Diego Zamora. A decommission is definitive: the asset of
// the case (PRUEBA-DZ-08, with a component) is registered by the test in LAB-110, managed by Mario Solís (administrador).

test.describe('CP-29 · Dar de baja un activo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. da de baja, b. ya no está disponible, c. no se repite y d. conserva el historial', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('29a') });
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('add-component-button').click();
            await page.getByTestId('component-name').fill('Memoria RAM');
            await page.getByTestId('save-component').click();
            await expect(page.getByTestId('component-row')).toHaveCount(1);

            // a.
            await registerEvent(page, asset, 'Baja', 'Daño irreparable (prueba)');
            await expect(page.getByTestId('decommissioned-notice')).toBeVisible();
            await page.goto('/activos');
            await page.getByTestId('asset-search').fill(tag('29a'));
            await expect(page.getByRole('row').filter({ hasText: asset.code })).toContainText('De baja');

            // b.
            await page.goto(`/activos/${asset.id}`);
            await expect(page.getByTestId('edit-asset-button')).toHaveCount(0);
            await expect(page.getByTestId('add-component-button')).toHaveCount(0);
            await page.getByTestId('move-asset-button').click();
            await expect(page.getByTestId('movement-type')).toBeVisible();
            expect(await page.getByTestId('movement-type').locator('option').allInnerTexts()).toEqual(['Salida de los espacios de SETI']);
            await page.goto(`/activos/${asset.id}/editar`);
            await expect(page.getByText(`El activo ${asset.code} está dado de baja: su información ya no se modifica.`)).toBeVisible();

            // c.
            await page.goto(`/activos/${asset.id}`);
            await expect(page.getByTestId('register-event-button')).toHaveCount(0);

            // d.
            await page.getByTestId('asset-history-button').click();
            const entries = page.getByTestId('history-entry');
            await expect(entries.filter({ hasText: 'Registro del activo' })).toHaveCount(1);
            await expect(entries.filter({ hasText: 'Baja' }).first()).toBeVisible();
        });
    });

    test('e. un Visualizador no ve Registrar evento en un activo en servicio', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto('/activos/1');
        await expect(page.getByTestId('register-event-button')).toHaveCount(0);
    });
});
