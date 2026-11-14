import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { QA_USERS, signIn } from '../support/qa';

// CP-60 · Entrar al menú de reportes (RF_60, CU_RF_60).

test.describe('CP-60 · Entrar al menú de reportes', () => {
    test('a. el menú de reportes muestra las categorías Activos, Espacios, Auditoría y Usuarios', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/tablero');
        await page.getByRole('navigation').first().getByRole('link', { name: 'Reportes' }).click();
        await expect(page).toHaveURL(/\/reportes$/);
        const options = await page.getByLabel('Categoría').locator('option').allInnerTexts();
        expect(options.map((o) => o.trim())).toEqual(['Activos', 'Espacios', 'Auditoría', 'Usuarios']);
    });

    test('b. la categoría Activos lista sus reportes', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/reportes');
        await page.getByLabel('Categoría').selectOption({ label: 'Activos' });
        for (const report of ['Inventario de activos', 'Mantenimientos y reparaciones', 'Movimientos de inventario', 'Garantías', 'Activos dados de baja o perdidos', 'Componentes de equipos', 'Responsables de activos']) {
            await expect(page.getByText(report, { exact: true })).toBeVisible();
        }
    });

    test('c. una categoría sin reportes disponibles para el rol muestra el mensaje informativo', async ({ browser }) => {
        // Custom role "Consulta de reportes" (reports.view, no reports.audit), created by qa-data.php: the system has no
        // screen to create custom roles.
        const page = await signIn(browser, QA_USERS.consulta);
        await page.goto('/reportes');
        const options = (await page.getByLabel('Categoría').locator('option').allInnerTexts()).map((o) => o.trim());
        test.info().annotations.push({ type: 'categorías del rol sin auditoría', description: options.join(', ') });
        if (options.includes('Auditoría')) {
            await page.getByLabel('Categoría').selectOption({ label: 'Auditoría' });
            await expect(page.getByText('No hay reportes disponibles para esta categoría.')).toBeVisible();
        } else {
            // The category is not offered at all: also no access to the audit report.
            expect(options).not.toContain('Auditoría');
        }
    });

    test('d. el Visualizador no ve Reportes y la dirección responde "Acceso denegado" (403)', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto('/tablero');
        await expect(page.getByRole('navigation').first().getByRole('link', { name: 'Reportes' })).toHaveCount(0);
        const response = await page.goto('/reportes');
        expect(response?.status()).toBe(403);
        await expect(page.getByRole('heading', { name: 'Acceso denegado' })).toBeVisible();
    });
});
