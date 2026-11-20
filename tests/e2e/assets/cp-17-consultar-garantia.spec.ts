import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { registerAsset, tag } from '../support/assets';

// CP-17 · Consultar garantía de activos (RF_17; CU_RF_17), peer review by Diego Zamora. The assets with an active and an
// expired warranty are prepared by the test (PRUEBA-DZ-01 before and after the CP-16 c); the chair without warranty is one
// of the four Aeron chairs of LAB-110 (the deployed site has it in AULA-M1).

test.describe('CP-17 · Consultar garantía de activos', () => {
    test.describe.configure({ mode: 'serial' });

    let active: { id: number; code: string };
    let expired: { id: number; code: string };

    test.beforeAll(async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        for (const [suffix, start, end] of [['17a', -30, 330], ['17b', -400, -5]] as const) {
            const asset = await registerAsset(page, { tag: tag(suffix) });
            await page.goto(`/activos/${asset.id}/editar`);
            await page.getByLabel('Inicio de la garantía').fill(isoDate(start));
            await page.getByLabel('Fin de la garantía').fill(isoDate(end));
            await page.getByTestId('edit-asset-submit').click();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));
            if (suffix === '17a') {
                active = asset;
            } else {
                expired = asset;
            }
        }
    });

    test.use({ storageState: storageState('visualizador') });

    test('a. garantía vigente', async ({ page }) => {
        await page.goto(`/activos/${active.id}`);
        const warranty = page.getByTestId('asset-warranty');
        await expect(warranty).toContainText('Cuenta con garantía');
        await expect(warranty).toContainText('Sí');
        await expect(warranty).toContainText('Inicio de la garantía');
        await expect(warranty).toContainText(shownDate(isoDate(-30)));
        await expect(warranty).toContainText('Fin de la garantía');
        await expect(warranty).toContainText(shownDate(isoDate(330)));
        await expect(warranty).toContainText('Vigente');
    });

    test('b. garantía vencida', async ({ page }) => {
        await page.goto(`/activos/${expired.id}`);
        const warranty = page.getByTestId('asset-warranty');
        await expect(warranty).toContainText('Sí');
        await expect(warranty).toContainText(shownDate(isoDate(-400)));
        await expect(warranty).toContainText(shownDate(isoDate(-5)));
        await expect(warranty).toContainText('Vencida');
    });

    test('c. un activo sin garantía', async ({ page }) => {
        await page.goto('/activos');
        await page.getByTestId('asset-filters').getByLabel('Espacio').selectOption({ label: 'LAB-110 · Laboratorio 110' });
        await page.getByTestId('asset-search').fill('Aeron');
        await page.getByRole('row').filter({ hasText: 'Silla' }).first().getByRole('link').first().click();

        const warranty = page.getByTestId('asset-warranty');
        await expect(warranty).toContainText('Cuenta con garantía');
        await expect(warranty).toContainText('No');
        await expect(warranty).toContainText('Sin garantía');
    });
});
