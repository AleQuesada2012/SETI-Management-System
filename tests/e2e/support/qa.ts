import { execFileSync } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { expect, type Browser, type Download, type Page } from '@playwright/test';
import { PASSWORD } from './accounts';

/*
 * Helpers of the Security and Reports cases (CP-00-1 to CP-84). The accounts below are created by qa-data.php on top
 * of the demo data; their password is also "password".
 */
export const QA_USERS = {
    consulta: 'consulta@seti.test', // custom role: reports.view without audit, export or history
    exporta: 'exporta@seti.test', // custom role: reports.view + reports.export, no history
    dosfactores: 'dosfactores@seti.test', // Superusuario with two-factor authentication confirmed
    bloqueo: 'bloqueo@seti.test',
    recupera: 'recupera@seti.test',
    cambios: 'cambios@seti.test',
} as const;

/** The fixed two-factor secret qa-data.php stores for dosfactores@seti.test. */
export const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

/** The Mailpit API of the local stack (MAILPIT_URL, default the QA stack's port). */
export const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://localhost:8125';

export const SUCCESS = 'El reporte se generó correctamente.';

/** Signs in through the form with a fresh browser context and returns the page (for accounts without a saved session). */
export async function signIn(browser: Browser, email: string, password = PASSWORD): Promise<Page> {
    const page = await (await browser.newContext()).newPage();
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Contraseña', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Ingresar al sistema' }).click();
    await expect(page).not.toHaveURL(/\/login/);

    return page;
}

/**
 * Runs an action that triggers a Livewire request (a checkbox, a typed filter with its 300 ms debounce) and waits for
 * that request's response and the re-render, so the next step never reads or types over a stale page.
 */
export async function livewire(page: Page, action: () => Promise<unknown>): Promise<void> {
    const response = page.waitForResponse((r) => /\/livewire[^/]*\/update/.test(r.url()), { timeout: 10_000 }).catch(() => null);
    await action();
    await response;
    await page.waitForTimeout(200);
}

/** Kept for the specs that call it after an action: waits a little for a pending (debounced) request to finish. */
export async function settle(page: Page): Promise<void> {
    await page.waitForTimeout(400);
    await page.waitForFunction(() => !(window as any).Livewire?.all?.().some((c: any) => c?.__pendingRequest), null, { timeout: 5_000 }).catch(() => {});
    await page.waitForTimeout(300);
}

function criterionDetails(page: Page, criterion: string) {
    return page.locator('details').filter({ has: page.locator('summary', { hasText: new RegExp(`^${criterion}$`) }) });
}

/** Opens a filter criterion of the "values" type (a <details> with the criterion's name) and checks some options. */
export async function pick(page: Page, criterion: string, ...options: string[]): Promise<void> {
    const details = criterionDetails(page, criterion);
    if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) {
        await details.locator('summary').click();
    }
    for (const option of options) {
        await livewire(page, () => details.getByRole('checkbox', { name: option, exact: true }).check());
    }
}

/** Unchecks options of a criterion that is already open. */
export async function unpick(page: Page, criterion: string, ...options: string[]): Promise<void> {
    const details = criterionDetails(page, criterion);
    for (const option of options) {
        await livewire(page, () => details.getByRole('checkbox', { name: option, exact: true }).uncheck());
    }
}

/** Types into a filter input (search, dates) and waits for the preview to update. */
export async function type(page: Page, label: string, value: string): Promise<void> {
    await livewire(page, () => page.getByLabel(label, { exact: true }).fill(value));
}

/** Fills "Desde" and "Hasta" of a report's date criterion and waits until "Filtros aplicados" shows them. */
export async function dates(page: Page, from: string, to: string): Promise<void> {
    if (from) {
        await livewire(page, () => page.getByLabel('Desde').fill(from));
    }
    if (to) {
        await livewire(page, () => page.getByLabel('Hasta').fill(to));
    }
    if (from) {
        await expect(appliedFilters(page)).toContainText(`Desde ${shown(from)}`);
    }
    if (to) {
        await expect(appliedFilters(page)).toContainText(`Hasta ${shown(to)}`);
    }
}

/** The "Filtros aplicados" callout. */
export function appliedFilters(page: Page) {
    return page.locator('[data-flux-callout]').filter({ hasText: 'Filtros aplicados' });
}

/** The "N registros" total under the preview, as a number (0 when the preview is empty). */
export async function previewTotal(page: Page): Promise<number> {
    const total = page.getByText(/^\d+ registros?$/);
    if ((await total.count()) === 0) {
        return 0;
    }

    return Number((await total.innerText()).match(/\d+/)![0]);
}

/** Reads every column of the preview table (all pages are not needed: the demo data fits in one page of 50). */
export async function previewColumn(page: Page, header: string): Promise<string[]> {
    const headers = await page.getByRole('table').getByRole('columnheader').allInnerTexts();
    const index = headers.findIndex((text) => text.trim() === header);
    expect(index, `column "${header}" in ${headers.join(' | ')}`).toBeGreaterThanOrEqual(0);

    return page.getByRole('table').getByRole('row').filter({ has: page.getByRole('cell') }).locator(`td:nth-child(${index + 1})`).allInnerTexts();
}

/** "Exportar" → format; waits for the download and the success toast, and returns the file's text. */
export async function exportAndRead(page: Page, format: 'PDF' | 'Excel (XLSX)'): Promise<{ download: Download; text: string }> {
    await page.getByTestId('export-button').click();
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: `Exportar ${format}` }).click();
    const download = await downloadPromise;
    await expect(page.getByText(SUCCESS).first()).toBeVisible();

    return { download, text: await fileText(download) };
}

/** The text of a downloaded PDF (pdftotext) or XLSX (the XML of its sheets, tags removed, one cell per line). */
export async function fileText(download: Download): Promise<string> {
    const path = await download.path();
    if (download.suggestedFilename().endsWith('.pdf')) {
        return execFileSync('pdftotext', ['-layout', path, '-']).toString();
    }

    return xlsxText(path);
}

export function xlsxText(path: string): string {
    const entries = execFileSync('unzip', ['-Z1', path]).toString().split('\n').filter(Boolean);
    const read = (entry: string) => execFileSync('unzip', ['-p', path, entry]).toString();
    const strings = entries.includes('xl/sharedStrings.xml')
        ? [...read('xl/sharedStrings.xml').matchAll(/<si>([\s\S]*?)<\/si>/g)].map((m) => decode(m[1].replace(/<[^>]+>/g, '')))
        : [];
    const sheetNames = [...read('xl/workbook.xml').matchAll(/<sheet [^>]*name="([^"]+)"/g)].map((m) => decode(m[1]));
    const sheets = entries.filter((entry) => /^xl\/worksheets\/sheet\d+\.xml$/.test(entry)).sort();

    return sheets
        .map((entry, i) => {
            const cells = [...read(entry).matchAll(/<c [^>]*?(t="(\w+)")?[^>]*>([\s\S]*?)<\/c>/g)].map((m) => {
                const value = (m[3].match(/<v>([\s\S]*?)<\/v>/) ?? m[3].match(/<t[^>]*>([\s\S]*?)<\/t>/))?.[1] ?? '';
                return m[2] === 's' ? strings[Number(value)] : decode(value);
            });
            return `### hoja ${sheetNames[i] ?? entry}\n${cells.join('\n')}`;
        })
        .join('\n');
}

function decode(text: string): string {
    return text.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#039;|&apos;/g, "'").replace(/&amp;/g, '&');
}

/** RFC 6238 TOTP (SHA-1, 30 s, 6 digits) of a base32 secret, as an authenticator app computes it. */
export function totp(secret: string, at = Date.now()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) {
        bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    }
    const key = Buffer.from(bits.match(/.{8}/g)!.map((byte) => parseInt(byte, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(at / 1000 / 30)));
    const hmac = createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0xf;
    const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return code.toString().padStart(6, '0');
}

/** The latest Mailpit message sent to an address (polls for up to 15 s), as HTML. */
export async function latestMail(to: string): Promise<string> {
    for (let attempt = 0; attempt < 30; attempt++) {
        const list = await (await fetch(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(`to:${to}`)}&limit=1`)).json();
        if (list.messages?.length) {
            const message = await (await fetch(`${MAILPIT_URL}/api/v1/message/${list.messages[0].ID}`)).json();
            return message.HTML || message.Text;
        }
        await new Promise((resolve) => setTimeout(resolve, 500));
    }
    throw new Error(`No email for ${to} in Mailpit`);
}

/** Runs PHP code in the app container (tinker), for the checks the cases make "en el ambiente local" with the database. */
export function tinker(code: string): string {
    return execFileSync('docker', ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'tinker', '--execute', code], {
        cwd: new URL('../../../', import.meta.url).pathname,
    }).toString();
}

/** Runs SQL with psql as the application's database user, in the local stack. Returns stdout+stderr and the exit code. */
export function psql(sql: string): { output: string; ok: boolean } {
    try {
        const output = execFileSync('docker', ['compose', 'exec', '-T', 'pgsql', 'psql', '-U', 'seti', '-d', 'seti', '-v', 'ON_ERROR_STOP=1', '-c', sql], {
            cwd: new URL('../../../', import.meta.url).pathname,
            stdio: ['ignore', 'pipe', 'pipe'],
        }).toString();
        return { output, ok: true };
    } catch (error) {
        const e = error as { stdout?: Buffer; stderr?: Buffer };
        return { output: `${e.stdout ?? ''}${e.stderr ?? ''}`, ok: false };
    }
}

/** The ISO date of today plus some days, in Costa Rica. */
export function isoDay(offsetDays = 0): string {
    const date = new Date(Date.now() + offsetDays * 86_400_000);
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Costa_Rica' }).format(date);
}

/** DD/MM/AAAA of an ISO date, as the reports show it. */
export function shown(iso: string): string {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}
