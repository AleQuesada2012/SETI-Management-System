import { expect, test } from '@playwright/test';
import { storageState, USERS } from '../support/accounts';
import { isoDate } from '../support/dates';
import { maintenanceRows, registerDamage } from '../support/maintenance';

// CP-11 · Registrar reparaciones de activos (RF_11, CU_RF_11). A-0028 (air conditioner, LAB-312) is damaged and under
// repair in the demo data; A-0002 (LAB-110) starts in good condition.

test.describe('CP-11 · Registrar reparaciones de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra la reparación en curso de un activo dañado', async ({ page }) => {
            await page.goto('/activos/2');
            await registerDamage(page, isoDate(), 'Pantalla no enciende (prueba E2E CP-11a).');

            await page.getByTestId('register-repair-button').click();
            // The form opens on the latest damage of the asset.
            await expect(page.getByTestId('repair-damage-select')).toHaveValue(/^\d+$/);
            await expect(page.getByTestId('repair-damage-select').locator('option:checked')).toContainText('Pantalla no enciende');
            await page.getByTestId('repair-performed-on').fill(isoDate());
            await page.getByTestId('repair-performed-by').fill('Soporte Técnico SETI');
            await page.getByTestId('repair-description').fill('Diagnóstico de la tarjeta madre (prueba E2E CP-11a).');
            await page.getByTestId('save-repair').click();

            await expect(page.getByText('Se registró la reparación en curso: el activo A-0002 quedó en mantenimiento.')).toBeVisible();
            const row = maintenanceRows(page).filter({ hasText: 'prueba E2E CP-11a' });
            await expect(row).toContainText('En curso');
            await expect(row).toContainText(USERS.administrador.name);
            await expect(row.getByTestId('repair-damage')).toBeVisible();
        });

        test('b. al finalizar la reparación el activo vuelve a estar en funcionamiento', async ({ page }) => {
            await page.goto('/activos/28');
            await page.getByTestId('complete-repair-button').click();
            await expect(page.getByRole('dialog')).toBeVisible();
            await page.getByTestId('repair-completed-on').fill(isoDate());
            await page.getByTestId('complete-condition').selectOption({ label: 'Buen estado' });
            await page.getByTestId('save-complete').click();

            await expect(page.getByText('Se finalizó la reparación: el activo A-0028 volvió a estar en servicio.')).toBeVisible();
            const repair = maintenanceRows(page).filter({ hasText: 'Revisión y recarga de refrigerante.' });
            await expect(repair).not.toContainText('En curso');
            await expect(repair.getByTestId('complete-repair-button')).toHaveCount(0);
            await expect(page.locator('dt:text-is("Condición") + dd')).toHaveText('Buen estado');
            await expect(page.locator('dt:text-is("Estado") + dd').first()).toHaveText('En servicio');
        });

        test('c. un activo sin daño no ofrece "Registrar reparación"', async ({ page }) => {
            await page.goto('/activos/4');

            await expect(page.getByTestId('register-maintenance-button')).toBeVisible();
            await expect(page.getByTestId('register-repair-button')).toHaveCount(0);
        });
    });

    test('d. un Visualizador ve las reparaciones pero no puede registrarlas', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto('/activos/28');

        await expect(maintenanceRows(page).first()).toBeVisible();
        await expect(page.getByTestId('register-repair-button')).toHaveCount(0);
        await expect(page.getByTestId('complete-repair-button')).toHaveCount(0);
    });
});
