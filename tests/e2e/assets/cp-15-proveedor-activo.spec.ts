import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, tag } from '../support/assets';

// CP-15 · Registrar proveedor de activos (RF_15; CU_RF_15), peer review by Diego Zamora. The deployed site has the suppliers
// "Distribuidora Tecnológica (prueba)" and "Mobiliario de Oficina (prueba)"; here the first and second of the catalogue.

test.describe('CP-15 · Registrar proveedor de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. elige un proveedor registrado y b. lo cambia por otro', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('15a') });
            await page.goto(`/activos/${asset.id}/editar`);
            const supplier = page.getByLabel('Proveedor');

            const options = (await supplier.locator('option').allInnerTexts()).map((text) => text.trim()).filter((text) => text !== '' && text !== 'Ninguno');
            expect(options.length, 'la lista ofrece los proveedores registrados').toBeGreaterThanOrEqual(2);
            test.info().annotations.push({ type: 'proveedores ofrecidos', description: options.join(', ') });

            await supplier.selectOption({ label: options[0] });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText(options[0], { exact: true }).first()).toBeVisible();

            await page.goto(`/activos/${asset.id}/editar`);
            await supplier.selectOption({ label: options[1] });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText(options[1], { exact: true }).first()).toBeVisible();

        });

        test('b. quitar el proveedor con "Ninguno"', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('15b') });
            await page.goto(`/activos/${asset.id}/editar`);
            const supplier = page.getByLabel('Proveedor');
            await supplier.selectOption({ index: 1 });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));

            await page.goto(`/activos/${asset.id}/editar`);
            await supplier.selectOption({ label: 'Ninguno' }, { timeout: 5000 });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));
            await expect(page.getByText('Proveedor').locator('xpath=following-sibling::dd[1]')).toHaveText('—');
        });
    });

    test('c. un Visualizador recibe Acceso denegado al añadir', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/activos/nuevo');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
