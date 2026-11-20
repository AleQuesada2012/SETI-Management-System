import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate } from '../support/dates';
import { registerAsset, tag } from '../support/assets';

// CP-06 · Traslado de activo entre espacios (RF_06; CU_RF_06), peer review by Diego Zamora. The case moves PRUEBA-DZ-04 from
// LAB-101 to LAB-204, a space its administrador does not manage; here Mario Solís (administrador, LAB-110) moves an asset
// to LAB-316, which belongs to Lucía Campos (administrador2).

const DESTINATION = 'LAB-316 · Laboratorio 316';

test.describe('CP-06 · Traslado de activo entre espacios', () => {
    test.describe.configure({ mode: 'serial' });

    let moved: { id: number; code: string };

    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. traslada el activo a otro espacio', async ({ page }) => {
            moved = await registerAsset(page, { tag: tag('06a') });
            await page.goto(`/activos/${moved.id}`);
            await page.getByTestId('move-asset-button').click();

            await page.getByTestId('movement-type').selectOption({ label: 'Traslado a otro espacio' });
            await page.getByTestId('movement-destination').selectOption({ label: DESTINATION });
            await page.getByLabel('Motivo').fill('Reubicación (prueba)');
            await page.getByTestId('move-asset-submit').click();

            await expect(page.getByText(`El activo ${moved.code} se trasladó al espacio ${DESTINATION}.`)).toBeVisible();
            await expect(page).toHaveURL(new RegExp(`/activos/${moved.id}$`));
            await expect(page.getByText('LAB-316').first()).toBeVisible();
            await expect(page.getByTestId('asset-movements')).toContainText('Traslado');
        });

        test('b. una fecha anterior al último movimiento se rechaza', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('06b') });
            await page.goto(`/activos/${asset.id}/movimiento`);

            await page.getByTestId('movement-type').selectOption({ label: 'Traslado a otro espacio' });
            await page.getByTestId('movement-destination').selectOption({ label: DESTINATION });
            await page.getByLabel('Fecha y hora').fill(`${isoDate(-1)}T10:00`);
            await page.getByLabel('Motivo').fill('Fecha anterior (prueba)');
            await page.getByTestId('move-asset-submit').click();

            await expect(page.getByText('El activo ya tiene un movimiento posterior a esa fecha.')).toBeVisible();
            await expect(page).toHaveURL(/\/movimiento$/);
        });

        test('c. un administrador que ya no es encargado del espacio del activo no puede trasladarlo', async ({ page }) => {
            await page.goto(`/activos/${moved.id}`);
            await expect(page.getByTestId('move-denied')).toContainText('No estás asignado como encargado del espacio de este activo, así que no puedes trasladarlo ni registrar sus movimientos.');
            await expect(page.getByTestId('move-asset-button')).toHaveCount(0);

            const response = await page.goto(`/activos/${moved.id}/movimiento`);
            expect(response?.status()).toBe(403);
            await expect(page.getByText('Acceso denegado').first()).toBeVisible();
        });
    });

    test('d. un Visualizador no ve Registrar movimiento ni el aviso de denegación', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(`/activos/${moved.id}`);

        await expect(page.getByTestId('move-asset-button')).toHaveCount(0);
        await expect(page.getByTestId('move-denied')).toHaveCount(0);
    });
});
