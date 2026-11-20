import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, tag } from '../support/assets';

// CP-09 · Consulta del historial de un activo (RF_09; CU_RF_09), peer review by Diego Zamora. The assets of the case
// (PRUEBA-DZ-01, edited, and PRUEBA-DZ-04, moved) are prepared by the test as Mario Solís (administrador).

test.describe('CP-09 · Consulta del historial de un activo', () => {
    test.describe.configure({ mode: 'serial' });

    let edited: { id: number; code: string };
    let moved: { id: number; code: string };

    test.beforeAll(async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        edited = await registerAsset(page, { tag: tag('09a') });
        await page.goto(`/activos/${edited.id}/editar`);
        await page.getByLabel('Modelo').fill('Modelo editado DZ');
        await page.getByTestId('edit-asset-submit').click();
        await expect(page.getByText(/se guardaron correctamente/).first()).toBeVisible();

        moved = await registerAsset(page, { tag: tag('09b') });
        await page.goto(`/activos/${moved.id}/movimiento`);
        await page.getByTestId('movement-type').selectOption({ label: 'Traslado a otro espacio' });
        await page.getByTestId('movement-destination').selectOption({ label: 'LAB-316 · Laboratorio 316' });
        await page.getByLabel('Motivo').fill('Reubicación (prueba)');
        await page.getByTestId('move-asset-submit').click();
        await expect(page.getByText(/se trasladó al espacio/)).toBeVisible();
    });

    test.describe('como Visualizador', () => {
        test.use({ storageState: storageState('visualizador') });

        test('a. quién cambió el activo', async ({ page }) => {
            await page.goto(`/activos/${edited.id}`);
            await page.getByTestId('asset-history-button').click();

            await expect(page.getByText('Historial del activo').first()).toBeVisible();
            const entries = page.getByTestId('history-entry');
            await expect(entries.filter({ hasText: 'Registro del activo' })).toHaveCount(1);
            await expect(entries.filter({ hasText: 'Modificación de datos' }).first()).toContainText('Modelo editado DZ');
            await expect(entries.filter({ hasText: 'Cambio' }).first()).toBeVisible();
            await expect(entries.filter({ hasText: 'Movimiento' }).first()).toBeVisible();
            await expect(entries.first()).toContainText('Por Mario Solís');
            await expect(entries.first()).toContainText(/\d{2}\/\d{2}\/\d{4}/);
        });

        test('b. dónde ha estado', async ({ page }) => {
            await page.goto(`/activos/${moved.id}`);
            await page.getByTestId('asset-history-button').click();
            await page.getByRole('link', { name: 'Volver al activo' }).click();
            await page.getByTestId('asset-history-button').click();

            const move = page.getByTestId('history-entry').filter({ hasText: 'LAB-316 · Laboratorio 316' });
            await expect(move).toContainText('LAB-110 · Laboratorio 110 → LAB-316 · Laboratorio 316');
            await expect(move).toContainText('Motivo: Reubicación (prueba)');
        });

        test('c. el historial de un activo que no existe', async ({ page }) => {
            const response = await page.goto('/activos/999999/historial');
            expect(response?.status()).toBe(404);
            await expect(page.getByText('Página no encontrada').first()).toBeVisible();
            await expect(page.getByText('La página o el registro que buscas no existe o fue eliminado.')).toBeVisible();
        });
    });

    test('d. un Reservante recibe Acceso denegado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const response = await page.goto(`/activos/${edited.id}/historial`);
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
