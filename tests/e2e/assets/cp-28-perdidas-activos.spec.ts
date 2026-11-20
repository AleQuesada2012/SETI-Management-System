import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { shownDate, isoDate } from '../support/dates';
import { registerAsset, registerEvent, tag } from '../support/assets';

// CP-28 · Registrar pérdidas de activos (RF_28; CU_RF_28), peer review by Diego Zamora. The asset of the case (PRUEBA-DZ-06)
// is registered by each test in LAB-110, managed by Mario Solís (administrador).

test.describe('CP-28 · Registrar pérdidas de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra la pérdida, b. no se traslada y d. el historial se conserva y se recupera', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('28a') });
            await registerEvent(page, asset, 'Pérdida', 'No se encontró en el inventario (prueba)');

            // a.
            await expect(page.getByTestId('asset-status')).toHaveText('Perdido');
            await expect(page.getByText('Fecha de la pérdida')).toBeVisible();
            await expect(page.getByTestId('asset-status-since')).toHaveText(shownDate(isoDate(0)));
            await page.goto('/activos');
            await page.getByTestId('asset-search').fill(tag('28a'));
            await expect(page.getByRole('row').filter({ hasText: asset.code })).toContainText('Perdido');
            await page.getByTestId('asset-filters').getByLabel('Estado del ciclo de vida').selectOption({ label: 'En servicio' });
            await expect(page.getByTestId('empty-inventory')).toBeVisible();

            // b.
            await page.goto(`/activos/${asset.id}/movimiento`);
            await expect(page.getByTestId('cannot-move')).toContainText('Este activo está perdido: registra su recuperación antes de moverlo.');

            // d.
            await page.goto(`/activos/${asset.id}/historial`);
            const entries = page.getByTestId('history-entry');
            await expect(entries.filter({ hasText: 'Registro del activo' })).toHaveCount(1);
            await expect(entries.filter({ hasText: 'Pérdida' }).first()).toBeVisible();
            await registerEvent(page, asset, 'Recuperación', 'Apareció en la bodega (prueba)');
            await expect(page.getByTestId('asset-status')).toHaveText('En servicio');
        });

        test('c. una pérdida sin descripción se rechaza', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('28c') });
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('register-event-button').click();
            await page.getByTestId('event-kind').selectOption({ label: 'Pérdida' });
            await page.getByTestId('save-event').click();

            await expect(page.getByTestId('event-row')).toHaveCount(0);
            const message = await page.getByTestId('event-description').evaluate((el) => (el as HTMLTextAreaElement).validationMessage);
            expect(message, 'la descripción es obligatoria').not.toBe('');
            test.info().annotations.push({ type: 'aviso del navegador', description: message });
            await expect(page.getByTestId('asset-status')).toHaveText('En servicio');
        });
    });

    test('e. un Visualizador ve los eventos pero no puede registrarlos', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        const asset = await registerAsset(admin, { tag: tag('28e') });
        await registerEvent(admin, asset, 'Pérdida', 'No se encontró (prueba)');

        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(`/activos/${asset.id}`);
        await expect(page.getByTestId('event-row')).toContainText('Pérdida');
        await expect(page.getByTestId('register-event-button')).toHaveCount(0);
    });
});
