import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate } from '../support/dates';
import { ASSET_SPACE, DEMO_ASSET, registerAsset, tag } from '../support/assets';

// CP-03 · Añadir activo (RF_03; CU_RF_03), peer review by Diego Zamora. The tags of the case (PRUEBA-DZ-01…) carry the run
// here, and the supplier is the first one of the catalogue (the deployed site has "Distribuidora Tecnológica (prueba)").

test.describe('CP-03 · Añadir activo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra un activo con todos sus datos y deja su ingreso en Movimientos', async ({ page }) => {
            const placa = tag('03a');
            await page.goto('/activos');
            await page.getByTestId('create-asset-button').click();

            await page.getByTestId('asset-space').selectOption({ label: ASSET_SPACE.label });
            await page.getByLabel('Detalle de la ubicación').fill('Escritorio 5');
            await page.getByLabel('Tipo de activo').selectOption({ label: 'Laptop' });
            await page.getByLabel(/Condición/).selectOption({ label: 'Buen estado (Verde)' });
            await page.getByLabel('Nombre', { exact: true }).fill('Laptop de prueba DZ');
            await page.getByLabel('Marca').selectOption({ label: 'Dell' });
            await page.getByLabel('Modelo').fill('Latitude 5420');
            await page.getByLabel('Placa').fill(placa);
            await page.getByLabel('Número de serie').fill(`DZ-${placa.slice(-6)}`);
            await page.getByLabel('Fecha de adquisición').fill(isoDate(-30));
            await page.getByLabel('Proveedor').selectOption({ index: 1 });
            await page.getByLabel('Inicio de la garantía').fill(isoDate(-30));
            await page.getByLabel('Fin de la garantía').fill(isoDate(330));
            const owner = (await page.getByLabel('Responsable').locator('option').allInnerTexts()).find((text) => text.includes('Mario Solís'))!;
            await page.getByLabel('Responsable').selectOption({ label: owner });
            await page.getByLabel('Observaciones').fill('Dato de prueba: se elimina antes de entregar el sistema.');
            await page.getByTestId('create-asset-submit').click();

            await expect(page).toHaveURL(/\/activos\/\d+$/);
            await expect(page.getByText(/^El activo A-\d+ se registró correctamente\.$/).first()).toBeVisible();
            for (const value of ['Laptop de prueba DZ', 'Dell', 'Latitude 5420', placa, 'Escritorio 5']) {
                await expect(page.getByText(value).first()).toBeVisible();
            }
            const movements = page.getByTestId('asset-movements');
            await expect(movements).toContainText('Ingreso');
            await expect(movements).toContainText('LAB-110');
            await expect(movements).toContainText('Registro inicial');
        });

        test('b. con los campos obligatorios vacíos el activo no se guarda', async ({ page }) => {
            await page.goto('/activos/nuevo');
            await page.getByTestId('create-asset-submit').click();

            await expect(page).toHaveURL(/\/activos\/nuevo$/);
            const message = await page.getByLabel('Tipo de activo').evaluate((el) => (el as HTMLSelectElement).validationMessage);
            // The browser words this message in its own language: Spanish for David, English in the Chromium of the tests.
            expect(message).toMatch(/Selecciona un elemento de la lista|Seleccione un elemento|Please select an item in the list/);
            test.info().annotations.push({ type: 'aviso del navegador', description: message });
        });

        test('c. una placa repetida y una garantía a medias se rechazan con su mensaje', async ({ page }) => {
            const existing = await registerAsset(page, { tag: tag('03c') });
            expect(existing.code).toMatch(/^A-\d+$/);

            await page.goto('/activos/nuevo');
            await page.getByTestId('asset-space').selectOption({ label: ASSET_SPACE.label });
            await page.getByLabel('Tipo de activo').selectOption({ index: 1 });
            await page.getByLabel(/Condición/).selectOption({ label: 'Buen estado (Verde)' });
            await page.getByLabel('Placa').fill(tag('03c'));
            await page.getByTestId('create-asset-submit').click();
            await expect(page.getByText('Ya existe un activo con esa placa.')).toBeVisible();

            await page.getByLabel('Placa').fill(tag('03c-b'));
            await page.getByLabel('Inicio de la garantía').fill(isoDate(-1));
            await page.getByTestId('create-asset-submit').click();
            await expect(page.getByText('El campo fin de la garantía es obligatorio cuando inicio de la garantía está presente.')).toBeVisible();
            await expect(page).toHaveURL(/\/activos\/nuevo$/);
        });
    });

    test('d. un Visualizador no ve Añadir activo y /activos/nuevo responde Acceso denegado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto('/activos');
        await expect(page.getByTestId('create-asset-button')).toHaveCount(0);

        const response = await page.goto('/activos/nuevo');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });

    test('d. un Administrador solo ve en Espacio los espacios de los que es encargado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('administrador2') })).newPage();
        await page.goto('/activos/nuevo');

        const options = await page.getByTestId('asset-space').locator('option').allInnerTexts();
        expect(options.join(' | ')).not.toContain(ASSET_SPACE.label);
        test.info().annotations.push({ type: 'espacios de admin2@', description: options.join(' | ') });
        expect(DEMO_ASSET.code).toBe('A-0001');
    });
});
