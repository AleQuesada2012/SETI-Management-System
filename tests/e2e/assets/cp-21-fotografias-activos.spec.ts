import { expect, type Page, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { oversizedPng, pdf, png, svg } from '../support/files';

// CP-21 · Adjuntar fotografías a los activos (RF_21; CU_RF_21). A-0001 (id 1) is in LAB-110, managed by Mario Solís
// (administrador). The cases run in order: a attaches two photos that b and e then look at, and d removes one.
// DEF-11 (#254): every file error is shown twice. That check is marked as an expected failure: it turns red when the
// defect is fixed.

const ASSET = '/activos/1';
const RUN = Date.now().toString(36);
const FIRST = `foto1-${RUN}.png`;
const SECOND = `foto2-${RUN}.png`;

const card = (page: Page) => page.getByTestId('asset-photos');

async function photoCount(page: Page): Promise<number> {
    const heading = await card(page).getByText(/^Fotografías \(\d+\)$/).innerText();

    return Number(heading.match(/\d+/)![0]);
}

test.describe('CP-21 · Adjuntar fotografías a los activos', () => {
    test.describe.configure({ mode: 'serial' });

    test.describe('como encargado (Administrador)', () => {
        test.use({ storageState: storageState('administrador') });

        test('a. agrega dos fotografías y se ven como miniaturas', async ({ page }) => {
            await page.goto(ASSET);
            const before = await photoCount(page);

            await page.getByTestId('asset-photos-input').setInputFiles([png(FIRST), png(SECOND)]);
            await expect(page.getByTestId('asset-photos-chosen')).toHaveText('2 fotografías elegidas');
            await page.getByTestId('asset-photos-upload').click();

            await expect(page.getByText('Se agregaron 2 fotografías.')).toBeVisible();
            await expect(card(page).getByText(`Fotografías (${before + 2})`)).toBeVisible();
            await expect(card(page).getByRole('img', { name: FIRST })).toBeVisible();
            await expect(card(page).getByRole('img', { name: SECOND })).toBeVisible();
        });

        test('c. rechaza un PDF y un SVG por su tipo', async ({ page }) => {
            await page.goto(ASSET);
            const before = await photoCount(page);

            for (const file of [pdf('doc.pdf'), svg('imagen.svg')]) {
                await page.goto(ASSET);
                await page.getByTestId('asset-photos-input').setInputFiles(file);
                await page.getByTestId('asset-photos-upload').click();

                const error = card(page).getByText(/El archivo fotografía debe ser de tipo/).first();
                await expect(error).toBeVisible();
                test.info().annotations.push({ type: file.name, description: await error.innerText() });
            }

            expect(await photoCount(page)).toBe(before);
        });

        test('c. rechaza una imagen de más de 10 MB y dice cuánto puede pesar', async ({ page }) => {
            await page.goto(ASSET);
            const before = await photoCount(page);

            await page.getByTestId('asset-photos-input').setInputFiles(oversizedPng('foto-grande.png'));
            await page.getByTestId('asset-photos-upload').click();

            const error = card(page).getByText(/El archivo fotografía no puede pesar más de 10240 kilobytes\./).first();
            await expect(error).toBeVisible();
            test.info().annotations.push({ type: 'error', description: await error.innerText() });
            expect(await photoCount(page)).toBe(before);
        });

        test('c. el error de un archivo se muestra una sola vez (DEF-11)', async ({ page }) => {
            test.fail(true, 'DEF-11 (#254): las dos tarjetas dibujan el mismo error dos veces.');
            await page.goto(ASSET);

            await page.getByTestId('asset-photos-input').setInputFiles(pdf('doc.pdf'));
            await page.getByTestId('asset-photos-upload').click();

            await expect(card(page).getByText(/El archivo fotografía debe ser de tipo/).first()).toBeVisible();
            await expect(card(page).getByText(/El archivo fotografía debe ser de tipo/)).toHaveCount(1);
        });
    });

    test('b. un Visualizador abre la fotografía completa desde la miniatura', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto(ASSET);

        const link = card(page).getByRole('link').filter({ has: page.getByRole('img', { name: FIRST }) });
        await expect(link).toHaveAttribute('target', '_blank');
        const href = (await link.getAttribute('href'))!;
        expect(href).toMatch(/\/activos\/1\/archivos\/\d+$/);

        const response = await page.request.get(href);
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('image/png');
        test.info().annotations.push({ type: 'respuesta', description: `${response.status()} ${response.headers()['content-type']} · ${href}` });
    });

    for (const role of ['visualizador', 'administrador2'] as const) {
        test(`e. ${role}: ve las fotografías pero no puede agregar ni eliminar`, async ({ browser }) => {
            const page = await (await browser.newContext({ storageState: storageState(role) })).newPage();
            await page.goto(ASSET);

            await expect(card(page).getByRole('img', { name: FIRST })).toBeVisible();
            await expect(page.getByTestId('asset-photos-input')).toHaveCount(0);
            await expect(page.getByTestId('asset-photos-upload')).toHaveCount(0);
            await expect(card(page).getByRole('button', { name: /Eliminar/ })).toHaveCount(0);
        });
    }

    test.describe('d. eliminar', () => {
        test.use({ storageState: storageState('administrador') });

        test('d. elimina una fotografía: el diálogo muestra la miniatura', async ({ page }) => {
            await page.goto(ASSET);
            const before = await photoCount(page);

            await card(page).getByRole('button', { name: `Eliminar ${FIRST}` }).click();
            const dialog = page.getByRole('dialog');
            await expect(dialog).toContainText('¿Eliminar esta fotografía?');
            await expect(dialog).toContainText(`Se eliminará «${FIRST}» del activo.`);
            await expect(dialog.getByRole('img', { name: FIRST })).toBeVisible();
            await page.getByTestId('asset-photo-confirm-remove').click();

            await expect(page.getByText(`Se eliminó la fotografía «${FIRST}».`)).toBeVisible();
            await expect(card(page).getByText(`Fotografías (${before - 1})`)).toBeVisible();
            await expect(card(page).getByRole('img', { name: FIRST })).toHaveCount(0);
            await expect(card(page).getByRole('img', { name: SECOND })).toBeVisible();
        });
    });
});
