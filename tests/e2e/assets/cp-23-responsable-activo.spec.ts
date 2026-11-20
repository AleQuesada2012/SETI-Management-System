import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, tag } from '../support/assets';

// CP-23 · Registrar responsable de un activo (RF_23; CU_RF_23), peer review by Diego Zamora. The asset of the case
// (PRUEBA-DZ-01) is registered by each test in LAB-110, managed by Mario Solís (administrador).

test.describe('CP-23 · Registrar responsable de un activo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. asigna, b. cambia y quita el responsable, c. solo usuarios activos, d. se consulta por filtro', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('23a') });
            await page.goto(`/activos/${asset.id}/editar`);
            const owner = page.getByLabel('Responsable');

            // c.
            const names = (await owner.locator('option').allInnerTexts()).map((text) => text.trim()).filter((text) => text !== '' && text !== 'Ninguno');
            expect(names.length).toBeGreaterThanOrEqual(2);
            expect(names, 'la cuenta desactivada de Jorge Ramírez no se ofrece').not.toContain('Jorge Ramírez');
            test.info().annotations.push({ type: 'responsables ofrecidos', description: names.join(' | ') });

            // a.
            await owner.selectOption({ label: names[0] });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));
            await expect(page.getByText(names[0], { exact: true }).first()).toBeVisible();

            // d.
            await page.goto('/activos');
            await page.getByTestId('asset-search').fill(tag('23a'));
            await expect(page.getByRole('row').filter({ hasText: asset.code })).toContainText(names[0]);
            await page.getByTestId('asset-search').fill('');
            await page.getByTestId('asset-filters').getByLabel('Responsable').selectOption({ label: names[0] });
            await expect(page.getByRole('row').filter({ hasText: asset.code })).toBeVisible();

            // b.
            await page.goto(`/activos/${asset.id}/editar`);
            await owner.selectOption({ label: names[1] });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText(names[1], { exact: true }).first()).toBeVisible();
        });

        test('b. quitar el responsable con "Ninguno"', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('23b') });
            await page.goto(`/activos/${asset.id}/editar`);
            const owner = page.getByLabel('Responsable');
            await owner.selectOption({ index: 1 });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));

            await page.goto(`/activos/${asset.id}/editar`);
            await owner.selectOption({ label: 'Ninguno' }, { timeout: 5000 });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText('Responsable').locator('xpath=following-sibling::dd[1]')).toHaveText('—');
        });
    });

    test('e. un Visualizador recibe Acceso denegado en la edición', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/activos/1/editar');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
