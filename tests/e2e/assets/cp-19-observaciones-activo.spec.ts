import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, tag } from '../support/assets';

// CP-19 · Registrar observaciones sobre el activo (RF_19; CU_RF_19), peer review by Diego Zamora.

const NOTE = 'Dato de prueba: se elimina antes de entregar el sistema.';

test.describe('CP-19 · Registrar observaciones sobre el activo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra, b. deja vacías y c. modifica las observaciones', async ({ page }) => {
            // a.
            await page.goto('/activos/nuevo');
            await page.getByTestId('asset-space').selectOption({ index: 1 });
            await page.getByLabel('Tipo de activo').selectOption({ index: 1 });
            await page.getByLabel(/Condición/).selectOption({ label: 'Buen estado (Verde)' });
            await page.getByLabel('Placa').fill(tag('19a'));
            await page.getByLabel('Observaciones').fill(NOTE);
            await page.getByTestId('create-asset-submit').click();
            await expect(page).toHaveURL(/\/activos\/(\d+)$/);
            const id = page.url().split('/').pop();
            await expect(page.getByText(NOTE)).toBeVisible();

            // b.
            const empty = await registerAsset(page, { tag: tag('19b'), name: `Sin observaciones ${tag('19b')}` });
            await page.goto(`/activos/${empty.id}`);
            await expect(page.getByText('Sin observaciones.')).toBeVisible();

            // c.
            await page.goto(`/activos/${id}/editar`);
            await page.getByLabel('Observaciones').fill('Observación modificada (prueba)');
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText('Observación modificada (prueba)')).toBeVisible();
            await expect(page.getByText(NOTE)).toHaveCount(0);
        });
    });

    test('d. un Visualizador recibe Acceso denegado en la edición', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/activos/1/editar');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
