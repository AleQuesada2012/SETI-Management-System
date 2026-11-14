import { expect, type Download, type Page } from '@playwright/test';
import { shownDate } from './dates';

/** Opens a report from the "Reportes" menu: picks the category and follows its "Ver reporte" link. */
export async function openReport(page: Page, category: string, code: string): Promise<void> {
    await page.goto('/reportes');
    await page.getByLabel('Categoría').selectOption({ label: category });
    await page.locator(`a[href$="/reportes/${code}"]`).click();
    await expect(page).toHaveURL(new RegExp(`/reportes/${code}$`));
}

/**
 * Fills the "Desde" and "Hasta" of the report's date criterion and waits until "Filtros aplicados" shows the range,
 * as a person would before exporting.
 */
export async function filterDates(page: Page, from: string, to: string): Promise<void> {
    await page.getByLabel('Desde').fill(from);
    await page.getByLabel('Hasta').fill(to);
    await expect(page.getByText(`Desde ${shownDate(from)} · Hasta ${shownDate(to)}`)).toBeVisible();
}

/** The data rows of the preview table (the header row excluded). */
export function previewRows(page: Page) {
    return page.getByRole('table').getByRole('row').filter({ has: page.getByRole('cell') });
}

/** "Exportar" → the format, and waits for the file the browser downloads. */
export async function exportReport(page: Page, format: 'PDF' | 'Excel (XLSX)'): Promise<Download> {
    await page.getByTestId('export-button').click();
    const download = page.waitForEvent('download');
    await page.getByRole('menuitem', { name: `Exportar ${format}` }).click();

    return download;
}
