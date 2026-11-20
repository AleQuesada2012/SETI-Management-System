import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { ASSET_SPACE, registerAsset, tag } from '../support/assets';

// CP-25 · Registrar movimientos de inventario (RF_25; CU_RF_25), peer review by Diego Zamora. The asset of the case
// (PRUEBA-DZ-09) is registered by the test in LAB-110, managed by Mario Solís (administrador).

test.describe('CP-25 · Registrar movimientos de inventario', () => {
    test.describe.configure({ mode: 'serial' });

    let asset: { id: number; code: string };

    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra una salida y d. la salida sin motivo se rechaza', async ({ page }) => {
            asset = await registerAsset(page, { tag: tag('25') });
            await page.goto(`/activos/${asset.id}/movimiento`);
            await page.getByTestId('movement-type').selectOption({ label: 'Salida de los espacios de SETI' });

            // d. the field becomes required once Livewire re-renders the form for an exit
            await expect(page.getByLabel('Motivo')).toHaveAttribute('required', 'required');
            await page.getByTestId('move-asset-submit').click();
            await expect(page).toHaveURL(/\/movimiento$/);
            const message = await page.getByLabel('Motivo').evaluate((el) => (el as HTMLTextAreaElement).validationMessage);
            expect(message, 'el motivo es obligatorio en una salida').not.toBe('');
            test.info().annotations.push({ type: 'aviso del navegador', description: message });

            // a.
            await page.getByLabel('Motivo').fill('Reparación externa (prueba)');
            await page.getByTestId('move-asset-submit').click();
            await expect(page.getByText(`Se registró la salida del activo ${asset.code} del espacio ${ASSET_SPACE.label}.`)).toBeVisible();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));
            await expect(page.getByText('Espacio').locator('xpath=following-sibling::dd[1]')).not.toContainText('LAB-110');
        });

        test('b. registra un ingreso y c. consulta los movimientos', async ({ page }) => {
            await page.goto(`/activos/${asset.id}/movimiento`);
            await page.getByTestId('movement-type').selectOption({ label: 'Ingreso a un espacio' });
            await page.getByTestId('movement-destination').selectOption({ label: ASSET_SPACE.label });
            await page.getByTestId('move-asset-submit').click();
            await expect(page.getByText(`El activo ${asset.code} ingresó al espacio ${ASSET_SPACE.label}.`)).toBeVisible();

            const movements = page.getByTestId('asset-movements');
            for (const column of ['Fecha', 'Tipo', 'Origen', 'Destino', 'Registrado por', 'Motivo']) {
                await expect(movements.getByRole('columnheader', { name: column })).toBeVisible();
            }
            const rows = movements.getByRole('row');
            await expect(rows).toHaveCount(4); // header + ingreso, salida, ingreso
            await expect(rows.nth(1)).toContainText('Ingreso');
            await expect(rows.nth(2)).toContainText('Salida');
            await expect(rows.nth(2)).toContainText('Mario Solís');
            await expect(rows.nth(2)).toContainText('Reparación externa (prueba)');
        });
    });

    test('e. un Visualizador no ve Registrar movimiento y recibe Acceso denegado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(`/activos/${asset.id}`);
        await expect(page.getByTestId('move-asset-button')).toHaveCount(0);
        const response = await page.goto(`/activos/${asset.id}/movimiento`);
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
