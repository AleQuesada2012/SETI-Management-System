import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { DEMO_ASSET, registerAsset, tag } from '../support/assets';

// CP-01 · Consultar información de activo (RF_01; CU_RF_01), peer review by Diego Zamora. David's case names LAB-101 and
// PRUEBA-1001 on the deployed site; here the equivalents are LAB-110 and A-0001, and the asset of test b is registered
// by the test itself (PRUEBA-DZ-01 in the case).

test.describe('CP-01 · Consultar información de activo', () => {
    test('a. un Visualizador consulta un activo existente y ve todas sus tarjetas', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await page.goto('/activos');
        await page.getByRole('link', { name: DEMO_ASSET.code, exact: true }).click();

        await expect(page).toHaveURL(new RegExp(`/activos/${DEMO_ASSET.id}$`));
        await expect(page.getByText('Detalle del activo').first()).toBeVisible();
        const cards = ['Identificación', 'Estado', 'Ubicación y custodia', 'Adquisición', 'Garantía', 'Observaciones', 'Componentes', 'Fotografías', 'Documentos', 'Eventos', 'Mantenimientos', 'Movimientos'];
        const headings = await page.getByRole('heading').allInnerTexts();
        for (const card of cards) {
            expect(headings.some((h) => h.startsWith(card)), `la tarjeta "${card}"`).toBe(true);
        }
        test.info().annotations.push({ type: 'tarjetas', description: headings.join(' | ') });
    });

    test('b. la ficha muestra lo último que se guardó en la base de datos', async ({ browser }) => {
        const admin = await (await browser.newContext({ storageState: storageState('administrador') })).newPage();
        const viewer = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const asset = await registerAsset(admin, { tag: tag('01'), model: 'Modelo inicial' });

        await viewer.goto(`/activos/${asset.id}`);
        await expect(viewer.getByText('Modelo inicial', { exact: true })).toBeVisible();

        await admin.goto(`/activos/${asset.id}/editar`);
        await admin.getByLabel('Modelo').fill('Latitude 5420');
        await admin.getByTestId('edit-asset-submit').click();
        await expect(admin.getByText(/Los cambios del activo A-\d+ se guardaron correctamente\./).first()).toBeVisible();

        await viewer.goto(`/activos/${asset.id}`);
        await expect(viewer.getByText('Latitude 5420', { exact: true })).toBeVisible();
        await expect(viewer.getByText('Modelo inicial', { exact: true })).toHaveCount(0);
    });

    test('c. un activo que no existe dice que no existe', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        const response = await page.goto('/activos/999999');

        expect(response?.status()).toBe(404);
        await expect(page.getByText('Página no encontrada').first()).toBeVisible();
        await expect(page.getByText('La página o el registro que buscas no existe o fue eliminado.')).toBeVisible();
    });

    test('d. un Reservante no ve Activos en el menú y la ficha responde Acceso denegado', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        await page.goto('/reservas');
        await expect(page.getByRole('link', { name: 'Activos', exact: true })).toHaveCount(0);

        const response = await page.goto(`/activos/${DEMO_ASSET.id}`);
        expect(response?.status()).toBe(403);
        await expect(page.getByText('Acceso denegado').first()).toBeVisible();
    });
});
