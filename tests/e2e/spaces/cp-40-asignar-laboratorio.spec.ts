import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { ADMIN_USER, RESERVANTE_USER, SPACES, assignSpaces, cleanUp, openUser } from '../support/managers';

// CP-40 · Asignar laboratorio a usuarios administradores (RF_40; CU_RF_40), peer review by Diego Zamora. The case assigns
// LAB-204 and LAB-REDES to "Administrador Prueba SETI"; here Lucía Campos (Administrador) gets SAL-01 and OF-SETI, and
// the test retires them at the end so the demo data stays as it was.

test.describe('CP-40 · Asignar laboratorio a usuarios administradores', () => {
    test.describe.configure({ mode: 'serial' });

    test.describe('como Superusuario', () => {
        test.use({ storageState: storageState('superusuario') });

        test.afterAll(async ({ browser }) => {
            await cleanUp(await (await browser.newContext({ storageState: storageState('superusuario') })).newPage());
        });

        test('a. asigna uno o varios espacios y b. la ventana ya no ofrece los asignados ni acepta una selección vacía', async ({ page }) => {
            await openUser(page, ADMIN_USER);
            await assignSpaces(page, SPACES);

            await expect(page.getByText(`Se asignaron ${SPACES.join(', ')} a ${ADMIN_USER.name}.`).first()).toBeVisible();
            for (const code of SPACES) {
                await expect(page.getByRole('listitem').filter({ hasText: code })).toContainText(/Sala|Oficina/);
            }

            await page.goto('/espacios');
            await page.getByRole('row').filter({ hasText: 'SAL-01' }).getByRole('link').first().click();
            await expect(page.getByText('Encargados del espacio').or(page.getByText('Encargados')).first()).toBeVisible();
            await expect(page.getByText(ADMIN_USER.name).first()).toBeVisible();

            // b.
            await openUser(page, ADMIN_USER);
            await page.getByRole('button', { name: 'Asignar espacios' }).click();
            const dialog = page.getByRole('dialog');
            for (const code of SPACES) {
                await expect(dialog.getByRole('checkbox', { name: new RegExp(code) })).toHaveCount(0);
            }
            await dialog.getByRole('button', { name: 'Asignar', exact: true }).click();
            await expect(dialog.getByText('El campo espacios es obligatorio.')).toBeVisible();
        });

        test('c. solo se asignan espacios a Administradores', async ({ page }) => {
            await openUser(page, RESERVANTE_USER);
            await expect(page.getByText('Espacios a cargo')).toHaveCount(0);
            await expect(page.getByRole('button', { name: 'Asignar espacios' })).toHaveCount(0);
        });
    });

    test('d. un Administrador ve los espacios sin controles y /usuarios/encargados responde Acceso denegado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await openUser(page, ADMIN_USER);
        await expect(page.getByText('Espacios a cargo')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Asignar espacios' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Retirar' })).toHaveCount(0);
        await expect(page.getByRole('checkbox')).toHaveCount(0);

        const response = await page.goto('/usuarios/encargados');
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
