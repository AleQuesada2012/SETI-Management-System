import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDay, pick, dates } from '../support/qa';
import { previewRows } from '../support/reports';

// CP-00-3 · Auditar creaciones (RF_00_3, CU_RF_00_3).

test.describe('CP-00-3 · Auditar creaciones', () => {
    test('a. la creación del espacio PRUEBA-AUD queda con fecha, identificador y usuario', async ({ browser }) => {
        const superuser = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await superuser.goto('/espacios');
        await superuser.getByTestId('create-space-button').click();
        const form = superuser.getByRole('dialog');
        await form.getByLabel('Código').fill('PRUEBA-AUD');
        await form.getByLabel('Nombre').fill('Espacio de prueba de auditoría');
        await form.getByLabel('Tipo de espacio').selectOption({ label: 'Sala' });
        await form.getByLabel('Edificio').selectOption({ index: 1 });
        await form.getByLabel('Capacidad').fill('5');
        await superuser.getByTestId('create-space-submit').click();
        await expect(superuser).toHaveURL(/\/espacios\/\d+$/);
        await expect(superuser.getByRole('heading', { name: 'PRUEBA-AUD · Espacio de prueba de auditoría' })).toBeVisible();

        const reports = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await reports.goto('/reportes/audit');
        await pick(reports, 'Tipo de operación', 'Creó');
        await dates(reports, isoDay(), isoDay());
        const row = previewRows(reports).filter({ hasText: 'PRUEBA-AUD' }).first();
        await expect(row).toBeVisible();
        await expect(row).toContainText('Sofía Vargas');
        await expect(row).toContainText('Creó');
        await expect(row).toContainText(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
        test.info().annotations.push({ type: 'fila', description: await row.innerText() });
    });

    test('b. la invitación de un usuario nuevo queda con su identificador y el Superusuario que lo creó', async ({ browser }) => {
        const superuser = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await superuser.goto('/usuarios');
        await superuser.getByRole('button', { name: 'Añadir usuario' }).click();
        const dialog = superuser.getByRole('dialog');
        await dialog.getByLabel('Nombre').fill('Ivana Invitada QA');
        await dialog.getByLabel('Correo').fill('invitada.qa@seti.test');
        await dialog.getByRole('checkbox', { name: 'Reservante' }).check();
        await dialog.getByRole('button', { name: 'Registrar' }).click();
        await expect(superuser.getByText('Se registró a Ivana Invitada QA y se envió la invitación a invitada.qa@seti.test.')).toBeVisible();

        const reports = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await reports.goto('/reportes/audit');
        await pick(reports, 'Tipo de operación', 'Creó');
        await dates(reports, isoDay(), isoDay());
        const row = previewRows(reports).filter({ hasText: 'invitada.qa@seti.test' }).first();
        await expect(row).toBeVisible();
        await expect(row).toContainText('Sofía Vargas');
        test.info().annotations.push({ type: 'fila', description: await row.innerText() });
    });
});
