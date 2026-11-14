import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDay, pick, psql, QA_USERS, dates } from '../support/qa';
import { previewRows } from '../support/reports';

// CP-00-2 · Auditar cambios (RF_00_2, CU_RF_00_2). Mario Solís (Administrador) manages LAB-110, where A-0001 is.

test.describe('CP-00-2 · Auditar cambios', () => {
    test('a. la edición de un activo queda en la auditoría con usuario, fecha y valores anteriores y nuevos', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await admin.goto('/activos/1/editar');
        await admin.getByLabel('Observaciones').fill('Revisión QA del 00-2');
        await admin.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(admin).toHaveURL(/\/activos\/1$/);
        await expect(admin.getByText('Revisión QA del 00-2')).toBeVisible();

        const reports = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await reports.goto('/reportes/audit');
        await pick(reports, 'Usuario', 'Mario Solís');
        await pick(reports, 'Tipo de operación', 'Editó');
        await dates(reports, isoDay(), isoDay());

        const row = previewRows(reports).filter({ hasText: 'Revisión QA del 00-2' }).first();
        await expect(row).toBeVisible();
        await expect(row).toContainText('Mario Solís');
        await expect(row).toContainText('Editó');
        await expect(row).toContainText('A-0001');
        await expect(row).toContainText(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
        test.info().annotations.push({ type: 'fila', description: await row.innerText() });
    });

    test('b. el cambio de rol queda con el usuario que lo hizo y la fecha y hora', async ({ browser }) => {
        const superuser = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await superuser.goto('/usuarios');
        await superuser.getByRole('link', { name: /Carlos Cambios/ }).first().click();
        await superuser.getByRole('button', { name: 'Cambiar' }).first().click();
        await superuser.getByLabel('Nuevo rol').selectOption({ label: 'Visualizador' });
        await superuser.getByRole('button', { name: 'Cambiar rol' }).click();
        await expect(superuser.getByText('Se cambió el rol Reservante por Visualizador a Carlos Cambios.')).toBeVisible();

        const reports = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await reports.goto('/reportes/audit');
        await pick(reports, 'Usuario', 'Sofía Vargas');
        await dates(reports, isoDay(), isoDay());
        const rows = previewRows(reports).filter({ hasText: 'Carlos Cambios' });
        await expect(rows.first()).toBeVisible();
        const texts = await rows.allInnerTexts();
        test.info().annotations.push({ type: 'filas', description: texts.join('\n') });
        expect(texts.join('\n')).toMatch(/Visualizador/);
        expect(texts.join('\n')).toMatch(/Reservante/);
        expect(texts.join('\n')).toMatch(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
    });

    test('c. la base de datos rechaza UPDATE y DELETE sobre audit_logs', async () => {
        const before = psql('SELECT count(*) FROM audit_logs;').output;
        const update = psql("UPDATE audit_logs SET action = 'x' WHERE id = (SELECT min(id) FROM audit_logs);");
        const remove = psql('DELETE FROM audit_logs WHERE id = (SELECT min(id) FROM audit_logs);');
        const after = psql('SELECT count(*) FROM audit_logs;').output;

        test.info().annotations.push({ type: 'UPDATE', description: update.output.trim() }, { type: 'DELETE', description: remove.output.trim() });
        expect(update.ok).toBe(false);
        expect(remove.ok).toBe(false);
        expect(after).toBe(before);
    });
});
