import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { registerAsset, tag } from '../support/assets';

// CP-22 · Registrar fecha de baja de activos (RF_22; CU_RF_22), peer review by Diego Zamora. A decommission is definitive, so
// each test registers its own asset (PRUEBA-DZ-07 in the case) in LAB-110, managed by Mario Solís (administrador).

async function withAcquisitionDate(page: import('@playwright/test').Page, suffix: string, daysAgo: number) {
    const asset = await registerAsset(page, { tag: tag(suffix) });
    await page.goto(`/activos/${asset.id}/editar`);
    await page.getByLabel('Fecha de adquisición').fill(isoDate(-daysAgo));
    await page.getByTestId('edit-asset-submit').click();
    await expect(page).toHaveURL(new RegExp(`/activos/${asset.id}$`));

    return asset;
}

test.describe('CP-22 · Registrar fecha de baja de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra la fecha de baja y c. el historial la conserva', async ({ page }) => {
            const asset = await withAcquisitionDate(page, '22a', 30);
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('register-event-button').click();
            await page.getByTestId('event-kind').selectOption({ label: 'Baja' });
            await page.getByTestId('event-occurred-on').fill(isoDate(-1));
            await page.getByTestId('event-description').fill('Obsolescencia (prueba)');
            await page.getByTestId('save-event').click();

            await expect(page.getByText(`Se registró la baja del activo ${asset.code}.`).first()).toBeVisible();
            await expect(page.getByTestId('asset-status')).toHaveText('De baja');
            await expect(page.getByTestId('asset-status-since')).toHaveText(shownDate(isoDate(-1)));
            await expect(page.getByTestId('decommissioned-notice')).toContainText(`Este activo fue dado de baja el ${shownDate(isoDate(-1))}. Su información y su historial se conservan, pero ya no se modifican.`);

            await page.getByTestId('asset-history-button').click();
            const entries = page.getByTestId('history-entry');
            await expect(entries.filter({ hasText: 'Registro del activo' })).toHaveCount(1);
            await expect(entries.filter({ hasText: 'Ingreso' })).toHaveCount(1);
            await expect(entries.filter({ hasText: 'Baja' }).first()).toBeVisible();
        });

        test('b. no admite una fecha futura ni anterior a la adquisición', async ({ page }) => {
            const asset = await withAcquisitionDate(page, '22b', 30);
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('register-event-button').click();
            await page.getByTestId('event-kind').selectOption({ label: 'Baja' });
            const date = page.getByTestId('event-occurred-on');
            await page.getByTestId('event-description').fill('Fecha inválida (prueba)');

            expect(await date.getAttribute('max'), 'no deja elegir fechas futuras').toBe(isoDate(0));
            expect(await date.getAttribute('min'), 'no deja elegir fechas anteriores a la adquisición').toBe(isoDate(-30));
            for (const invalid of [isoDate(5), isoDate(-60)]) {
                await date.fill(invalid);
                await page.getByTestId('save-event').click();
                const message = await date.evaluate((el) => (el as HTMLInputElement).validationMessage);
                expect(message, invalid).not.toBe('');
                test.info().annotations.push({ type: `aviso para ${invalid}`, description: message });
            }
            await expect(page.getByTestId('event-row')).toHaveCount(0);
            await expect(page.getByTestId('asset-status')).toHaveText('En servicio');
        });
    });

    test('d. un Visualizador no ve Registrar evento', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto('/activos/1');
        await expect(page.getByTestId('asset-events')).toBeVisible();
        await expect(page.getByTestId('register-event-button')).toHaveCount(0);
    });
});
