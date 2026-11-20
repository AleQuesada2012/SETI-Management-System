import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { registerAsset, tag } from '../support/assets';

// CP-16 · Registrar garantía de activos (RF_16; CU_RF_16), peer review by Diego Zamora. The asset of the case (PRUEBA-DZ-01)
// is registered by the test in LAB-110, managed by Mario Solís (administrador).

test.describe('CP-16 · Registrar garantía de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra la garantía, b. rechaza fechas incompletas o invertidas y c. la deja vencida', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('16a') });
            const warranty = page.getByTestId('asset-warranty');
            await page.goto(`/activos/${asset.id}/editar`);

            // a.
            await page.getByLabel('Inicio de la garantía').fill(isoDate(-30));
            await page.getByLabel('Fin de la garantía').fill(isoDate(330));
            await page.getByTestId('edit-asset-submit').click();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));
            await expect(warranty).toContainText('Cuenta con garantía');
            await expect(warranty).toContainText('Sí');
            await expect(warranty).toContainText(shownDate(isoDate(-30)));
            await expect(warranty).toContainText(shownDate(isoDate(330)));
            await expect(warranty).toContainText('Vigente');

            // b.
            await page.goto(`/activos/${asset.id}/editar`);
            await page.getByLabel('Fin de la garantía').fill('');
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText('El campo fin de la garantía es obligatorio cuando inicio de la garantía está presente.')).toBeVisible();
            await page.getByLabel('Fin de la garantía').fill(isoDate(-60));
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText('El campo fin de la garantía debe ser una fecha posterior o igual a inicio de la garantía.')).toBeVisible();
            await expect(page).toHaveURL(/\/editar$/);

            // c.
            await page.getByLabel('Fin de la garantía').fill(isoDate(-5));
            await page.getByTestId('edit-asset-submit').click();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));
            await expect(warranty).toContainText('Vencida');
        });
    });

    test('d. un Visualizador recibe Acceso denegado en la edición', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/activos/1/editar');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
