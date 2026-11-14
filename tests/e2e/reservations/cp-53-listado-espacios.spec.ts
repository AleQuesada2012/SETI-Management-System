import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';

// CP-53 · Consultar listado de espacios (RF_53, CU_RF_53). The demo data has seven spaces.

test.describe('CP-53 · Consultar listado de espacios', () => {
    test.use({ storageState: storageState('reservante') });

    test('a. muestra cada espacio con nombre, ubicación, capacidad, tipo y estado', async ({ page }) => {
        await page.goto('/espacios');

        await expect(page.getByText('7 espacios', { exact: true })).toBeVisible();
        for (const column of ['Espacio', 'Ubicación', 'Capacidad', 'Tipo', 'Estado']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        const lab = page.getByRole('row').filter({ hasText: 'LAB-110' });
        await expect(lab).toContainText('Laboratorio 110');
        await expect(lab).toContainText('Edificio A · Piso 1');
        await expect(lab.getByRole('cell').nth(2)).toHaveText('30');
        await expect(lab).toContainText('Laboratorio');
        await expect(lab).toContainText('Disponible');
    });

    test('b. abre la información detallada de un espacio desde la lista', async ({ page }) => {
        await page.goto('/espacios');
        await page.getByRole('link', { name: 'Laboratorio 110', exact: true }).click();

        await expect(page).toHaveURL(/\/espacios\/1$/);
        await expect(page.getByRole('heading', { name: 'LAB-110 · Laboratorio 110' })).toBeVisible();
        await expect(page.getByText('Información del espacio')).toBeVisible();
    });

    test('c. sin iniciar sesión, la lista lleva al inicio de sesión', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: { cookies: [], origins: [] } })).newPage();
        await page.goto('/espacios');

        await expect(page).toHaveURL(/\/login$/);
    });
});
