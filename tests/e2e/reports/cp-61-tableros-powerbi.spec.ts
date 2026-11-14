import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';

// CP-61 · Visualizar tableros interactivos (RF_61, CU_RF_61). Tests a, b and d need the published Power BI report and a
// licensed Microsoft account: they are run on the site (https://3-129-66-37.sslip.io/reportes/tableros), not here.

test.describe('CP-61 · Visualizar tableros interactivos', () => {
    test('a. (local) sin POWERBI_EMBED_URL la página lo explica en lugar de un marco vacío', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await page.goto('/tablero');
        await page.getByRole('link', { name: 'Tableros de Power BI' }).click();
        await expect(page.getByRole('heading', { name: 'Tableros de Power BI', level: 2 })).toBeVisible();
        await expect(page.getByText('Los tableros de Power BI todavía no están configurados. Comunícate con el Superusuario del sistema.')).toBeVisible();
    });

    test('c. el rol Reportes no ve la opción y la dirección responde "Acceso denegado" (403)', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/tablero');
        await expect(page.getByRole('link', { name: 'Tableros de Power BI' })).toHaveCount(0);
        const response = await page.goto('/reportes/tableros');
        expect(response?.status()).toBe(403);
        await expect(page.getByRole('heading', { name: 'Acceso denegado' })).toBeVisible();
    });
});
