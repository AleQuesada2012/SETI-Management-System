import { expect, test, type Page } from '@playwright/test';
import { PASSWORD, storageState, USERS } from '../support/accounts';
import { livewire, pick, psql, QA_USERS, SUCCESS, totp, TWO_FACTOR_SECRET } from '../support/qa';
import { openReport } from '../support/reports';

// Usabilidad (Nielsen) y accesibilidad (WCAG 2.2, nivel A) de Seguridad y de Reportes y Estadísticas: lo que un
// navegador comprueba sin otra herramienta. Each check leaves what it observed in an annotation, the evidence for the
// plan's tables. Contrast (1.4.3) is level AA: it is measured and recorded, never failed.

type Screen = { name: string; path: string; role: 'reportes' | 'superusuario' | null };

const screens: Screen[] = [
    { name: 'Inicio de sesión', path: '/login', role: null },
    { name: 'Olvidé mi contraseña', path: '/forgot-password', role: null },
    { name: 'Menú de reportes', path: '/reportes', role: 'reportes' },
    { name: 'Reporte de inventario', path: '/reportes/assets_inventory', role: 'reportes' },
    { name: 'Reporte de auditoría', path: '/reportes/audit', role: 'reportes' },
    { name: 'Estadísticas de activos', path: '/reportes/estadisticas', role: 'reportes' },
    { name: 'Estadísticas de mantenimiento', path: '/reportes/estadisticas/mantenimiento', role: 'reportes' },
    { name: 'Estadísticas de reservas', path: '/reportes/estadisticas/reservas', role: 'reportes' },
    { name: 'Reportes generados', path: '/reportes/generados', role: 'superusuario' },
    { name: 'Tableros de Power BI', path: '/reportes/tableros', role: 'superusuario' },
];

async function open(page: Page, screen: Screen): Promise<void> {
    await page.goto(screen.path);
    await page.waitForLoadState('networkidle');
}

/** Controls and images without an accessible name, duplicated ids, charts without a name and a table beside them. */
async function audit(page: Page) {
    return page.evaluate(() => {
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
        const unnamed = [...document.querySelectorAll('input:not([type=hidden]), select, textarea, button, a[href], summary')]
            .filter(visible)
            .filter((el) => !named(el))
            .map((el) => el.outerHTML.slice(0, 140));
        const images = [...document.querySelectorAll('img')].filter((img) => !img.hasAttribute('alt')).map((img) => img.outerHTML.slice(0, 140));
        const ids = [...document.querySelectorAll('[id]')].map((el) => el.id).filter(Boolean);
        const duplicatedIds = [...new Set(ids.filter((id, i) => ids.indexOf(id) !== i))];
        const charts = [...document.querySelectorAll('canvas')].map((canvas) => ({
            label: canvas.getAttribute('aria-label'),
            role: canvas.getAttribute('role'),
            table: Boolean(canvas.closest('div')?.parentElement?.querySelector('table')),
        }));
        const headings = [...document.querySelectorAll('h1, h2, h3, h4')].filter(visible).map((h) => `${h.tagName}:${h.textContent?.trim().slice(0, 40)}`);

        return { unnamed: [...unnamed, ...images], duplicatedIds, charts, headings };
    });
}

/** Text whose contrast with its background is under 4.5:1 (3:1 for large text), colours resolved through a canvas. */
async function lowContrast(page: Page) {
    return page.evaluate(() => {
        const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true })!;
        const rgba = (color: string): [number, number, number, number] => {
            ctx.clearRect(0, 0, 1, 1);
            ctx.fillStyle = '#000';
            ctx.fillStyle = color;
            ctx.fillRect(0, 0, 1, 1);
            const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;
            return [r, g, b, a / 255];
        };
        const lum = ([r, g, b]: number[]) => {
            const c = [r, g, b].map((v) => {
                const s = v / 255;
                return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
            });
            return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        };
        const background = (el: Element | null): number[] => {
            for (; el; el = el.parentElement) {
                const [r, g, b, a] = rgba(getComputedStyle(el).backgroundColor);
                if (a > 0.9) return [r, g, b];
            }
            return [255, 255, 255];
        };
        const found = new Map<string, string>();
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const text = node.textContent?.trim();
            const el = node.parentElement;
            if (!text || !el) continue;
            const box = el.getBoundingClientRect();
            const style = getComputedStyle(el);
            if (box.width === 0 || box.height === 0 || style.visibility === 'hidden' || Number(style.opacity) === 0) continue;
            if (el.closest('[aria-hidden="true"], [disabled]')) continue;
            const fg = rgba(style.color);
            const bg = background(el);
            const mixed = fg.slice(0, 3).map((v, i) => v * fg[3] + bg[i] * (1 - fg[3]));
            const [l1, l2] = [lum(mixed), lum(bg)].sort((a, b) => b - a);
            const ratio = (l1 + 0.05) / (l2 + 0.05);
            const size = parseFloat(style.fontSize);
            const large = size >= 24 || (size >= 18.66 && Number(style.fontWeight) >= 700);
            if (ratio < (large ? 3 : 4.5)) {
                found.set(text.slice(0, 40), `${ratio.toFixed(2)}:1`);
            }
        }
        return [...found].map(([text, ratio]) => `"${text}" ${ratio}`);
    });
}

/** Presses Tab until the locator has the focus; returns how many presses it took. */
async function tabTo(page: Page, target: ReturnType<Page['locator']>, max = 80): Promise<number> {
    for (let presses = 0; presses <= max; presses++) {
        if (await target.evaluate((el) => el === document.activeElement || el.contains(document.activeElement)).catch(() => false)) {
            return presses;
        }
        await page.keyboard.press('Tab');
    }
    await expect(target).toBeFocused();
    return max;
}

async function focusIndicator(page: Page): Promise<string> {
    return page.evaluate(() => {
        const el = document.activeElement as HTMLElement;
        const style = getComputedStyle(el);
        return `${el.tagName.toLowerCase()} · outline ${style.outlineStyle} ${style.outlineWidth} · box-shadow ${style.boxShadow}`;
    });
}

test.describe('Accesibilidad · Seguridad, Reportes y Estadísticas', () => {
    const titles = new Map<string, string>();

    for (const screen of screens) {
        test(`${screen.name}: idioma, título, nombres accesibles, gráficos y contraste`, async ({ browser }) => {
            const page = await (await browser.newContext(screen.role ? { storageState: storageState(screen.role) } : {})).newPage();
            await open(page, screen);

            await expect(page.locator('html')).toHaveAttribute('lang', 'es');
            const title = await page.title();
            expect(title.trim()).not.toBe('');
            titles.set(screen.path, title);

            const found = await audit(page);
            const contrast = await lowContrast(page);
            test.info().annotations.push(
                { type: 'título', description: title },
                { type: 'encabezados', description: found.headings.join(' · ') },
                { type: 'gráficos', description: JSON.stringify(found.charts) },
                { type: 'ids repetidos', description: found.duplicatedIds.join(', ') || 'ninguno' },
                { type: 'contraste < 4,5:1 (AA, observación)', description: contrast.join(' | ') || 'ninguno' },
            );

            expect(found.unnamed, 'controls or images without an accessible name').toEqual([]);
            for (const chart of found.charts) {
                expect(chart.role).toBe('img');
                expect(chart.label?.trim()).toBeTruthy();
                expect(chart.table, `table beside the chart "${chart.label}"`).toBe(true);
            }
        });
    }

    test('cada reporte tiene un título de página propio', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        const seen: string[] = [];
        for (const code of ['assets_inventory', 'maintenance', 'movements', 'warranties', 'audit', 'users', 'spaces']) {
            await page.goto(`/reportes/${code}`);
            seen.push(await page.title());
        }
        test.info().annotations.push({ type: 'títulos', description: seen.join(' | ') });
        expect(new Set(seen).size).toBe(seen.length);
    });

    test('a 360 px ninguna pantalla se desborda; la vista previa se desplaza dentro de su tarjeta', async ({ browser }) => {
        const results: string[] = [];
        for (const screen of screens) {
            const context = await browser.newContext({
                ...(screen.role ? { storageState: storageState(screen.role) } : {}),
                viewport: { width: 360, height: 780 },
                isMobile: true,
                hasTouch: true,
            });
            const page = await context.newPage();
            await open(page, screen);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            const table = await page.evaluate(() => {
                const t = document.querySelector('table');
                const card = t?.closest('.overflow-x-auto') as HTMLElement | null;
                return t ? `tabla ${t.scrollWidth}px en tarjeta ${card ? `${card.clientWidth}px con desplazamiento` : 'sin desplazamiento propio'}` : 'sin tabla';
            });
            await test.info().attach(`${screen.name} (360 px)`, { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
            results.push(`${screen.name}: desborde ${overflow}px, ${table}`);
            await context.close();
        }
        test.info().annotations.push({ type: '360 px', description: results.join(' | ') });
        expect(results.filter((line) => !/desborde (0|-\d+)px/.test(line))).toEqual([]);
    });

    test('inicio de sesión solo con el teclado, con el foco visible', async ({ page }) => {
        await page.goto('/login');
        const email = page.getByLabel('Correo electrónico');
        const presses = await tabTo(page, email);
        const focusEmail = await focusIndicator(page);
        await page.keyboard.type(USERS.reportes.email);
        await page.keyboard.press('Tab');
        await expect(page.getByLabel('Contraseña', { exact: true })).toBeFocused();
        await page.keyboard.type(PASSWORD);
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/tablero$/);
        test.info().annotations.push({ type: 'teclado', description: `${presses} Tab hasta el correo; foco: ${focusEmail}` });
    });

    test('código de autenticación solo con el teclado', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill(QA_USERS.dosfactores);
        await page.getByLabel('Contraseña', { exact: true }).fill(PASSWORD);
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/two-factor-challenge/);
        const found = await audit(page);
        expect(found.unnamed).toEqual([]);
        const focused = await page.evaluate(() => document.activeElement?.outerHTML.slice(0, 120));
        await page.keyboard.type(totp(TWO_FACTOR_SECRET));
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/tablero$/);
        test.info().annotations.push({ type: 'foco al abrir el código', description: focused ?? '' }, { type: 'título', description: await page.title() });
    });

    test('filtrar, paginar y exportar un reporte solo con el teclado, con el foco visible', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('superusuario') })).newPage();
        await openReport(page, 'Auditoría', 'audit');
        const notes: string[] = [];
        const log = (line: string) => notes.push(line);

        // A criterion: Tab to its summary, Enter opens it, Tab to the first option, Space checks it.
        const summary = page.locator('summary', { hasText: /^Tipo de operación$/ });
        log(`${await tabTo(page, summary)} Tab hasta "Tipo de operación"; foco: ${await focusIndicator(page)}`);
        await page.keyboard.press('Enter');
        await page.keyboard.press('Tab');
        const option = await page.evaluate(() => document.activeElement?.closest('[data-flux-field], label')?.textContent?.trim() ?? '');
        log(`opción con el foco: ${option}; foco: ${await focusIndicator(page)}`);
        await livewire(page, () => page.keyboard.press('Space'));
        await expect(page.locator('[data-flux-callout]').filter({ hasText: 'Filtros aplicados' })).toContainText('Tipo de operación');

        // Pagination, when the filtered preview has more than one page.
        await livewire(page, () => page.keyboard.press('Space'));
        const next = page.locator('[data-flux-pagination] button, nav[aria-label*="aginación"] button, button[wire\\:click*="nextPage"]').last();
        if ((await next.count()) > 0) {
            await tabTo(page, next, 200);
            const before = await page.getByRole('table').getByRole('row').nth(1).innerText();
            await livewire(page, () => page.keyboard.press('Enter'));
            const after = await page.getByRole('table').getByRole('row').nth(1).innerText();
            expect(after).not.toBe(before);
            log(`paginación con Enter: primera fila cambió; foco: ${await focusIndicator(page)}`);
        } else {
            log('la vista previa tiene una sola página: sin paginación que probar');
        }

        // Export: Tab to "Exportar", Enter opens the menu, the arrow keys move, Enter generates the PDF.
        const exportButton = page.getByTestId('export-button');
        await exportButton.focus();
        log(`foco en Exportar: ${await focusIndicator(page)}`);
        await page.keyboard.press('Enter');
        await expect(page.getByRole('menuitem', { name: 'Exportar PDF' })).toBeVisible();
        const download = page.waitForEvent('download', { timeout: 15_000 });
        const active = () => page.evaluate(() => `${document.activeElement?.tagName.toLowerCase()}[role=${document.activeElement?.getAttribute('role')}] ${(document.activeElement as HTMLElement).innerText?.trim().split('\n')[0]}`);
        // Flux moves the focus into the menu a moment after it opens; a person never types faster than that.
        const intoMenu = await expect
            .poll(() => page.evaluate(() => Boolean(document.activeElement?.closest('[role=menu]'))), { timeout: 3_000 })
            .toBe(true)
            .then(() => true, () => false);
        const focusedItem = await active();
        if (!/menuitem.*Exportar PDF/.test(focusedItem)) {
            await page.keyboard.press('ArrowDown');
        }
        log(`menú abierto; foco dentro del menú: ${intoMenu ? 'sí' : 'no'} (${focusedItem}); tras la flecha: ${await active()}`);
        await page.keyboard.press('Enter');
        expect((await download).suggestedFilename()).toMatch(/\.pdf$/);
        await expect(page.getByText(SUCCESS).first()).toBeVisible();
        test.info().annotations.push({ type: 'teclado', description: notes.join(' | ') });
    });

    test('Escape cierra "Exportar" sin generar nada', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Activos', 'assets_inventory');
        const count = () => Number(psql('select count(*) from report_generations').output.match(/\n\s*(\d+)\n/)?.[1]);
        const before = count();
        await page.getByTestId('export-button').click();
        await expect(page.getByRole('menuitem', { name: 'Exportar PDF' })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('menuitem', { name: 'Exportar PDF' })).toBeHidden();
        await page.waitForTimeout(1_000);
        expect(count()).toBe(before);
        await expect(page.getByText(SUCCESS)).toHaveCount(0);
    });

    test('la actualización cada 15 s no mueve el foco ni borra lo escrito', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/reportes/estadisticas/mantenimiento');
        await page.waitForLoadState('networkidle');
        const from = page.getByLabel('Realizados desde', { exact: true });
        await livewire(page, () => from.fill('2026-01-01'));
        const to = page.getByLabel('Realizados hasta', { exact: true });
        await to.focus();

        const polls: number[] = [];
        page.on('response', (r) => /\/livewire[^/]*\/update/.test(r.url()) && polls.push(Date.now()));
        await page.waitForTimeout(16_500);

        expect(polls.length, 'a poll request in 16.5 s').toBeGreaterThan(0);
        await expect(to).toBeFocused();
        await expect(from).toHaveValue('2026-01-01');
        test.info().annotations.push({ type: '15 s', description: `${polls.length} actualización(es) en 16,5 s; el foco siguió en "Realizados hasta" y "Realizados desde" conservó 01/01/2026` });
    });

    test('nada parpadea: animaciones activas en las estadísticas', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto('/reportes/estadisticas');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2_000);
        const animations = await page.evaluate(() =>
            document.getAnimations().map((a) => {
                const target = (a.effect as KeyframeEffect | null)?.target as Element | null;
                return `${(a as CSSAnimation).animationName ?? a.constructor.name} en ${target?.tagName.toLowerCase()}.${target?.className?.toString().slice(0, 40)} (${a.effect?.getTiming().iterations})`;
            }),
        );
        test.info().annotations.push({ type: 'animaciones', description: animations.join(' | ') || 'ninguna' });
        expect(animations.filter((a) => /blink|flash|pulse/i.test(a))).toEqual([]);
    });

    test('cambiar un filtro actualiza la vista previa sin cambiar de página', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Activos', 'assets_inventory');
        let loads = 0;
        page.on('load', () => loads++);
        await pick(page, 'Color de condición', 'Ámbar');
        expect(new URL(page.url()).pathname).toBe('/reportes/assets_inventory');
        expect(loads).toBe(0);
        test.info().annotations.push({ type: 'dirección', description: page.url() });
    });

    test('los errores dicen qué campo corregir', async ({ browser, page }) => {
        const notes: string[] = [];

        await page.goto('/login');
        await page.getByLabel('Correo electrónico').fill(USERS.visualizador.email);
        await page.getByLabel('Contraseña', { exact: true }).fill('incorrecta');
        await page.keyboard.press('Enter');
        const error = page.getByText('El correo electrónico o la contraseña no son correctos.');
        await expect(error).toBeVisible();
        const link = await page.getByLabel('Correo electrónico').evaluate((input) => ({
            invalid: input.getAttribute('aria-invalid'),
            describedBy: input.getAttribute('aria-describedby'),
            nextToField: Boolean(input.closest('[data-flux-field]')?.textContent?.includes('no son correctos')),
        }));
        notes.push(`inicio de sesión: ${JSON.stringify(link)}`);

        // Credentials of an email that does not exist: the same message, so it does not reveal which accounts exist.
        await page.getByLabel('Correo electrónico').fill('nadie-existe@seti.test');
        await page.getByLabel('Contraseña', { exact: true }).fill('incorrecta');
        await page.keyboard.press('Enter');
        await expect(error).toBeVisible();
        notes.push('correo inexistente: el mismo mensaje');

        const reports = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(reports, 'Activos', 'maintenance');
        await livewire(reports, () => reports.getByLabel('Desde').fill('2026-11-10'));
        await livewire(reports, () => reports.getByLabel('Hasta').fill('2026-11-01'));
        await expect(reports.getByText('La fecha final no puede ser anterior a la inicial.')).toBeVisible();
        const dateField = await reports.getByLabel('Hasta').evaluate((input) => ({
            invalid: input.getAttribute('aria-invalid'),
            describedBy: input.getAttribute('aria-describedby'),
            nextToField: Boolean(input.closest('[data-flux-field]')?.textContent?.includes('no puede ser anterior')),
        }));
        notes.push(`fechas invertidas: ${JSON.stringify(dateField)}`);
        test.info().annotations.push({ type: 'errores', description: notes.join(' | ') });
    });

    test('reconocimiento: "Volver a reportes", opciones "Sin …" y enlaces de las estadísticas', async ({ browser }) => {
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        const notes: string[] = [];
        for (const path of ['/reportes/assets_inventory', '/reportes/custodians', '/reportes/estadisticas', '/reportes/estadisticas/mantenimiento', '/reportes/estadisticas/reservas']) {
            await page.goto(path);
            const back = await page.getByRole('link', { name: 'Volver a reportes' }).count();
            const missing = await page.getByRole('checkbox', { name: /^Sin / }).evaluateAll((boxes) => boxes.map((b) => (b as HTMLElement).closest('[data-flux-field], label, div')?.textContent?.trim()));
            notes.push(`${path}: Volver ${back ? 'sí' : 'no'}; ${missing.join(', ') || 'sin opciones "Sin …"'}`);
            expect(back).toBeGreaterThan(0);
        }
        test.info().annotations.push({ type: 'reconocimiento', description: notes.join(' | ') });
    });
});
