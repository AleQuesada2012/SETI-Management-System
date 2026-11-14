import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDay, livewire } from '../support/qa';

// CP-80 · Generar estadísticas de estado de activos (RF_80, CU_RF_80).

const count = (page: import('@playwright/test').Page, table: string, group: string) =>
    page.getByRole('table', { name: table }).getByRole('row').filter({ has: page.getByRole('cell', { name: group, exact: true }) }).getByRole('cell').nth(1);

test.describe('CP-80 · Generar estadísticas de estado de activos', () => {
    test('a. total y distribución por condición y por estado del ciclo de vida, con ceros', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/reportes');
        await page.getByRole('link', { name: 'Estadísticas de activos' }).click();
        await expect(page.getByText(/^Total de activos: \d+$/)).toBeVisible();
        for (const [table, groups] of [
            ['Condición física', ['Buen estado (Verde)', 'Regular (Ámbar)', 'Dañado (Rojo)']],
            ['Estado del ciclo de vida', ['En servicio', 'En mantenimiento', 'Perdido', 'De baja']],
        ] as const) {
            await expect(page.getByRole('img', { name: table })).toBeVisible();
            for (const group of groups) {
                await expect(count(page, table, group)).toHaveText(/^\d+$/);
            }
        }
        test.info().annotations.push({ type: 'tablas', description: (await page.getByRole('table').allInnerTexts()).join('\n---\n') });
    });

    test('b. las cantidades cambian sin recargar al registrar un daño en otra pestaña', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/reportes/estadisticas');
        const before = Number(await count(page, 'Condición física', 'Dañado (Rojo)').innerText());
        let navigations = 0;
        page.on('framenavigated', (frame) => frame === page.mainFrame() && navigations++);

        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await admin.goto('/activos/8');
        await admin.getByTestId('register-event-button').click();
        const dialog = admin.getByRole('dialog');
        await livewire(admin, () => dialog.getByTestId('event-kind').selectOption({ label: 'Daño' }));
        await dialog.getByLabel('Fecha').fill(isoDay());
        await dialog.getByLabel('Descripción').fill('Pantalla rota (prueba QA)');
        await dialog.getByTestId('event-condition').selectOption({ label: 'Dañado' }).catch(() => dialog.getByTestId('event-condition').selectOption({ index: 0 }));
        await dialog.getByRole('button', { name: 'Registrar' }).click();
        await expect(admin.getByText(/Se registró el daño del activo/)).toBeVisible();

        await expect(count(page, 'Condición física', 'Dañado (Rojo)')).toHaveText(String(before + 1), { timeout: 20_000 });
        expect(navigations).toBe(0);
    });
});
