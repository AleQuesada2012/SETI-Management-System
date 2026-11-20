import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { registerAsset, tag } from '../support/assets';
import { png } from '../support/files';

// CP-13 · Registrar daños de activos (RF_13; CU_RF_13), peer review by Diego Zamora. The assets of the case (PRUEBA-DZ-05 in
// service, PRUEBA-DZ-06 lost) are registered by the tests in LAB-110, managed by Mario Solís (administrador).

const DOCX = {
    name: 'evidencia.docx',
    mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    buffer: Buffer.from('no es un documento de Word'),
};

test.describe('CP-13 · Registrar daños de activos', () => {
    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. registra un daño con evidencia; b. un formato no permitido y c. sin descripción se rechazan', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('13a') });
            await page.goto(`/activos/${asset.id}`);

            // a. valid damage with a photo as evidence
            await page.getByTestId('register-event-button').click();
            await page.getByTestId('event-description').fill('Pantalla rota (prueba)');
            await page.getByTestId('event-evidence-input').setInputFiles(png('dano.png'));
            await expect(page.getByTestId('event-evidence-chosen')).toContainText('1 archivo');
            await page.getByTestId('save-event').click();

            await expect(page.getByText(`Se registró el daño del activo ${asset.code}.`).first()).toBeVisible();
            const row = page.getByTestId('event-row');
            await expect(row).toContainText('Daño');
            await expect(row.getByTestId('event-evidence').getByRole('link', { name: 'dano.png' })).toBeVisible();
            await expect(page.getByTestId('asset-condition')).toHaveText('Dañado');
            await expect(page.getByTestId('asset-status')).toHaveText('En servicio');

            // b. a .docx is not valid evidence (a second choice adds to the first, so the form is reopened for each try)
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('register-event-button').click();
            await page.getByTestId('event-description').fill('Segundo daño (prueba)');
            await page.getByTestId('event-evidence-input').setInputFiles(DOCX);
            await page.getByTestId('save-event').click();
            const error = page.getByText(/El archivo evidencia debe ser de tipo/).first();
            await expect(error).toBeVisible();
            test.info().annotations.push({ type: 'error de la evidencia', description: await error.innerText() });
            test.info().annotations.push({ type: 'observación', description: `el aviso se repite ${await page.getByText(/El archivo evidencia debe ser de tipo/).count()} veces y nombra tipos MIME` });
            await expect(row).toHaveCount(1);

            // c. without description
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('register-event-button').click();
            await page.getByTestId('save-event').click();
            await expect(row).toHaveCount(1);
            const message = await page.getByTestId('event-description').evaluate((el) => (el as HTMLTextAreaElement).validationMessage);
            expect(message, 'la descripción es obligatoria').not.toBe('');
            test.info().annotations.push({ type: 'aviso del navegador', description: message });
        });

        test('d. un activo perdido no ofrece "Daño"', async ({ page }) => {
            const asset = await registerAsset(page, { tag: tag('13d') });
            await page.goto(`/activos/${asset.id}`);
            await page.getByTestId('register-event-button').click();
            await page.getByTestId('event-kind').selectOption({ label: 'Pérdida' });
            await page.getByTestId('event-description').fill('Se perdió en el traslado (prueba)');
            await page.getByTestId('save-event').click();
            await expect(page.getByText(`Se registró la pérdida del activo ${asset.code}.`).first()).toBeVisible();

            await page.getByTestId('register-event-button').click();
            await expect(page.getByTestId('event-kind')).toBeVisible();
            const kinds = await page.getByTestId('event-kind').locator('option').allInnerTexts();
            expect(kinds, 'la lista ya cargó').toContain('Recuperación');
            expect(kinds).not.toContain('Daño');
            test.info().annotations.push({ type: 'tipos de evento del activo perdido', description: kinds.join(', ') });
        });
    });

    test('e. un Visualizador ve el daño pero no puede registrar eventos', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        const asset = await registerAsset(admin, { tag: tag('13e') });
        await admin.goto(`/activos/${asset.id}`);
        await admin.getByTestId('register-event-button').click();
        await admin.getByTestId('event-description').fill('Pantalla rota (prueba)');
        await admin.getByTestId('save-event').click();
        await expect(admin.getByTestId('event-row')).toHaveCount(1);

        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(`/activos/${asset.id}`);
        await expect(page.getByTestId('event-row')).toContainText('Pantalla rota (prueba)');
        await expect(page.getByTestId('register-event-button')).toHaveCount(0);
    });
});
