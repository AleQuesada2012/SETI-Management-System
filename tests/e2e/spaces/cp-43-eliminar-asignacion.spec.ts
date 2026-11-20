import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { ADMIN_USER, SPACES, assignSpaces, cleanUp, openUser } from '../support/managers';

// CP-43 · Eliminar asignación de laboratorio a usuarios administradores (RF_43; CU_RF_43), peer review by Diego Zamora. The
// case retires LAB-REDES and LAB-204 of "Administrador Prueba SETI"; here Lucía Campos (Administrador) is assigned SAL-01 and
// OF-SETI by the test and gets them retired, and keeps her original spaces (LAB-316, LAB-322, LAIMI).

test.describe('CP-43 · Eliminar asignación de laboratorio a usuarios administradores', () => {
    test.describe.configure({ mode: 'serial' });

    test.describe('como Superusuario', () => {
        test.use({ storageState: storageState('superusuario') });

        test.beforeAll(async ({ browser }) => {
            const page = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
            await cleanUp(page);
            await openUser(page, ADMIN_USER);
            await assignSpaces(page, SPACES);
            await expect(page.getByText(`Se asignaron ${SPACES.join(', ')} a ${ADMIN_USER.name}.`).first()).toBeVisible();
        });

        test.afterAll(async ({ browser }) => {
            await cleanUp(await (await browser.newContext({ storageState: storageState('superusuario') })).newPage());
        });

        test('a. retira una asignación con confirmación y b. varias a la vez', async ({ page }) => {
            await openUser(page, ADMIN_USER);

            // a. cancel first, then confirm
            await page.getByRole('listitem').filter({ hasText: 'OF-SETI' }).getByRole('button', { name: 'Retirar' }).click();
            const dialog = page.getByRole('dialog');
            await expect(dialog).toContainText('¿Retirar estas asignaciones?');
            await expect(dialog).toContainText('OF-SETI · Oficina SETI');
            await expect(dialog).toContainText(`${ADMIN_USER.name} dejará de estar a cargo de estos espacios`);
            await dialog.getByRole('button', { name: 'Cancelar' }).click();
            await expect(page.getByRole('listitem').filter({ hasText: 'OF-SETI' })).toHaveCount(1);

            await page.getByRole('listitem').filter({ hasText: 'OF-SETI' }).getByRole('button', { name: 'Retirar' }).click();
            await page.getByRole('button', { name: 'Sí, retirar' }).click();
            await expect(page.getByText(`Se retiró OF-SETI de ${ADMIN_USER.name}.`).first()).toBeVisible();
            await expect(page.getByRole('listitem').filter({ hasText: 'OF-SETI' })).toHaveCount(0);

            // b. several at once: the two spaces of the test (the other one is still assigned) with the ones she has by seed left
            await page.getByRole('button', { name: 'Asignar espacios' }).click();
            await page.getByRole('dialog').getByRole('checkbox', { name: /OF-SETI/ }).check();
            await page.getByRole('dialog').getByRole('button', { name: 'Asignar', exact: true }).click();
            await expect(page.getByRole('listitem').filter({ hasText: 'OF-SETI' })).toHaveCount(1);
            for (const code of SPACES) {
                await page.getByLabel(`Elegir ${code} para retirar`).check();
            }
            await page.getByRole('button', { name: 'Retirar seleccionados' }).click();
            await page.getByRole('button', { name: 'Sí, retirar' }).click();
            await expect(page.getByText(`Se retiraron ${SPACES.join(', ')} de ${ADMIN_USER.name}.`).first()).toBeVisible();
            for (const code of SPACES) {
                await expect(page.getByRole('listitem').filter({ hasText: code })).toHaveCount(0);
            }
        });

        test('c. sin espacios elegidos se avisa y sin asignaciones se notifica', async ({ page }) => {
            await openUser(page, ADMIN_USER);
            await page.getByRole('button', { name: 'Retirar seleccionados' }).click();
            await expect(page.getByText('Elige al menos un espacio para retirar.')).toBeVisible();
            await expect(page.getByRole('dialog')).toHaveCount(0);

            // Lucía has no spare Administrador account to empty, so her demo spaces are retired and given back at the end.
            const seeded = ['LAB-316', 'LAB-322', 'LAIMI'];
            try {
                const boxes = page.getByRole('checkbox');
                for (let i = (await boxes.count()) - 1; i >= 0; i--) {
                    await boxes.nth(i).check();
                }
                await page.getByRole('button', { name: 'Retirar seleccionados' }).click();
                await page.getByRole('button', { name: 'Sí, retirar' }).click();
                await expect(page.getByText(`${ADMIN_USER.name} no tiene espacios asignados; no hay asignaciones que retirar.`)).toBeVisible();
            } finally {
                await openUser(page, ADMIN_USER);
                await assignSpaces(page, seeded);
                await expect(page.getByText(/^Se asignaron .+ a Lucía Campos\.$/).first()).toBeVisible();
            }
        });
    });

    test('d. un Administrador ve los espacios sin casillas ni botones para retirar', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        await openUser(page, ADMIN_USER);
        await expect(page.getByRole('listitem').filter({ hasText: 'LAB-316' })).toBeVisible();
        await expect(page.getByRole('checkbox')).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Retirar' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Retirar seleccionados' })).toHaveCount(0);
    });
});
