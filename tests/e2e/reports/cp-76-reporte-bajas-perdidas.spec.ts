import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { dates, isoDay, livewire, pick, previewColumn } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-76 · Generar reporte de activos dados de baja o perdidos (RF_76, CU_RF_76). qa-data.php: a decommission (5 days
// ago) and a loss (2 days ago) in LAB-322. The test registers the loss and recovery of asset 22 (LAB-312).

async function registerEvent(page: import('@playwright/test').Page, kind: string, description: string): Promise<void> {
    await page.getByTestId('register-event-button').click();
    const dialog = page.getByRole('dialog');
    await livewire(page, () => dialog.getByTestId('event-kind').selectOption({ label: kind }));
    await dialog.getByLabel('Fecha').fill(isoDay());
    await dialog.getByLabel('Descripción').fill(description);
    await dialog.getByRole('button', { name: 'Registrar' }).click();
    await expect(page.getByText(/Se registró la (pérdida|recuperación) del activo/)).toBeVisible();
}

test.describe('CP-76 · Generar reporte de activos dados de baja o perdidos', () => {
    test('a. lista bajas y pérdidas con sus datos y el estado actual del activo', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Activos', 'disposals');
        for (const column of ['Código de activo', 'Activo', 'Tipo de evento', 'Fecha del evento', 'Descripción', 'Registrado por', 'Estado actual del activo']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        const kinds = await previewColumn(page, 'Tipo de evento');
        expect(kinds).toContain('Baja');
        expect(kinds).toContain('Pérdida');
    });

    test('b. una pérdida recuperada sigue en el reporte con el estado "En servicio"', async ({ browser }) => {
        const superuser = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await superuser.goto('/activos/22');
        const code = (await superuser.getByRole('heading', { level: 2 }).first().innerText()).split(' · ')[0];
        await registerEvent(superuser, 'Pérdida', 'Pérdida de prueba QA');
        await registerEvent(superuser, 'Recuperación', 'Apareció en bodega (prueba QA)');

        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Activos', 'disposals');
        const row = previewRows(page).filter({ hasText: 'Pérdida de prueba QA' });
        await expect(row).toHaveCount(1);
        await expect(row).toContainText(code);
        await expect(row).toContainText('En servicio');
    });

    test('c. filtra solo bajas dentro de un rango de fechas', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Activos', 'disposals');
        await pick(page, 'Tipo de evento', 'Baja');
        await dates(page, isoDay(-7), isoDay(-3));
        expect(await previewColumn(page, 'Tipo de evento')).toEqual(['Baja']);
        await dates(page, isoDay(-2), isoDay());
        await expect(page.getByText('No hay registros que cumplan los filtros.')).toBeVisible();
    });
});
