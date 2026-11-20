import { expect, type Page } from '@playwright/test';

/*
 * CP-40 and CP-43 use "Administrador Prueba SETI", LAB-204 and LAB-REDES on the deployed site. In the local demo data the
 * Administrador is Lucía Campos (administrador2, id 3: LAB-316, LAB-322 and LAIMI), and the spaces nobody else's are
 * SAL-01 and OF-SETI (OF-SETI has no manager at all). Both specs leave Lucía with her original spaces.
 */
export const ADMIN_USER = { id: 3, name: 'Lucía Campos' };
export const RESERVANTE_USER = { id: 6, name: 'Esteban Castro' };
export const SPACES = ['OF-SETI', 'SAL-01'];
export const SPACE_LABELS = { 'SAL-01': 'SAL-01 · Sala de reuniones', 'OF-SETI': 'OF-SETI · Oficina SETI' };

export async function openUser(page: Page, user: { id: number; name: string }): Promise<void> {
    await page.goto(`/usuarios/${user.id}`);
    await expect(page.getByRole('heading', { name: user.name }).first()).toBeVisible();
}

export async function assignSpaces(page: Page, codes: string[]): Promise<void> {
    await page.getByRole('button', { name: 'Asignar espacios' }).click();
    for (const code of codes) {
        await page.getByRole('dialog').getByRole('checkbox', { name: new RegExp(code) }).check();
    }
    await page.getByRole('dialog').getByRole('button', { name: 'Asignar', exact: true }).click();
}

/** Removes every space the test assigned (leaves the ones Lucía has by seed). */
export async function cleanUp(page: Page): Promise<void> {
    await openUser(page, ADMIN_USER);
    for (const code of SPACES) {
        const row = page.getByRole('listitem').filter({ hasText: code });
        if ((await row.count()) > 0) {
            await row.getByRole('button', { name: 'Retirar' }).click();
            await page.getByRole('button', { name: 'Sí, retirar' }).click();
            await expect(page.getByRole('listitem').filter({ hasText: code })).toHaveCount(0);
        }
    }
}
