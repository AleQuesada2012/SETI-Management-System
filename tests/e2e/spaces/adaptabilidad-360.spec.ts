import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';

// Adaptabilidad (Plan de Calidad): every Espacios screen, and the forms that open over them, can be used on a phone 360 px
// wide, with no sideways scroll of the page (wide tables scroll inside their own card). Each test attaches a full-page
// screenshot as evidence. LAB-110 (id 1) is managed by Mario Solís (administrador).

test.describe('Adaptabilidad · Gestión de Espacios a 360 px', () => {
    test.use({ storageState: storageState('administrador'), viewport: { width: 360, height: 780 }, isMobile: true, hasTouch: true });

    const pages: Array<[string, string]> = [
        ['Listado de espacios', '/espacios'],
        ['Detalle de un espacio', '/espacios/1'],
        ['Historial de un espacio', '/espacios/1/historial'],
        ['Calendario de reservas', '/reservas'],
    ];

    for (const [name, path] of pages) {
        test(`${name} no se desborda a lo ancho`, async ({ page }, testInfo) => {
            await page.goto(path);
            await page.waitForLoadState('networkidle');

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            await testInfo.attach(`${name} (360 px)`, { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });

            expect(overflow, `the page is ${overflow}px wider than the screen`).toBeLessThanOrEqual(0);
        });
    }

    test('las tablas anchas se desplazan dentro de su tarjeta, no la página', async ({ page }) => {
        await page.goto('/espacios');
        await page.waitForLoadState('networkidle');

        const scroller = await page.evaluate(() => {
            const el = [...document.querySelectorAll('*')].find((e) => ['auto', 'scroll'].includes(getComputedStyle(e).overflowX) && e.scrollWidth > e.clientWidth + 1);

            return el ? { scrollWidth: el.scrollWidth, clientWidth: el.clientWidth } : null;
        });

        expect(scroller, 'la tabla del listado desborda su contenedor y se desplaza dentro de él').not.toBeNull();
        test.info().annotations.push({ type: 'tabla del listado', description: `${scroller!.scrollWidth} px de contenido en ${scroller!.clientWidth} px de ancho` });
    });

    test('los botones de acción del detalle pasan a otra línea', async ({ page }, testInfo) => {
        await page.goto('/espacios/1');
        const buttons = ['space-history-link', 'change-status-button', 'edit-space-button', 'delete-space-button'].map((id) => page.getByTestId(id));
        const boxes = [];
        for (const button of buttons) {
            await expect(button).toBeVisible();
            boxes.push((await button.boundingBox())!);
        }

        for (const box of boxes) {
            expect(box.x + box.width, 'el botón cabe en la pantalla').toBeLessThanOrEqual(360);
        }
        await testInfo.attach('Detalle de un espacio: botones (360 px)', { body: await page.screenshot(), contentType: 'image/png' });
    });

    for (const [name, open] of [
        ['Añadir espacio', async (page: import('@playwright/test').Page) => { await page.goto('/espacios'); await page.getByTestId('create-space-button').click(); }],
        ['Editar espacio', async (page: import('@playwright/test').Page) => { await page.goto('/espacios/1'); await page.getByTestId('edit-space-button').click(); }],
    ] as const) {
        test(`el formulario "${name}" cabe en la pantalla`, async ({ page }, testInfo) => {
            await open(page);
            const dialog = page.getByRole('dialog');
            await expect(dialog).toContainText(name);

            const box = (await dialog.boundingBox())!;
            expect(box.x, 'el diálogo empieza dentro de la pantalla').toBeGreaterThanOrEqual(0);
            expect(box.x + box.width, 'el diálogo termina dentro de la pantalla').toBeLessThanOrEqual(360);
            expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
            await testInfo.attach(`${name} (360 px)`, { body: await page.screenshot(), contentType: 'image/png' });
        });
    }
});
