import { expect, test } from '@playwright/test';
import { storageState, USERS } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { maintenanceCard, maintenanceRows, registerMaintenance, validationMessage } from '../support/maintenance';

// CP-10 · Registrar mantenimiento de activos (RF_10, CU_RF_10). A-0003 sits in LAB-110, managed by Mario Solís.

test.describe('CP-10 · Registrar mantenimiento de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra un mantenimiento con fecha, tipo, descripción y responsable', async ({ page }) => {
            await page.goto('/activos/3');
            await registerMaintenance(page, {
                kind: 'Correctivo',
                date: isoDate(),
                performedBy: 'Soporte Técnico SETI',
                description: 'Cambio de fuente de poder (prueba E2E CP-10a).',
            });

            await expect(page.getByText('Se registró el mantenimiento del activo A-0003.')).toBeVisible();
            const row = maintenanceRows(page).filter({ hasText: 'prueba E2E CP-10a' });
            await expect(row).toContainText(shownDate(isoDate()));
            await expect(row).toContainText('Correctivo');
            await expect(row).toContainText('Soporte Técnico SETI');
            await expect(row).toContainText(USERS.administrador.name);
        });

        test('b. el mantenimiento se consulta en el historial del activo', async ({ page }) => {
            await page.goto('/activos/3');
            await registerMaintenance(page, {
                kind: 'Correctivo',
                date: isoDate(),
                performedBy: 'Soporte Técnico SETI',
                description: 'Ajuste de ventiladores (prueba E2E CP-10b).',
            });
            await expect(page.getByText('Se registró el mantenimiento del activo A-0003.')).toBeVisible();

            await page.goto('/activos/3/historial');
            await expect(page.getByText('Ajuste de ventiladores (prueba E2E CP-10b).')).toBeVisible();
        });

        test('c. no deja registrar un mantenimiento con fecha futura', async ({ page }) => {
            await page.goto('/activos/3');
            await registerMaintenance(page, {
                kind: 'Correctivo',
                date: isoDate(1),
                performedBy: 'Soporte Técnico SETI',
                description: 'Fecha futura (prueba E2E CP-10c).',
            });

            expect(await validationMessage(page.getByTestId('maintenance-performed-on'))).not.toBe('');
            await expect(page.getByRole('dialog')).toBeVisible();
            await expect(maintenanceRows(page).filter({ hasText: 'prueba E2E CP-10c' })).toHaveCount(0);
        });
    });

    test('d. el Superusuario también registra mantenimientos', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await page.goto('/activos/3');
        await registerMaintenance(page, {
            kind: 'Correctivo',
            date: isoDate(),
            performedBy: 'Soporte Técnico SETI',
            description: 'Limpieza interna (prueba E2E CP-10d).',
        });

        await expect(maintenanceRows(page).filter({ hasText: 'prueba E2E CP-10d' })).toContainText(USERS.superusuario.name);
    });

    test('e. un Administrador que no es encargado del espacio y un Visualizador no pueden registrar', async ({ browser }) => {
        for (const role of ['administrador2', 'visualizador'] as const) {
            const page = await (await browser.newContext({ storageState: storageState(role) })).newPage();
            await page.goto('/activos/3');

            await expect(maintenanceCard(page)).toBeVisible();
            await expect(page.getByTestId('register-maintenance-button')).toHaveCount(0);
        }
    });

    test('f. un rol sin acceso a los activos recibe "Acceso denegado"', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        const response = await page.goto('/activos/3');

        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado')).toBeVisible();
    });
});
