import { expect, type Page, test } from '@playwright/test';
import { storageState } from '../support/accounts';

// CP-58 · Cambiar estado de un espacio (RF_58, duplicate of RF_44; CU_RF_58). SAL-01 (id 6) is managed by
// Mario Solís (administrador) and has one upcoming reservation in the demo data.

/** Picks a status in "Cambiar estado". It asks for confirmation only when upcoming reservations are affected. */
async function changeStatus(page: Page, status: string): Promise<void> {
    await page.getByTestId('change-status-button').click();
    await page.getByRole('menuitem', { name: status }).click();
}

test.describe('CP-58 · Cambiar estado de un espacio', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. cambia el estado y se refleja en el detalle y en el listado', async ({ page }) => {
            await page.goto('/espacios/6');
            await changeStatus(page, 'Inhabilitado');
            await expect(page.getByRole('dialog')).toContainText('¿Cambiar el estado a Inhabilitado?');
            await expect(page.getByTestId('status-warning')).toContainText('Este espacio tiene 1 reserva confirmada que aún no termina.');
            await page.getByTestId('confirm-status').click();

            await expect(page.getByText('El estado de Sala de reuniones ahora es Inhabilitado.')).toBeVisible();
            await expect(page.getByTestId('space-status')).toHaveText('Inhabilitado');

            await page.goto('/espacios');
            await expect(page.getByRole('row').filter({ hasText: 'SAL-01' })).toContainText('Inhabilitado');

            // Back to "Disponible": no reservation is affected, so it applies without asking.
            await page.goto('/espacios/6');
            await changeStatus(page, 'Disponible');
            await expect(page.getByText('El estado de Sala de reuniones ahora es Disponible.')).toBeVisible();
            await expect(page.getByTestId('space-status')).toHaveText('Disponible');
        });

        test('b. cancelar el diálogo no cambia el estado', async ({ page }) => {
            await page.goto('/espacios/6');
            await changeStatus(page, 'En mantenimiento');
            await expect(page.getByRole('dialog')).toContainText('¿Cambiar el estado a En mantenimiento?');
            await page.getByRole('dialog').getByRole('button', { name: 'Cancelar' }).click();

            await expect(page.getByRole('dialog')).toBeHidden();
            await expect(page.getByTestId('space-status')).toHaveText('Disponible');
        });
    });

    test('c. un Administrador que no es encargado del espacio no puede cambiar su estado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('administrador2') })).newPage();
        await page.goto('/espacios/6');

        await expect(page.getByRole('heading', { name: 'SAL-01 · Sala de reuniones' })).toBeVisible();
        await expect(page.getByTestId('change-status-button')).toHaveCount(0);
    });

    test('d. un Visualizador no ve la opción de cambiar estado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto('/espacios/6');

        await expect(page.getByRole('heading', { name: 'SAL-01 · Sala de reuniones' })).toBeVisible();
        await expect(page.getByTestId('change-status-button')).toHaveCount(0);
    });
});
