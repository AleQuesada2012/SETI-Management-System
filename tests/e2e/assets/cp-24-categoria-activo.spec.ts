import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { ASSET_SPACE, tag } from '../support/assets';

// CP-24 · Clasificar activos por categoría (RF_24; CU_RF_24), peer review by Diego Zamora.

test.describe('CP-24 · Clasificar activos por categoría', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. asigna una categoría, b. la cambia y c. el tipo es obligatorio', async ({ page }) => {
            await page.goto('/activos/nuevo');
            const type = page.getByLabel('Tipo de activo');

            // the types are grouped by category
            const groups = await type.locator('optgroup').evaluateAll((els) => els.map((el) => (el as HTMLOptGroupElement).label));
            expect(groups).toEqual(expect.arrayContaining(['Redes', 'Audiovisual']));
            test.info().annotations.push({ type: 'grupos de tipos', description: groups.join(', ') });

            // c.
            await page.getByTestId('asset-space').selectOption({ label: ASSET_SPACE.label });
            await page.getByLabel(/Condición/).selectOption({ label: 'Buen estado (Verde)' });
            await page.getByLabel('Placa').fill(tag('24a'));
            await page.getByTestId('create-asset-submit').click();
            await expect(page).toHaveURL(/\/activos\/nuevo$/);
            expect(await type.evaluate((el) => (el as HTMLSelectElement).validationMessage)).not.toBe('');

            // a.
            await type.selectOption({ label: 'Switch' });
            await page.getByTestId('create-asset-submit').click();
            await expect(page).toHaveURL(/\/activos\/(\d+)$/);
            const id = page.url().split('/').pop();
            await expect(page.getByText('Categoría').locator('xpath=following-sibling::dd[1]')).toHaveText('Redes');

            // b.
            await page.goto(`/activos/${id}/editar`);
            await page.getByLabel('Tipo de activo').selectOption({ label: 'Proyector' });
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText('Categoría').locator('xpath=following-sibling::dd[1]')).toHaveText('Audiovisual');
        });

        test('d. filtra por categoría', async ({ page }) => {
            await page.goto('/activos');
            await page.getByTestId('asset-filters').getByLabel('Categoría').selectOption({ label: 'Redes' });

            const categories = async () => page.getByRole('row').evaluateAll((els) => [...new Set(els.slice(1).map((el) => el.children[2]?.textContent?.trim()))]);
            await expect.poll(categories).toEqual(['Redes']);
        });
    });

    test('e. un Visualizador recibe Acceso denegado al añadir', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/activos/nuevo');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
