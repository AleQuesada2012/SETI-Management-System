import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { pick, previewColumn, settle, type } from '../support/qa';
import { openReport, previewRows } from '../support/reports';

// CP-69 · Generar reporte de usuarios (RF_69, CU_RF_69).

test.describe('CP-69 · Generar reporte de usuarios', () => {
    test('a. muestra nombre, correo, roles, estado e invitación pendiente, activos y desactivados', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Usuarios', 'users');
        for (const column of ['Nombre', 'Correo', 'Roles', 'Estado de cuenta', 'Invitación pendiente']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        const status = await previewColumn(page, 'Estado de cuenta');
        expect(status).toContain('Activa');
        expect(status).toContain('Desactivada'); // Jorge Ramírez (inactivo@seti.test)
        await expect(previewRows(page).filter({ hasText: 'invitada@seti.test' })).toContainText('Sí');
    });

    test('b. filtra por rol Reservante y estado Activa', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Usuarios', 'users');
        await pick(page, 'Rol', 'Reservante');
        await pick(page, 'Estado de cuenta', 'Activa');
        const roles = await previewColumn(page, 'Roles');
        expect(roles.length).toBeGreaterThan(0);
        for (const r of roles) {
            expect(r).toContain('Reservante');
        }
        expect(new Set(await previewColumn(page, 'Estado de cuenta'))).toEqual(new Set(['Activa']));
    });

    test('c. una cuenta desactivada por el Superusuario aparece como desactivada', async ({ browser }) => {
        const superuser = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await superuser.goto('/usuarios');
        await superuser.getByRole('link', { name: /Carlos Cambios/ }).first().click();
        await superuser.getByRole('button', { name: 'Desactivar cuenta' }).click();
        await superuser.getByRole('button', { name: 'Sí, desactivar' }).click();
        await expect(superuser.getByText('Se desactivó la cuenta de Carlos Cambios.')).toBeVisible();

        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Usuarios', 'users');
        await type(page, 'Buscar', 'cambios@seti.test');
        expect(await previewColumn(page, 'Estado de cuenta')).toEqual(['Desactivada']);
    });
});
