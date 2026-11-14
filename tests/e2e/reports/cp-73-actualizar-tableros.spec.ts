import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';

// CP-73 c · Aviso cuando los datos no se pueden actualizar (RF_73). Local only, and only when the stack runs with
// POWERBI_FEED_PASSWORD, POWERBI_FEED_STALE_AFTER_HOURS=0 and a POWERBI_EMBED_URL (the warning sits under the embedded
// report, so without an embed URL the page only says it is not configured). Pass the same password in
// E2E_POWERBI_FEED_PASSWORD; the test is skipped otherwise. a, b and d need Power BI Service or the site.

const password = process.env.E2E_POWERBI_FEED_PASSWORD;

test.describe('CP-73 · Actualizar información de los tableros', () => {
    test.skip(!password, 'needs the stack started with the Power BI feed settings and E2E_POWERBI_FEED_PASSWORD');

    test('c. avisa que los datos nunca se actualizaron y, después de una lectura vencida, desde cuándo', async ({ browser, request, baseURL }) => {
        const page = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await page.goto('/reportes/tableros');
        await expect(page.getByText('Los datos del tablero todavía no se han actualizado con la información del sistema.')).toBeVisible();

        const wrong = await request.get(`${baseURL}/powerbi/assets.csv`, { headers: { Authorization: `Basic ${Buffer.from('powerbi:incorrecta').toString('base64')}` } });
        expect(wrong.status()).toBe(401);
        await page.reload();
        await expect(page.getByText('Los datos del tablero todavía no se han actualizado con la información del sistema.')).toBeVisible();

        const feed = await request.get(`${baseURL}/powerbi/assets.csv`, { headers: { Authorization: `Basic ${Buffer.from(`powerbi:${password}`).toString('base64')}` } });
        expect(feed.status()).toBe(200);
        const csv = await feed.text();
        test.info().annotations.push({ type: 'assets.csv', description: `${feed.headers()['content-type']} · ${csv.split('\n').length - 1} filas · ${csv.split('\n')[0]}` });

        await page.waitForTimeout(61_000);
        await page.reload();
        const stale = page.getByText(/Los datos del tablero no se han podido actualizar desde el \d{2}\/\d{2}\/\d{4} \d{2}:\d{2}, así que pueden estar desactualizados\. Comunícate con el Superusuario del sistema\./).first();
        await expect(stale).toBeVisible();
        test.info().annotations.push({ type: 'aviso', description: await stale.innerText() });
    });
});
