import { expect, test } from '@playwright/test';
import { PASSWORD, USERS } from '../support/accounts';
import { latestMail, MAILPIT_URL, QA_USERS, signIn, totp, TWO_FACTOR_SECRET } from '../support/qa';

// CP-00-1 · Inicio de sesión (RF_00_1, CU_RF_00_1). Each test signs in through the form with a fresh browser.

test.describe('CP-00-1 · Inicio de sesión', () => {
    test('a. inicia sesión con rol Reportes y el menú muestra solo sus opciones', async ({ browser }) => {
        const page = await signIn(browser, USERS.reportes.email);

        await expect(page).toHaveURL(/\/tablero$/);
        const menu = page.getByRole('navigation').first();
        await expect(menu.getByRole('link', { name: 'Reportes' })).toBeVisible();
        await expect(menu.getByRole('link', { name: 'Usuarios y roles' })).toHaveCount(0);
        await expect(menu.getByRole('link', { name: 'Tableros de Power BI' })).toHaveCount(0);
    });

    test('b. una contraseña incorrecta muestra el error y no inicia la sesión', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill(USERS.visualizador.email);
        await page.getByLabel('Contraseña', { exact: true }).fill('123123');
        await page.getByRole('button', { name: 'Ingresar al sistema' }).click();

        await expect(page.getByText('El correo electrónico o la contraseña no son correctos.')).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
    });

    test('c. con campos vacíos indica el campo requerido y no envía el formulario', async ({ page }) => {
        await page.goto('/login');
        const requests: string[] = [];
        page.on('request', (request) => request.method() === 'POST' && requests.push(request.url()));
        await page.getByRole('button', { name: 'Ingresar al sistema' }).click();

        const email = page.getByLabel('Correo electrónico');
        expect(await email.evaluate((input: HTMLInputElement) => input.validity.valueMissing)).toBe(true);
        expect(await email.evaluate((input: HTMLInputElement) => input.validationMessage)).not.toBe('');

        await email.fill(USERS.visualizador.email);
        await page.getByRole('button', { name: 'Ingresar al sistema' }).click();
        const password = page.getByLabel('Contraseña', { exact: true });
        expect(await password.evaluate((input: HTMLInputElement) => input.validity.valueMissing)).toBe(true);

        expect(requests).toEqual([]);
        await expect(page).toHaveURL(/\/login$/);
    });

    test('d. con autenticación en dos pasos entra con el código correcto y no con uno incorrecto', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill(QA_USERS.dosfactores);
        await page.getByLabel('Contraseña', { exact: true }).fill(PASSWORD);
        await page.getByRole('button', { name: 'Ingresar al sistema' }).click();
        await expect(page).toHaveURL(/two-factor-challenge/);
        await expect(page.getByText('Código de autenticación').first()).toBeVisible();

        // Wrong code first: an error, and the challenge stays.
        await page.locator('[data-flux-otp] input, input[name="code"]').first().click();
        await page.keyboard.type('000000');
        await page.getByRole('button', { name: 'Continuar' }).click();
        await expect(page).toHaveURL(/two-factor-challenge/);
        await expect(page.getByText(/código.*(no es válido|incorrecto|inválido)|invalid/i).first()).toBeVisible();

        // The code of the authenticator app.
        await page.locator('[data-flux-otp] input, input[name="code"]').first().click();
        await page.keyboard.type(totp(TWO_FACTOR_SECRET));
        await page.getByRole('button', { name: 'Continuar' }).click();
        await expect(page).toHaveURL(/\/tablero$/);
    });

    test('e. desde el sexto intento fallido en un minuto pide esperar', async ({ page }) => {
        await page.goto('/login');
        for (let attempt = 1; attempt <= 6; attempt++) {
            await page.getByLabel('Correo electrónico').fill(QA_USERS.bloqueo);
            await page.getByLabel('Contraseña', { exact: true }).fill(`incorrecta-${attempt}`);
            await page.getByRole('button', { name: 'Ingresar al sistema' }).click();
            await page.waitForLoadState('networkidle');
            if (attempt <= 5) {
                await expect(page.getByText('El correo electrónico o la contraseña no son correctos.')).toBeVisible();
            }
        }
        // The sixth attempt is refused with "too many attempts". (Observed: a full-page 429 "Demasiadas solicitudes"
        // instead of the login form with auth.throttle's "Intenta de nuevo en N segundos".)
        const tooMany = page.getByText(/Demasiados intentos de inicio de sesión|Demasiadas solicitudes/).first();
        await expect(tooMany).toBeVisible();
        test.info().annotations.push({ type: 'observado', description: await page.locator('body').innerText() });

        // Even the right password is refused while the lock lasts.
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill(QA_USERS.bloqueo);
        await page.getByLabel('Contraseña', { exact: true }).fill(PASSWORD);
        await page.getByRole('button', { name: 'Ingresar al sistema' }).click();
        await expect(page.getByText(/Demasiados intentos de inicio de sesión|Demasiadas solicitudes/).first()).toBeVisible();
        await expect(page).not.toHaveURL(/\/tablero/);
    });

    test('f. recupera la contraseña con el enlace del correo e ingresa con la nueva', async ({ page, browser }) => {
        await fetch(`${MAILPIT_URL}/api/v1/messages`, { method: 'DELETE' });
        await page.goto('/login');
        await page.getByRole('link', { name: '¿Olvidaste tu contraseña?' }).click();
        await expect(page).toHaveURL(/forgot-password/);
        await expect(page.getByRole('heading', { name: 'Olvidé mi contraseña' })).toBeVisible();
        await page.locator('input[name="email"]').fill(QA_USERS.recupera);
        await page.getByTestId('email-password-reset-link-button').click();
        await expect(page.locator('body')).toContainText(/enlace|correo/i);

        const mail = await latestMail(QA_USERS.recupera);
        const link = mail.match(/href="([^"]*reset-password[^"]*)"/)?.[1]?.replace(/&amp;/g, '&');
        expect(link, 'reset link in the email').toBeTruthy();

        await page.goto(link!);
        await page.locator('input[name="password"]').fill('NuevaClave-2026!');
        await page.locator('input[name="password_confirmation"]').fill('NuevaClave-2026!');
        await page.getByTestId('reset-password-button').click();
        await expect(page).toHaveURL(/\/login/);

        // signIn() fails if the form stays on /login; a Visualizador lands on Activos.
        const signedIn = await signIn(browser, QA_USERS.recupera, 'NuevaClave-2026!');
        await expect(signedIn.getByRole('button', { name: /Rosa Recupera/ })).toBeVisible();
    });

    test('g. una cuenta desactivada no puede ingresar', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill('inactivo@seti.test');
        await page.getByLabel('Contraseña', { exact: true }).fill(PASSWORD);
        await page.getByRole('button', { name: 'Ingresar al sistema' }).click();

        await expect(page.getByText('Tu cuenta está desactivada. Comunícate con el Superusuario del sistema.')).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
    });
});
