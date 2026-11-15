import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { expect, type Page, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { audit, contrast } from '../support/a11y';

// Usabilidad (Nielsen) y accesibilidad (WCAG 2.2, nivel A) de Gestión de Espacios: lo que un navegador comprueba sin
// otra herramienta. Each check leaves what it observed in an annotation, the evidence for the plan's tables. Contrast
// (1.4.3) is level AA: it is measured and recorded, never failed. LAB-110 (id 1) is managed by Mario Solís
// (administrador) and has assets; SAL-01 (id 6) has an upcoming reservation.

const screens: Array<[string, string]> = [
    ['Listado de espacios', '/espacios'],
    ['Detalle de un espacio', '/espacios/1'],
    ['Historial de un espacio', '/espacios/1/historial'],
    ['Calendario de reservas', '/reservas'],
];

/** The rows of the main list: while searching, the page also shows a table with the matches above it. */
const rows = (page: Page) => page.getByRole('table').last().locator('tbody tr');

const note = (type: string, description: string) => test.info().annotations.push({ type, description });

/** Opens "Añadir espacio" from the list. */
async function openCreate(page: Page) {
    await page.goto('/espacios');
    await page.getByTestId('create-space-button').click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Añadir espacio');

    return dialog;
}

/** Opens "Editar" from the detail of LAB-110. */
async function openEdit(page: Page) {
    await page.goto('/espacios/1');
    await page.getByTestId('edit-space-button').click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Editar espacio');

    return dialog;
}

/** Names of the fields of a form, in order. */
const fieldNames = (dialog: ReturnType<Page['getByRole']>) =>
    dialog.locator('input:not([type=hidden]), select, textarea').evaluateAll((els) => els.map((el) => (el as HTMLInputElement).name).filter(Boolean));

test.describe('Usabilidad · Gestión de Espacios', () => {
    test.use({ storageState: storageState('administrador') });

    test('Visibilidad del estado del sistema', async ({ page }) => {
        await page.goto('/espacios');
        await expect(rows(page).filter({ hasText: 'LAB-110' })).toContainText(/Disponible|En mantenimiento|Inhabilitado/);
        await expect(page.getByTestId('sort-created')).toBeVisible();
        await expect(page.getByTestId('sort-updated')).toBeVisible();

        await page.goto('/espacios/1');
        await expect(page.getByTestId('space-status')).toHaveText(/Disponible|En mantenimiento|Inhabilitado/);
        await expect(page.getByText('Cargando las fotografías…')).toHaveCount(1);

        await page.getByTestId('edit-space-button').click();
        await page.getByTestId('edit-space-submit').click();
        const toast = page.getByText('Los cambios de Laboratorio 110 se guardaron correctamente.').first();
        await expect(toast).toBeVisible();
        note('aviso al guardar', await toast.innerText());
    });

    test('Relación entre el sistema y el mundo real', async ({ page }) => {
        await page.goto('/espacios/1');
        for (const label of ['Tipo de espacio', 'Capacidad', 'Ubicación', 'Encargados del espacio']) {
            await expect(page.getByRole('term').filter({ hasText: label })).toBeVisible();
        }
        await expect(page.getByTestId('space-capacity')).toHaveText(/^\d+ personas?$/);

        const dialog = await openCreate(page);
        const types = await dialog.getByLabel('Tipo de espacio').locator('option').allInnerTexts();
        expect(types).toEqual(expect.arrayContaining(['Laboratorio', 'Oficina', 'Sala']));
        note('tipos de espacio', types.join(' | '));
    });

    test('Control y libertad del usuario', async ({ page }) => {
        const dialog = await openCreate(page);
        await expect(dialog.getByRole('button', { name: 'Cancelar' })).toBeVisible();
        await dialog.getByRole('button', { name: 'Cancelar' }).click();
        await expect(page.getByRole('dialog')).toBeHidden();

        await page.goto('/espacios');
        const all = await rows(page).count();
        await page.getByTestId('filter-space_status').getByText('En mantenimiento').click();
        await expect(page.getByTestId('active-filters')).toContainText('Estado: En mantenimiento');
        await expect(rows(page)).not.toHaveCount(all);

        await page.getByTestId('clear-filters').click();
        await expect(rows(page)).toHaveCount(all);
        expect(new URL(page.url()).search).toBe('');

        await page.goto('/espacios/1/historial');
        await expect(page.getByRole('link', { name: 'Volver al espacio' })).toBeVisible();
    });

    test('Consistencia y estándares', async ({ page }) => {
        const create = await fieldNames(await openCreate(page));
        const edit = await fieldNames(await openEdit(page));
        expect(edit, 'Añadir y Editar ofrecen los mismos campos').toEqual(create);
        note('campos de Añadir y Editar', create.join(', '));

        await expect(page.getByTestId('confirm-delete-space')).toHaveText('Sí, eliminar');
        await page.goto('/activos/1');
        await expect(page.getByTestId('asset-document-confirm-remove')).toHaveText('Sí, eliminar');
    });

    test('Prevención de errores', async ({ page }) => {
        await page.goto('/espacios/1');
        await page.getByTestId('delete-space-button').click();
        const blocked = page.getByRole('dialog').filter({ hasText: 'No se puede eliminar' });
        await expect(blocked).toContainText('tiene');
        await expect(blocked).toContainText('activos');
        note('eliminar bloqueado', (await blocked.innerText()).replace(/\s+/g, ' '));
        await blocked.getByRole('button', { name: 'Entendido' }).click();

        await expect(page.getByTestId('space-photos-input')).toHaveAttribute('accept', 'image/jpeg,image/png,image/webp,image/gif');

        await page.goto('/espacios/6');
        await page.getByTestId('change-status-button').click();
        await page.getByRole('menuitem', { name: 'Inhabilitado' }).click();
        await expect(page.getByTestId('status-warning')).toContainText('reserva confirmada que aún no termina');
        await page.getByRole('dialog').getByRole('button', { name: 'Cancelar' }).click();
        await expect(page.getByTestId('space-status')).toHaveText('Disponible');

        const dialog = await openCreate(page);
        await dialog.getByLabel('Código').fill('PRUEBA-DUP');
        await dialog.getByLabel('Nombre').fill('Laboratorio 110');
        await dialog.getByLabel('Tipo de espacio').selectOption({ index: 1 });
        await dialog.getByLabel('Edificio').selectOption({ index: 1 });
        await dialog.getByLabel('Capacidad').fill('5');
        await page.getByTestId('create-space-submit').click();
        await expect(dialog.getByText('Ya existe un espacio con ese nombre.')).toBeVisible();
    });

    test('Reconocimiento en vez de recuerdo', async ({ page }) => {
        const dialog = await openEdit(page);
        await expect(dialog.getByLabel('Código')).toHaveValue('LAB-110');
        await expect(dialog.getByLabel('Nombre')).toHaveValue('Laboratorio 110');
        await expect(dialog.getByLabel('Capacidad')).toHaveValue('30');
        expect(await dialog.getByLabel('Edificio').locator('option').count()).toBeGreaterThan(2);
        expect(await dialog.getByLabel('Tipo de espacio').locator('option').count()).toBeGreaterThan(2);
    });

    test('Flexibilidad y eficiencia de uso', async ({ page }) => {
        await page.goto('/espacios');
        const all = await rows(page).count();

        // The search filters while typing: no button to press.
        await page.getByTestId('space-search').fill('SAL-01');
        await expect(rows(page)).toHaveCount(1);
        expect(decodeURIComponent(new URL(page.url()).search)).toContain('buscar=SAL-01');
        await page.getByTestId('space-search').fill('');
        await expect(rows(page)).toHaveCount(all);

        // The three filters combine.
        await page.getByTestId('filter-space_type').getByText('Laboratorio', { exact: true }).click();
        await expect(page.getByTestId('active-filters')).toContainText('Tipo de espacio');
        const byType = await rows(page).count();
        await page.getByTestId('filter-space_status').getByText('Disponible', { exact: true }).click();
        await expect(page.getByTestId('active-filters')).toContainText('Estado');
        expect(await rows(page).count()).toBeLessThanOrEqual(byType);
        expect(decodeURIComponent(new URL(page.url()).search)).toMatch(/filters\[space_type\].*filters\[space_status\]/);

        await page.goto('/espacios/1');
        await expect(page.getByText(/hasta 10 a la vez/)).toBeVisible();
    });

    test('Diseño estético y minimalista', async ({ page, browser }) => {
        await page.goto('/espacios/1');
        const sections = await page.getByRole('heading', { level: 3 }).allInnerTexts();
        expect(sections.map((s) => s.replace(/\s*\(\d+\)$/, ''))).toEqual(
            expect.arrayContaining(['Información del espacio', 'Fotografías', 'Activos en el espacio', 'Reservas del espacio', 'Disponibilidad']),
        );
        note('tarjetas del detalle', sections.join(' | '));

        // A Visualizador sees no action buttons at all.
        const viewer = await (await browser.newContext({ storageState: storageState('visualizador') })).newPage();
        await viewer.goto('/espacios/1');
        for (const id of ['edit-space-button', 'delete-space-button', 'change-status-button', 'space-photos-input']) {
            await expect(viewer.getByTestId(id)).toHaveCount(0);
        }
    });

    test('Ayuda para reconocer, diagnosticar y recuperarse de errores', async ({ page }) => {
        const dialog = await openCreate(page);
        await dialog.getByLabel('Código').fill('PRUEBA-DUP');
        await dialog.getByLabel('Nombre').fill('Laboratorio 110');
        await dialog.getByLabel('Tipo de espacio').selectOption({ index: 1 });
        await dialog.getByLabel('Edificio').selectOption({ index: 1 });
        await dialog.getByLabel('Capacidad').fill('5');
        await page.getByTestId('create-space-submit').click();
        await expect(dialog.getByText('Ya existe un espacio con ese nombre.')).toBeVisible();

        await page.goto('/espacios/1');
        await page.getByTestId('delete-space-button').click();
        await expect(page.getByRole('dialog').filter({ hasText: 'No se puede eliminar' })).toContainText('Traslade sus activos a otro espacio y cancele sus reservas activas; después podrá eliminarlo.');

        await page.goto('/espacios');
        await page.getByTestId('space-search').fill('piscina');
        await expect(page.getByTestId('space-search-empty')).toContainText('No existe ningún espacio que coincida con «piscina».');
    });

    test('Ayuda y documentación', async ({ page }) => {
        const dialog = await openCreate(page);
        await expect(dialog).toContainText('Registra un laboratorio, oficina o sala. Empieza con el estado Disponible.');

        const manuals = ['fotografias-espacios.md', 'encargados-espacios.md', 'documentos-activos.md', 'fotografias-activos.md'];
        for (const manual of manuals) {
            expect(existsSync(fileURLToPath(new URL(`../../../docs/manuales/${manual}`, import.meta.url))), manual).toBe(true);
        }

        const links = await page.locator('a[href*="manual"], a[href*="ayuda"]').count();
        note('manuales', `${manuals.length} manuales en docs/manuales; enlaces a ellos en la aplicación: ${links}`);
    });
});

test.describe('Accesibilidad · Gestión de Espacios', () => {
    test.use({ storageState: storageState('administrador') });

    test('Perceptible · Texto alternativo: ninguna imagen sin alt ni control sin nombre', async ({ page }) => {
        for (const [name, path] of screens) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            const result = await audit(page);

            note(name, `imágenes sin alt: ${result.imagesWithoutAlt.length}; controles sin nombre: ${result.unnamed.length}`);
            expect(result.imagesWithoutAlt, `${name}: imágenes sin alt`).toEqual([]);
            expect(result.unnamed, `${name}: controles sin nombre`).toEqual([]);
        }
    });

    test('Perceptible · Adaptable: encabezados en orden y tablas con encabezados', async ({ page }) => {
        for (const [name, path] of screens) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            const result = await audit(page);
            const levels = result.headings.map((h) => Number(h[1]));

            expect(levels[0], `${name}: empieza con H1`).toBe(1);
            levels.forEach((level, i) => {
                if (i > 0) expect(level - levels[i - 1], `${name}: ${result.headings[i - 1]} → ${result.headings[i]}`).toBeLessThanOrEqual(1);
            });
            expect(result.tablesWithoutHeaders, `${name}: tablas sin encabezados`).toBe(0);
            note(name, result.headings.join(' › '));
        }
    });

    test('Perceptible · Distinguible: el estado no depende solo del color; contraste medido', async ({ page }) => {
        await page.goto('/reservas');
        await expect(page.getByRole('button', { name: /\d+ de \w+: (libre|con reserva)/i }).first()).toBeVisible();
        await expect(page.getByText('Con reservas', { exact: true })).toBeVisible();
        await expect(page.getByText('Libre', { exact: true })).toBeVisible();

        await page.goto('/espacios');
        await expect(rows(page).filter({ hasText: 'LAB-110' })).toContainText(/Disponible|En mantenimiento|Inhabilitado/);

        for (const scheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme: scheme });
            for (const [name, path] of screens) {
                await page.goto(path);
                await page.waitForLoadState('networkidle');
                const { lowest, below } = await contrast(page);
                note(`contraste ${scheme} · ${name}`, `mínimo ${lowest}:1; bajo 4,5:1: ${below.length ? below.map((b) => `${b.text} ${b.ratio}`).join('; ') : 'ninguno'}`);
            }
        }
    });

    test('Operable · Accesible por teclado: Añadir espacio, el formulario y el campo de archivo', async ({ page }) => {
        const tabUntil = async (matches: (el: Element) => boolean, max = 60): Promise<void> => {
            for (let presses = 0; presses < max; presses++) {
                await page.keyboard.press('Tab');
                if (await page.evaluate(`(${matches.toString()})(document.activeElement)`)) return;
            }
            throw new Error('no se llegó con Tab');
        };

        await page.goto('/espacios');
        await tabUntil((el) => el.getAttribute('data-test') === 'create-space-button');
        await page.keyboard.press('Enter');
        const dialog = page.getByRole('dialog');
        await expect(dialog).toContainText('Añadir espacio');
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();

        await page.goto('/espacios/1');
        await tabUntil((el) => el.getAttribute('data-test') === 'space-photos-input');
        await expect(page.getByTestId('space-photos-input')).toBeFocused();
    });

    test('Operable · Tiempo suficiente: los formularios no tienen límite de tiempo', async ({ page }) => {
        for (const [name, path] of screens) {
            await page.goto(path);
            await expect(page.locator('meta[http-equiv="refresh"]'), `${name}: redirección por tiempo`).toHaveCount(0);
        }
        note('sesión', 'se cierra a los 120 minutos sin uso o a las 9 horas sin avisar antes (DEF-01, #243)');
    });

    test('Operable · Convulsiones y reacciones físicas: solo hay indicadores de carga que giran', async ({ page }) => {
        for (const [name, path] of screens) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            const animations = await page.evaluate(() => document.getAnimations().map((a) => (a as CSSAnimation).animationName || (a as CSSTransition).transitionProperty || ''));

            expect(animations.filter((n) => n && n !== 'spin'), `${name}: animaciones que no son indicadores de carga`).toEqual([]);
            note(name, `animaciones: ${[...new Set(animations)].join(', ') || 'ninguna'}`);
        }
    });

    test('Operable · Navegable: título propio, foco visible y regiones', async ({ page }) => {
        const titles: string[] = [];
        for (const [, path] of screens) {
            await page.goto(path);
            titles.push(await page.title());
        }
        expect(new Set(titles).size, titles.join(' | ')).toBe(titles.length);
        note('títulos', titles.join(' | '));

        await page.goto('/espacios/1');
        await page.keyboard.press('Tab');
        const outline = await page.evaluate(() => {
            const style = getComputedStyle(document.activeElement!);
            return { style: style.outlineStyle, width: style.outlineWidth, shadow: style.boxShadow };
        });
        expect(outline.style !== 'none' && outline.width !== '0px' ? true : outline.shadow !== 'none').toBe(true);
        note('foco con el teclado', `${outline.style} ${outline.width}`);

        const { landmarks } = await audit(page);
        expect(landmarks).toContain('NAV');
        note('regiones', `${landmarks.join(', ')}${landmarks.includes('MAIN') ? '' : ' (sin <main> ni enlace para saltar el menú: se salta con los encabezados)'}`);
    });

    test('Comprensible · Legible: el idioma de cada página es español', async ({ page }) => {
        for (const [name, path] of screens) {
            await page.goto(path);
            await expect(page.locator('html'), name).toHaveAttribute('lang', 'es');
        }
    });

    test('Comprensible · Predecible: buscar, filtrar y guardar no cambian de página', async ({ page }) => {
        await page.goto('/espacios');
        await page.getByTestId('space-search').fill('lab');
        await page.getByTestId('filter-space_status').getByText('Disponible', { exact: true }).click();
        await expect(page.getByTestId('active-filters')).toBeVisible();
        expect(new URL(page.url()).pathname).toBe('/espacios');

        await page.goto('/espacios/1');
        await page.getByTestId('edit-space-button').click();
        await page.getByTestId('edit-space-submit').click();
        await expect(page.getByText('Los cambios de Laboratorio 110 se guardaron correctamente.').first()).toBeVisible();
        expect(new URL(page.url()).pathname).toBe('/espacios/1');
    });

    test('Comprensible · Asistencia para entradas: etiquetas, error anunciado y campo marcado', async ({ page }) => {
        const dialog = await openCreate(page);
        for (const label of ['Código', 'Nombre', 'Tipo de espacio', 'Edificio', 'Piso', 'Capacidad', 'Detalle de la ubicación', 'Notas']) {
            await expect(dialog.getByLabel(label, { exact: true }), label).toBeVisible();
        }

        await dialog.getByLabel('Código').fill('PRUEBA-DUP');
        await dialog.getByLabel('Nombre').fill('Laboratorio 110');
        await dialog.getByLabel('Tipo de espacio').selectOption({ index: 1 });
        await dialog.getByLabel('Edificio').selectOption({ index: 1 });
        await dialog.getByLabel('Capacidad').fill('5');
        await page.getByTestId('create-space-submit').click();

        await expect(dialog.getByRole('alert').filter({ hasText: 'Ya existe un espacio con ese nombre.' })).toBeVisible();
        await expect(dialog.getByLabel('Nombre')).toHaveAttribute('aria-invalid', 'true');

        const marked = await dialog.getByText(/\*|\(opcional\)|obligatorio/i).count();
        note('campos obligatorios', `${marked ? 'se marcan en pantalla' : 'solo llevan el atributo required: no se distinguen a la vista de los opcionales'}`);
    });

    test('Robusto · Compatible: sin ids repetidos', async ({ page }) => {
        for (const [name, path] of screens) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            expect((await audit(page)).duplicatedIds, `${name}: ids repetidos`).toEqual([]);
        }
    });

    test('Robusto · Compatible: el botón de cerrar el aviso tiene nombre accesible', async ({ page }) => {
        test.fail(true, 'DEF-12 (#257): el botón de cerrar del aviso de Flux no tiene texto, aria-label ni título (4.1.2).');
        await page.goto('/espacios/1');
        await page.getByTestId('edit-space-button').click();
        await page.getByTestId('edit-space-submit').click();
        await expect(page.getByText('Los cambios de Laboratorio 110 se guardaron correctamente.').first()).toBeVisible();

        await expect(page.locator('ui-toast-group').getByRole('button', { name: /\S/ })).toHaveCount(1);
    });
});
