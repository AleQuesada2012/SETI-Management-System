import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { createReservation, freeSlot } from '../support/reservations';

// Adaptabilidad (Plan de Calidad): every Reservas screen can be used on a phone 360 px wide, with no sideways scroll of
// the page (wide tables scroll inside their own card). Each test attaches a full-page screenshot as evidence.

test.describe('Adaptabilidad · Reservas a 360 px', () => {
    test.use({ storageState: storageState('reservante'), viewport: { width: 360, height: 780 }, isMobile: true, hasTouch: true });

    let detail = '';

    test.beforeAll(async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reservante') })).newPage();
        detail = await createReservation(page, freeSlot(), 'PRUEBA E2E · 360 px');
    });

    const pages: Array<[string, () => string]> = [
        ['Calendario de reservas', () => '/reservas'],
        ['Reservar espacio', () => '/reservas/nueva'],
        ['Detalle de la reserva', () => detail],
        ['Modificar reserva', () => `${detail}/editar`],
        ['Historial de reservas', () => '/reservas/historial'],
        ['Ocupación de espacios', () => '/reservas/ocupacion'],
        ['Listado de espacios', () => '/espacios'],
        ['Detalle de un espacio (disponibilidad y reservas)', () => '/espacios/1'],
    ];

    for (const [name, path] of pages) {
        test(`${name} no se desborda a lo ancho`, async ({ page }, testInfo) => {
            await page.goto(path());
            await page.waitForLoadState('networkidle');

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            await testInfo.attach(`${name} (360 px)`, { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });

            expect(overflow, `the page is ${overflow}px wider than the screen`).toBeLessThanOrEqual(0);
        });
    }
});
