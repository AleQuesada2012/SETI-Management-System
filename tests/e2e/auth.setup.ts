import { expect, test as setup } from '@playwright/test';
import { PASSWORD, type Role, storageState, USERS } from './support/accounts';

/*
 * Signs in once per role and saves the session, so the cases do not sign in again and again: the site locks an
 * account after 5 attempts a minute (RF_00_1).
 */
for (const role of Object.keys(USERS) as Role[]) {
    setup(`sign in as ${role}`, async ({ page }) => {
        await page.goto('/login');
        await page.locator('input[name="email"]').fill(USERS[role].email);
        await page.locator('input[name="password"]').fill(PASSWORD);
        await page.getByTestId('login-button').click();

        await expect(page).not.toHaveURL(/\/login/);
        await page.context().storageState({ path: storageState(role) });
    });
}
