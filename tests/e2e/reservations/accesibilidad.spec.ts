import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { freeSlot, LAB_110 } from '../support/reservations';

// Accesibilidad (WCAG 2.2, level A) of the Reservas screens: what a browser can check without an extra tool. The
// language and title of each page (3.1.1, 2.4.2), a name for every control and image (1.1.1, 4.1.2, 3.3.2), a visible
// focus (2.4.7, AA, checked as a courtesy) and booking with the keyboard alone (2.1.1).

const screens: Array<[string, string]> = [
    ['Calendario de reservas', '/reservas'],
    ['Reservar espacio', '/reservas/nueva'],
    ['Historial de reservas', '/reservas/historial'],
    ['Ocupación de espacios', '/reservas/ocupacion'],
    ['Listado de espacios', '/espacios'],
    ['Detalle de un espacio', `/espacios/${LAB_110.id}`],
];

test.describe('Accesibilidad · Reservas', () => {
    test.use({ storageState: storageState('reservante') });

    for (const [name, path] of screens) {
        test(`${name}: idioma, título y nombre accesible de cada control e imagen`, async ({ page }) => {
            await page.goto(path);
            await page.waitForLoadState('networkidle');

            await expect(page.locator('html')).toHaveAttribute('lang', 'es');
            await expect(page).toHaveTitle(/\S/);

            const unnamed = await page.evaluate(() => {
                const visible = (el: Element): boolean => {
                    const box = (el as HTMLElement).getBoundingClientRect();
                    return box.width > 0 && box.height > 0 && getComputedStyle(el).visibility !== 'hidden';
                };
                const named = (el: Element): boolean => {
                    const html = el as HTMLInputElement;
                    return Boolean(
                        el.getAttribute('aria-label')?.trim() ||
                            el.getAttribute('aria-labelledby') ||
                            el.getAttribute('title')?.trim() ||
                            (html.labels && html.labels.length > 0) ||
                            el.textContent?.trim(),
                    );
                };

                const controls = [...document.querySelectorAll('input:not([type=hidden]), select, textarea, button, a[href]')]
                    .filter(visible)
                    .filter((el) => !named(el))
                    .map((el) => el.outerHTML.slice(0, 120));
                const images = [...document.querySelectorAll('img')]
                    .filter((img) => !img.hasAttribute('alt'))
                    .map((img) => img.outerHTML.slice(0, 120));

                return [...controls, ...images];
            });

            expect(unnamed, 'controls or images without an accessible name').toEqual([]);
        });
    }

    test('Reservar espacio solo con el teclado', async ({ page }) => {
        const slot = freeSlot();
        await page.goto('/reservas/nueva');

        /** Presses Tab until the control has the focus (a date input takes several stops: day, month and year). */
        const tabTo = async (target: ReturnType<typeof page.getByLabel>): Promise<void> => {
            for (let presses = 0; presses < 6; presses++) {
                if (await target.evaluate((el) => el === document.activeElement)) {
                    return;
                }
                await page.keyboard.press('Tab');
            }
            await expect(target).toBeFocused();
        };

        await page.getByTestId('reservation-space').focus();
        await page.keyboard.press('ArrowDown');
        await expect(page.getByText(/Capacidad: \d+ personas/)).toBeVisible();

        await tabTo(page.getByLabel('Fecha'));
        const [year, month, day] = slot.date.split('-');
        await page.keyboard.type(`${month}${day}${year}`);
        await expect(page.getByLabel('Fecha')).toHaveValue(slot.date);

        await tabTo(page.getByTestId('reservation-start'));
        await page.keyboard.type(slot.start);
        await expect(page.getByTestId('reservation-start')).toHaveValue(slot.start);

        await tabTo(page.getByTestId('reservation-end'));
        await page.keyboard.type(slot.end);
        await expect(page.getByTestId('reservation-end')).toHaveValue(slot.end);

        await tabTo(page.getByLabel('Motivo'));
        await page.keyboard.type('PRUEBA E2E · teclado');
        await tabTo(page.getByLabel('Cantidad de personas'));
        await page.keyboard.type('4');
        await page.keyboard.press('Enter');

        await expect(page).toHaveURL(/\/reservas\/\d+$/);
        await expect(page.getByRole('heading', { name: 'PRUEBA E2E · teclado' })).toBeVisible();
    });

    test('El foco del teclado se ve en los botones y enlaces', async ({ page }) => {
        await page.goto('/reservas');

        const link = page.getByRole('link', { name: 'Reservar espacio' });
        await link.focus();
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');
        await expect(link).toBeFocused();

        const outline = await link.evaluate((el) => {
            const style = getComputedStyle(el);
            return { width: style.outlineWidth, style: style.outlineStyle, shadow: style.boxShadow };
        });
        expect(outline.style !== 'none' && outline.width !== '0px' ? true : outline.shadow !== 'none').toBe(true);
    });
});
