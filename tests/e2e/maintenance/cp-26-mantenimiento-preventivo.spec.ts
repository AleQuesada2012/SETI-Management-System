import { expect, test } from '@playwright/test';
import { storageState, USERS } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { maintenanceRows, registerMaintenance, validationMessage } from '../support/maintenance';

// CP-26 · Registrar mantenimiento preventivo (RF_26, CU_RF_26). A-0004 sits in LAB-110, managed by Mario Solís.

test.describe('CP-26 · Registrar mantenimiento preventivo', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra un mantenimiento preventivo con fecha y descripción', async ({ page }) => {
            await page.goto('/activos/4');
            await page.getByTestId('register-maintenance-button').click();
            await expect(page.getByTestId('maintenance-kind')).toHaveValue('corrective');
            await page.getByTestId('maintenance-kind').selectOption({ label: 'Preventivo' });
            await expect(page.getByTestId('maintenance-kind-help')).toContainText('Preventivo: una revisión periódica para evitar fallas.');
            await page.getByRole('dialog').getByRole('button', { name: 'Cancelar' }).click();

            await registerMaintenance(page, {
                kind: 'Preventivo',
                date: isoDate(-1),
                performedBy: 'Soporte Técnico SETI',
                description: 'Revisión semestral (prueba E2E CP-26a).',
            });

            await expect(page.getByText('Se registró el mantenimiento del activo A-0004.')).toBeVisible();
            const row = maintenanceRows(page).filter({ hasText: 'prueba E2E CP-26a' });
            await expect(row).toContainText(shownDate(isoDate(-1)));
            await expect(row).toContainText('Preventivo');
            await expect(row).toContainText(USERS.administrador.name);
        });

        test('b. el mantenimiento preventivo queda en el historial del activo', async ({ page }) => {
            await page.goto('/activos/4');
            await registerMaintenance(page, {
                kind: 'Preventivo',
                date: isoDate(),
                performedBy: 'Soporte Técnico SETI',
                description: 'Limpieza de polvo (prueba E2E CP-26b).',
            });
            await expect(page.getByText('Se registró el mantenimiento del activo A-0004.')).toBeVisible();

            await page.goto('/activos/4/historial');
            await expect(page.getByText('Limpieza de polvo (prueba E2E CP-26b).')).toBeVisible();
        });

        test('c. no deja registrar sin descripción', async ({ page }) => {
            await page.goto('/activos/4');
            await registerMaintenance(page, { kind: 'Preventivo', date: isoDate(), performedBy: 'Soporte Técnico SETI', description: '' });

            expect(await validationMessage(page.getByTestId('maintenance-description'))).not.toBe('');
            await expect(page.getByRole('dialog')).toBeVisible();
        });
    });

    test('d. un Visualizador no puede registrar mantenimientos', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto('/activos/4');

        await expect(page.getByTestId('asset-maintenance')).toBeVisible();
        await expect(page.getByTestId('register-maintenance-button')).toHaveCount(0);
    });
});
