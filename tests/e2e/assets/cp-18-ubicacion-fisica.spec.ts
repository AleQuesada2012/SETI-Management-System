import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { ASSET_SPACE, tag } from '../support/assets';

// CP-18 · Registrar ubicación física del activo (RF_18; CU_RF_18), peer review by Diego Zamora. The case uses LAB-101;
// here it is LAB-110, managed by Mario Solís (administrador).

test.describe('CP-18 · Registrar ubicación física del activo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra la ubicación, b. modifica el detalle y c. exige un espacio de los que encarga', async ({ page }) => {
            await page.goto('/activos/nuevo');
            const space = page.getByTestId('asset-space');

            // c.
            const options = await space.locator('option').allInnerTexts();
            expect(options).toContain(ASSET_SPACE.label);
            expect(options.join(' | ')).not.toContain('LAB-316');
            test.info().annotations.push({ type: 'espacios ofrecidos', description: options.join(' | ') });
            await page.getByLabel('Tipo de activo').selectOption({ index: 1 });
            await page.getByLabel(/Condición/).selectOption({ label: 'Buen estado (Verde)' });
            await page.getByLabel('Placa').fill(tag('18a'));
            await page.getByTestId('create-asset-submit').click();
            await expect(page).toHaveURL(/\/activos\/nuevo$/);
            expect(await space.evaluate((el) => (el as HTMLSelectElement).validationMessage), 'el espacio es obligatorio').not.toBe('');

            // a.
            await space.selectOption({ label: ASSET_SPACE.label });
            await page.getByLabel('Detalle de la ubicación').fill('Rack 2');
            await page.getByTestId('create-asset-submit').click();
            await expect(page).toHaveURL(/\/activos\/(\d+)$/);
            const id = page.url().split('/').pop();
            await expect(page.getByText(ASSET_SPACE.label).first()).toBeVisible();
            await expect(page.getByText('Rack 2').first()).toBeVisible();

            // b.
            await page.goto(`/activos/${id}/editar`);
            await page.getByLabel('Detalle de la ubicación').fill('Escritorio 5');
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText('Escritorio 5').first()).toBeVisible();
            await expect(page.getByText(ASSET_SPACE.label).first()).toBeVisible();
        });
    });

    test('d. un Visualizador recibe Acceso denegado en la edición', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/activos/1/editar');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
