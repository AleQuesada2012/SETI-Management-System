import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { dates, isoDay, pick, previewColumn, QA_USERS, signIn } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-68 · Generar reporte de auditoría (RF_68, CU_RF_68). Mario Solís renames A-0007 (LAB-110) twice.

async function rename(page: import('@playwright/test').Page, name: string): Promise<void> {
    await page.goto('/activos/7/editar');
    await page.getByLabel('Nombre', { exact: true }).fill(name);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page).toHaveURL(/\/activos\/7$/);
}

test.describe('CP-68 · Generar reporte de auditoría', () => {
    test('a. muestra usuario, fecha y hora, operación, elemento y valores anteriores y nuevos', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Auditoría', 'audit');
        for (const column of ['Usuario registrado', 'Fecha y hora', 'Operación', 'Elemento registrado', 'Valores anteriores', 'Valores nuevos']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
    });

    test('c y b. cada fila conserva el nombre de ese momento; el filtro usuario + Editó + fechas', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await rename(admin, 'Equipo QA nombre uno');
        await rename(admin, 'Equipo QA nombre dos');

        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Auditoría', 'audit');
        await pick(page, 'Usuario', 'Mario Solís');
        await pick(page, 'Tipo de operación', 'Editó');
        await dates(page, isoDay(), isoDay());

        // b. Only Mario's edits of today.
        expect(new Set(await previewColumn(page, 'Usuario registrado'))).toEqual(new Set(['Mario Solís']));
        expect(new Set(await previewColumn(page, 'Operación'))).toEqual(new Set(['Editó']));

        // c. Both renames, each with the name of that moment.
        const rows = previewRows(page).filter({ hasText: 'A-0007' });
        const texts = await rows.allInnerTexts();
        test.info().annotations.push({ type: 'filas de A-0007', description: texts.join('\n') });
        expect(texts.length).toBeGreaterThanOrEqual(2);
        expect(texts.some((t) => t.includes('Equipo QA nombre uno') && !t.includes('Equipo QA nombre dos'))).toBe(true);
        expect(texts.some((t) => t.includes('Equipo QA nombre dos'))).toBe(true);
    });

    test('d. el rol sin permiso de auditoría recibe "Acceso denegado" (403)', async ({ browser }) => {
        const page = await signIn(browser, QA_USERS.consulta);
        const response = await page.goto('/reportes/audit');
        expect(response?.status()).toBe(403);
        await expect(page.getByRole('heading', { name: 'Acceso denegado' })).toBeVisible();
    });
});
