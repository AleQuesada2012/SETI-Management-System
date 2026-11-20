import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, tag } from '../support/assets';

// CP-12 · Registrar componentes del equipo (RF_12; CU_RF_12), peer review by Diego Zamora. The asset of the case
// (PRUEBA-DZ-01) is registered by each test in LAB-110, managed by Mario Solís (administrador).

test.describe('CP-12 · Registrar componentes del equipo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. añade y b. modifica un componente', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('12a') });
            await page.goto(`/activos/${asset.id}`);
            const card = page.getByTestId('asset-components');

            await card.getByTestId('add-component-button').click();
            await page.getByTestId('component-name').fill('Memoria RAM');
            await page.getByLabel('Especificaciones').fill('16 GB DDR4 3200');
            await page.getByTestId('component-quantity').fill('2');
            await page.getByTestId('save-component').click();
            await expect(page.getByText('Se añadió el componente Memoria RAM.').first()).toBeVisible();
            await expect(card.getByTestId('component-row').filter({ hasText: 'Memoria RAM' })).toContainText('16 GB DDR4 3200');

            await card.getByRole('button', { name: 'Editar Memoria RAM' }).click();
            await expect(page.getByTestId('component-name')).toHaveValue('Memoria RAM');
            await expect(page.getByTestId('component-quantity')).toHaveValue('2');
            await page.getByTestId('component-quantity').fill('4');
            await page.getByTestId('save-component').click();
            await expect(page.getByText('Se actualizó el componente Memoria RAM.').first()).toBeVisible();
            await expect(card.getByTestId('component-row').filter({ hasText: 'Memoria RAM' })).toContainText('4');
        });

        test('c. elimina un componente con confirmación', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('12c') });
            await page.goto(`/activos/${asset.id}`);
            const card = page.getByTestId('asset-components');

            await card.getByTestId('add-component-button').click();
            await page.getByTestId('component-name').fill('Disco SSD');
            await page.getByTestId('component-quantity').fill('1');
            await page.getByTestId('save-component').click();
            await expect(card.getByTestId('component-row').filter({ hasText: 'Disco SSD' })).toBeVisible();

            await card.getByRole('button', { name: 'Eliminar Disco SSD' }).click();
            await expect(page.getByText('¿Eliminar el componente Disco SSD?')).toBeVisible();
            await page.getByTestId('confirm-remove-component').click();
            await expect(page.getByText('Se eliminó el componente Disco SSD.').first()).toBeVisible();
            await expect(card.getByTestId('component-row').filter({ hasText: 'Disco SSD' })).toHaveCount(0);
        });

        test('d. la validación del navegador señala el nombre vacío y la cantidad 0', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('12d') });
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('add-component-button').click();

            const name = page.getByTestId('component-name');
            const quantity = page.getByTestId('component-quantity');
            await quantity.fill('0');
            await page.getByTestId('save-component').click();
            const nameMessage = await name.evaluate((el) => (el as HTMLInputElement).validationMessage);
            expect(nameMessage, 'nombre vacío').not.toBe('');
            await expect(page.getByTestId('component-row')).toHaveCount(0);

            await name.fill('Componente sin cantidad');
            await page.getByTestId('save-component').click();
            const quantityMessage = await quantity.evaluate((el) => (el as HTMLInputElement).validationMessage);
            expect(quantityMessage, 'cantidad 0').toMatch(/1/);
            await expect(page.getByTestId('component-row')).toHaveCount(0);
            test.info().annotations.push({ type: 'avisos del navegador', description: `${nameMessage} | ${quantityMessage}` });
        });
    });

    test('e. un Visualizador ve los componentes sin botones para modificarlos', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        const asset = await registerAsset(admin, { tag: tag('12e') });
        await admin.goto(`/activos/${asset.id}`);
        await admin.getByTestId('add-component-button').click();
        await admin.getByTestId('component-name').fill('Memoria RAM');
        await admin.getByTestId('save-component').click();
        await expect(admin.getByTestId('component-row')).toHaveCount(1);

        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(`/activos/${asset.id}`);
        const card = page.getByTestId('asset-components');
        await expect(card.getByTestId('component-row')).toContainText('Memoria RAM');
        for (const id of ['add-component-button', 'edit-component-button', 'remove-component-button']) {
            await expect(card.getByTestId(id)).toHaveCount(0);
        }
    });
});
