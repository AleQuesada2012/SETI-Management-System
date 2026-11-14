import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';

// CP-56 · Consultar activos de un espacio (RF_56, duplicate of RF_35; CU_RF_56). LAB-110 holds 21 demo assets;
// LAB-316 holds none.

test.describe('CP-56 · Consultar activos de un espacio', () => {
    test.use({ storageState: storageState('visualizador') });

    test('a. lista los activos del espacio con identificación, nombre, marca, modelo y estado', async ({ page }) => {
        await page.goto('/espacios/1');

        await expect(page.getByRole('heading', { name: 'Activos en el espacio (21)' })).toBeVisible();
        const assets = page.getByRole('table').filter({ hasText: 'A-0001' });
        for (const column of ['Código', 'Activo', 'Tipo', 'Condición', 'Estado']) {
            await expect(assets.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        const first = assets.getByRole('row').filter({ hasText: 'A-0001' });
        await expect(first).toContainText('Dell OptiPlex 7010');
        await expect(first).toContainText('En servicio');
    });

    test('b. abre un activo desde la lista del espacio', async ({ page }) => {
        await page.goto('/espacios/1');
        await page.getByRole('link', { name: 'A-0001', exact: true }).click();

        await expect(page).toHaveURL(/\/activos\/1$/);
    });

    test('c. indica cuando el espacio no tiene activos', async ({ page }) => {
        await page.goto('/espacios/3');

        await expect(page.getByRole('heading', { name: 'Activos en el espacio (0)' })).toBeVisible();
        await expect(page.getByText('Este espacio no tiene activos registrados.')).toBeVisible();
    });
});
