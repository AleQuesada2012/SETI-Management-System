import { expect, type Page, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { maintenanceRows, validationMessage } from '../support/maintenance';

// CP-27 · Programar mantenimiento de activos (RF_27, CU_RF_27). A-0005 and A-0006 sit in LAB-110, managed by
// Mario Solís; A-0017 (projector) has a scheduled preventive maintenance in the demo data.

async function schedule(page: Page, assetId: number, date: string, description: string): Promise<void> {
    await page.goto(`/activos/${assetId}`);
    await page.getByTestId('schedule-maintenance-button').click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByTestId('scheduled-kind').selectOption({ label: 'Preventivo' });
    await page.getByTestId('scheduled-for').fill(date);
    await page.getByTestId('scheduled-description').fill(description);
    await page.getByTestId('save-scheduled').click();
}

test.describe('CP-27 · Programar mantenimiento de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. programa un mantenimiento y se consulta en "Mantenimientos programados"', async ({ page }) => {
            const date = isoDate(14);
            await schedule(page, 5, date, 'Calibración (prueba E2E CP-27a).');

            await expect(page.getByText(`Se programó el mantenimiento del activo A-0005 para el ${shownDate(date)}.`)).toBeVisible();
            await expect(maintenanceRows(page).filter({ hasText: 'prueba E2E CP-27a' })).toContainText('Programado');

            await page.goto('/mantenimientos');
            const row = page.getByRole('row').filter({ hasText: 'prueba E2E CP-27a' });
            await expect(row).toContainText(shownDate(date));
            await expect(row).toContainText('A-0005');
            await expect(row).toContainText('LAB-110 · Laboratorio 110');
        });

        test('b. modifica la fecha de un mantenimiento programado', async ({ page }) => {
            const date = isoDate(20);
            await page.goto('/activos/17');
            await page.getByTestId('edit-scheduled-button').click();
            await expect(page.getByRole('dialog')).toBeVisible();
            await page.getByTestId('scheduled-for').fill(date);
            await page.getByTestId('save-scheduled').click();

            await expect(page.getByText('Se modificó el mantenimiento programado del activo A-0017.')).toBeVisible();
            await expect(maintenanceRows(page).first()).toContainText(shownDate(date));
        });

        test('c. cancela un mantenimiento programado', async ({ page }) => {
            await schedule(page, 6, isoDate(9), 'Revisión a cancelar (prueba E2E CP-27c).');
            await expect(page.getByText(/Se programó el mantenimiento del activo A-0006/)).toBeVisible();

            const row = maintenanceRows(page).filter({ hasText: 'prueba E2E CP-27c' });
            await row.getByTestId('cancel-scheduled-button').click();
            await page.getByTestId('confirm-cancel').click();

            await expect(page.getByText('Se canceló el mantenimiento programado del activo A-0006.')).toBeVisible();
            await expect(row).toContainText('Cancelado');
            await page.goto('/mantenimientos');
            await expect(page.getByRole('row').filter({ hasText: 'prueba E2E CP-27c' })).toHaveCount(0);
        });

        test('d. no deja programar para hoy o una fecha pasada', async ({ page }) => {
            await schedule(page, 5, isoDate(), 'Fecha de hoy (prueba E2E CP-27d).');

            expect(await validationMessage(page.getByTestId('scheduled-for'))).not.toBe('');
            await expect(page.getByRole('dialog')).toBeVisible();
        });
    });

    test('e. un Visualizador consulta los programados pero no puede programar', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();

        await page.goto('/mantenimientos');
        await expect(page.getByRole('heading', { name: 'Mantenimientos programados', level: 2 })).toBeVisible();

        await page.goto('/activos/17');
        await expect(page.getByTestId('schedule-maintenance-button')).toHaveCount(0);
        await expect(page.getByTestId('edit-scheduled-button')).toHaveCount(0);
    });
});
