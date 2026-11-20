import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { DEMO_ASSET, registerAsset, tag } from '../support/assets';

// CP-02 · Eliminar activo (RF_02; CU_RF_02), peer review by Diego Zamora. The assets of tests a to c (PRUEBA-DZ-02 and -03 in
// the case) are registered by the test itself in LAB-110, managed by Mario Solís (administrador). DEF-13 (#259), fixed in #266: in
// test c the confirmation of an asset another tab already deleted shows the notice instead of a 404.

test.describe('CP-02 · Eliminar activo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. cancelar la confirmación deja el activo como estaba', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('02a') });
            await page.goto(`/activos/${asset.id}`);

            await page.getByRole('button', { name: 'Eliminar', exact: true }).click();
            const dialog = page.getByRole('dialog');
            await expect(dialog).toContainText('¿Eliminar el activo');
            await dialog.getByRole('button', { name: 'Cancelar' }).click();

            await expect(dialog).toBeHidden();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));
            await page.goto('/activos');
            await page.getByTestId('asset-search').fill(tag('02a'));
            await expect(page.getByRole('link', { name: asset.code, exact: true })).toBeVisible();
        });

        test('b. eliminar un activo registrado por error: aviso, inventario y búsqueda', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('02b') });
            await page.goto(`/activos/${asset.id}`);

            await page.getByRole('button', { name: 'Eliminar', exact: true }).click();
            await expect(page.getByRole('dialog')).toContainText('¿Eliminar el activo');
            await page.getByRole('button', { name: 'Eliminar activo' }).click();

            await expect(page).toHaveURL(/\/activos$/);
            await expect(page.getByText(/^Se eliminó el activo .+\.$/).first()).toBeVisible();

            await page.getByTestId('asset-search').fill(tag('02b'));
            await expect(page.getByTestId('empty-inventory')).toContainText(`El activo buscado no existe: ningún activo coincide con «${tag('02b')}».`);
        });

        test('c. un activo que otra pestaña ya eliminó dice que ya no existe', async ({ browser }) => {
            const context = await browser.newContext({ storageState: storageState('administrador') });
            const first = await context.newPage();
            const second = await context.newPage();
            const asset = await registerAsset(first, { tag: tag('02c') });

            await first.goto(`/activos/${asset.id}`);
            await second.goto(`/activos/${asset.id}`);

            await first.getByRole('button', { name: 'Eliminar', exact: true }).click();
            await first.getByRole('button', { name: 'Eliminar activo' }).click();
            await expect(first).toHaveURL(/\/activos$/);

            await second.getByRole('button', { name: 'Eliminar', exact: true }).click();
            await second.getByRole('button', { name: 'Eliminar activo' }).click();
            const notice = second.getByText(/^El activo .+ ya no existe: fue eliminado anteriormente\.$/).first();
            await expect(notice).toBeVisible();
            test.info().annotations.push({ type: 'aviso', description: await notice.innerText() });
        });
    });

    for (const role of ['visualizador', 'administrador2'] as const) {
        test(`d. ${role}: no aparece el botón Eliminar`, async ({ browser }) => {
            const page = await (await browser.newContext({ storageState: storageState(role) })).newPage();
            await page.goto(`/activos/${DEMO_ASSET.id}`);

            await expect(page.getByText('Detalle del activo').first()).toBeVisible();
            await expect(page.getByRole('button', { name: 'Eliminar', exact: true })).toHaveCount(0);
        });
    }
});
