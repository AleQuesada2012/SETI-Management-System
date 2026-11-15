import { expect, type Page, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate, shownDate } from '../support/dates';
import { oversizedPdf, pdf, text } from '../support/files';

// CP-20 · Adjuntar documentos a los activos (RF_20; CU_RF_20). A-0001 (id 1) is in LAB-110, managed by Mario Solís
// (administrador). The cases run in order: a attaches a PDF that b, c and e then look at, and d removes it.
// DEF-11 (#254): every file error is shown twice, and a PDF over 20 MB gets a technical message with no size. Those two
// checks are marked as expected failures: they turn red when the defect is fixed.

const ASSET = '/activos/1';
const FILE = `factura-${Date.now().toString(36)}.pdf`;

const card = (page: Page) => page.getByTestId('asset-documents');

async function documentCount(page: Page): Promise<number> {
    const heading = await card(page).getByText(/^Documentos \(\d+\)$/).innerText();

    return Number(heading.match(/\d+/)![0]);
}

test.describe('CP-20 · Adjuntar documentos a los activos', () => {
    test.describe.configure({ mode: 'serial' });

    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. adjunta un PDF y queda en la lista del activo', async ({ page }) => {
            await page.goto(ASSET);
            const before = await documentCount(page);

            await page.getByTestId('asset-documents-input').setInputFiles(pdf(FILE));
            await expect(page.getByTestId('asset-documents-chosen')).toHaveText('1 documento elegido');
            await page.getByTestId('asset-documents-upload').click();

            await expect(page.getByText('Se adjuntó 1 documento.')).toBeVisible();
            await expect(card(page).getByText(`Documentos (${before + 1})`)).toBeVisible();

            const row = card(page).getByRole('listitem').filter({ hasText: FILE });
            await expect(row).toContainText(`${shownDate(isoDate())} · Mario Solís`);
            await expect(row).toContainText(/\d+ B|\d+ KB/);
            test.info().annotations.push({ type: 'fila', description: (await row.innerText()).replace(/\s+/g, ' ') });
        });

        test('c. rechaza un .txt: error en español y el documento no se adjunta', async ({ page }) => {
            await page.goto(ASSET);
            const before = await documentCount(page);

            await page.getByTestId('asset-documents-input').setInputFiles(text('nota.txt'));
            await page.getByTestId('asset-documents-upload').click();

            const error = card(page).getByText(/El archivo documento debe ser de tipo/).first();
            await expect(error).toBeVisible();
            test.info().annotations.push({ type: 'error', description: await error.innerText() });
            expect(await documentCount(page)).toBe(before);
        });

        test('c. el error de un archivo se muestra una sola vez (DEF-11)', async ({ page }) => {
            test.fail(true, 'DEF-11 (#254): las dos tarjetas dibujan el mismo error dos veces.');
            await page.goto(ASSET);

            await page.getByTestId('asset-documents-input').setInputFiles(text('nota.txt'));
            await page.getByTestId('asset-documents-upload').click();

            await expect(card(page).getByText(/El archivo documento debe ser de tipo/).first()).toBeVisible();
            await expect(card(page).getByText(/El archivo documento debe ser de tipo/)).toHaveCount(1);
        });

        test('c. un PDF de más de 20 MB dice cuánto puede pesar (DEF-11)', async ({ page }) => {
            test.fail(true, 'DEF-11 (#254): PHP lo rechaza antes de la validación y el aviso es "No se pudo subir el archivo documents.0.".');
            await page.goto(ASSET);

            await page.getByTestId('asset-documents-input').setInputFiles(oversizedPdf('pdf-grande.pdf'));
            await page.getByTestId('asset-documents-upload').click();

            await expect(card(page).getByText(/no puede pesar más de/).first()).toBeVisible();
        });
    });

    test('b. un Visualizador abre el documento desde el activo, sin la ruta interna', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(ASSET);

        const link = card(page).getByRole('link', { name: FILE });
        await expect(link).toHaveAttribute('target', '_blank');
        const href = (await link.getAttribute('href'))!;
        expect(href).toMatch(/\/activos\/1\/archivos\/\d+$/);

        const response = await page.request.get(href);
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('application/pdf');
        expect(response.headers()['content-disposition']).toContain('inline');
        test.info().annotations.push({ type: 'respuesta', description: `${response.status()} ${response.headers()['content-type']} · ${response.headers()['content-disposition']} · ${href}` });
    });

    for (const role of ['visualizador', 'administrador2'] as const) {
        test(`e. ${role}: ve los documentos pero no puede adjuntar ni eliminar`, async ({ browser }) => {
            const page = await (await browser.newContext({ storageState: storageState(role) })).newPage();
            await page.goto(ASSET);

            await expect(card(page).getByRole('link', { name: FILE })).toBeVisible();
            await expect(page.getByTestId('asset-documents-input')).toHaveCount(0);
            await expect(page.getByTestId('asset-documents-upload')).toHaveCount(0);
            await expect(card(page).getByRole('button', { name: /Eliminar/ })).toHaveCount(0);
        });
    }

    test.describe('d. eliminar', () => {
        test.use({ storageState: storageState('administrador') });

        test('d. elimina el documento con confirmación', async ({ page }) => {
            await page.goto(ASSET);
            const before = await documentCount(page);

            await card(page).getByRole('button', { name: `Eliminar ${FILE}` }).click();
            const dialog = page.getByRole('dialog');
            await expect(dialog).toContainText('¿Eliminar este documento?');
            await expect(dialog).toContainText(`Se eliminará «${FILE}» del activo.`);
            await page.getByTestId('asset-document-confirm-remove').click();

            await expect(page.getByText(`Se eliminó el documento «${FILE}».`)).toBeVisible();
            await expect(card(page).getByText(`Documentos (${before - 1})`)).toBeVisible();
            await expect(card(page).getByRole('link', { name: FILE })).toHaveCount(0);
        });
    });
});
