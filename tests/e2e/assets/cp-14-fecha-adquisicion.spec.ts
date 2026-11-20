import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate } from '../support/dates';
import { registerAsset, tag } from '../support/assets';

// CP-14 · Registrar fecha de adquisición de activos (RF_14; CU_RF_14), peer review by Diego Zamora. The asset of the case
// (PRUEBA-DZ-01) is registered by each test in LAB-110, managed by Mario Solís (administrador).

const ddmmyyyy = (offset: number) => isoDate(offset).split('-').reverse().join('/');

test.describe('CP-14 · Registrar fecha de adquisición de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra la fecha y b. la modifica', async ({ page }) => {
            await page.goto('/activos/nuevo');
            await page.getByTestId('asset-space').selectOption({ index: 1 });
            await page.getByLabel('Tipo de activo').selectOption({ index: 1 });
            await page.getByLabel(/Condición/).selectOption({ label: 'Buen estado (Verde)' });
            await page.getByLabel('Placa').fill(tag('14a'));
            await page.getByLabel('Fecha de adquisición').fill(isoDate(-30));
            await page.getByTestId('create-asset-submit').click();

            await expect(page).toHaveURL(/\/activos\/(\d+)$/);
            const id = page.url().split('/').pop();
            await expect(page.getByText(ddmmyyyy(-30)).first()).toBeVisible();

            await page.goto(`/activos/${id}/editar`);
            await page.getByLabel('Fecha de adquisición').fill(isoDate(-10));
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText(ddmmyyyy(-10)).first()).toBeVisible();
        });

        test('c. no acepta una fecha futura', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('14c') });
            await page.goto(`/activos/${asset.id}/editar`);
            const field = page.getByLabel('Fecha de adquisición');

            expect(await field.getAttribute('max'), 'el calendario no deja elegir fechas futuras').toBe(isoDate(0));
            await field.fill(isoDate(5));
            await page.getByTestId('edit-asset-submit').click();
            const message = await field.evaluate((el) => (el as HTMLInputElement).validationMessage);
            expect(message).not.toBe('');
            await expect(page).toHaveURL(/\/editar$/);
            test.info().annotations.push({ type: 'aviso del navegador', description: message });
        });
    });

    test('d. un Visualizador recibe Acceso denegado en la edición', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/activos/1/editar');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
