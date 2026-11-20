import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate } from '../support/dates';
import { registerAsset, tag } from '../support/assets';

// CP-04 · Editar activo (RF_04; CU_RF_04), peer review by Diego Zamora. The asset of the case (PRUEBA-DZ-01) is registered
// by each test in LAB-110, managed by Mario Solís (administrador); admin2@ manages other spaces.

test.describe('CP-04 · Editar activo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. edita el nombre, el modelo y la fecha de adquisición', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('04a') });
            await page.goto(`/activos/${asset.id}/editar`);

            await page.getByLabel('Nombre', { exact: true }).fill('Laptop editada DZ');
            await page.getByLabel('Modelo').fill('Latitude 7440');
            await page.getByLabel('Fecha de adquisición').fill(isoDate(-10));
            await page.getByTestId('edit-asset-submit').click();

            await expect(page.getByText(/^Los cambios del activo A-\d+ se guardaron correctamente\.$/).first()).toBeVisible();
            await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));
            for (const value of ['Laptop editada DZ', 'Latitude 7440']) {
                await expect(page.getByText(value).first()).toBeVisible();
            }
        });

        test('b. el espacio se ve como texto y cambia con un traslado', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('04b') });
            await page.goto(`/activos/${asset.id}/editar`);

            const space = page.getByTestId('asset-space');
            await expect(space).toContainText('LAB-110');
            expect(await space.evaluate((el) => el.tagName), 'el espacio es texto, no una lista').not.toBe('SELECT');
            await expect(page.getByText('El espacio cambia registrando un traslado, no desde este formulario.')).toBeVisible();
            await expect(page.getByTestId('edit-move-link')).toHaveText('Registrar traslado');
            await expect(page.locator('select[wire\\:model="space_id"]')).toHaveCount(0);
        });

        test('c. una placa repetida y una garantía al revés se rechazan y no se guarda nada', async ({ page }) => {
            const other = await registerAsset(page, { tag: tag('04c-otro') });
            const asset = await registerAsset(page, { tag: tag('04c') });
            expect(other.id).not.toBe(asset.id);

            await page.goto(`/activos/${asset.id}/editar`);
            await page.getByLabel('Placa').fill(tag('04c-otro'));
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText('Ya existe un activo con esa placa.')).toBeVisible();

            await page.getByLabel('Placa').fill(tag('04c'));
            await page.getByLabel('Inicio de la garantía').fill(isoDate(0));
            await page.getByLabel('Fin de la garantía').fill(isoDate(-5));
            await page.getByTestId('edit-asset-submit').click();
            await expect(page.getByText('El campo fin de la garantía debe ser una fecha posterior o igual a inicio de la garantía.')).toBeVisible();
            await expect(page).toHaveURL(/\/editar$/);

            await page.goto(`/activos/${asset.id}`);
            await expect(page.getByText(tag('04c')).first()).toBeVisible();
        });
    });

    test('d. un Visualizador no ve Editar y la dirección responde Acceso denegado a las dos cuentas', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        const asset = await registerAsset(admin, { tag: tag('04d') });

        const viewer = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await viewer.goto(`/activos/${asset.id}`);
        await expect(viewer.getByTestId('edit-asset-button')).toHaveCount(0);

        for (const role of ['visualizador', 'administrador2'] as const) {
            const page = await (await browser.newContext({ storageState: storageState(role) })).newPage();
            const response = await page.goto(`/activos/${asset.id}/editar`);
            expect(response?.status(), role).toBe(403);
            await expect(page.getByText('Acceso denegado').first()).toBeVisible();
        }
    });
});
