import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { appliedFilters, exportAndRead, pick, psql, unpick } from '../support/qa';
import { openReport } from '../support/reports';

// CP-74 · Exportar reporte de un espacio (RF_74, CU_RF_74). The case uses the site's LAB-101 (17 assets) and AULA-M1;
// the local demo data has neither, so LAB-110 and LAB-312 stand in, and the expected assets come from the database.

function spaceOf(code: string): { id: number; assets: string[] } {
    const rows = psql(`select s.id, a.code from spaces s left join assets a on a.current_space_id = s.id where s.code = '${code}' order by a.code`)
        .output.split('\n')
        .map((line) => line.split('|').map((cell) => cell.trim()))
        .filter((cells) => cells.length === 2 && /^\d+$/.test(cells[0]));

    return { id: Number(rows[0][0]), assets: rows.map((cells) => cells[1]).filter(Boolean) };
}

test.describe('CP-74 · Exportar reporte de un espacio', () => {
    test('a y b. desde el espacio exporta el PDF y el XLSX con los datos del espacio y sus activos', async ({ browser }) => {
        const lab = spaceOf('LAB-110');
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await page.goto(`/espacios/${lab.id}`);

        const pdf = await exportAndRead(page, 'PDF');
        expect(pdf.text).toContain('Reporte de un espacio');
        for (const label of ['Espacio', 'Ubicación', 'Capacidad', 'Tipo de espacio', 'Estado operativo', 'Encargados del espacio']) {
            expect(pdf.text, `${label} in the PDF`).toContain(label);
        }
        expect(pdf.text).toContain('LAB-110');
        for (const code of lab.assets) {
            expect(pdf.text, `${code} in the PDF`).toContain(code);
        }
        expect(pdf.text).toContain(`Registros: ${lab.assets.length}`);
        test.info().annotations.push({ type: 'PDF', description: pdf.text.split('\n').slice(0, 16).join('\n') });

        const xlsx = await exportAndRead(page, 'Excel (XLSX)');
        const sheets = Object.fromEntries(xlsx.text.split('### hoja ').filter(Boolean).map((part) => [part.split('\n')[0], part]));
        expect(Object.keys(sheets)).toEqual(expect.arrayContaining(['Datos', 'Información']));
        for (const code of lab.assets) {
            expect(sheets['Datos'], `${code} in Datos`).toContain(code);
        }
        expect(sheets['Información']).toContain('LAB-110');
        expect(sheets['Información']).toContain('Andrés Mora');
        expect(sheets['Información']).toMatch(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
        test.info().annotations.push({ type: 'hoja Información', description: sheets['Información'].replace(/\n/g, ' / ') });
    });

    test('c. desde Reportes exige exactamente un espacio', async ({ browser }) => {
        const lab312 = spaceOf('LAB-312');
        const page = await (await browser.newContext({ storageState: storageState('reportes') })).newPage();
        await openReport(page, 'Espacios', 'space_detail');

        const message = page.getByText('Elige un espacio: este reporte describe un solo espacio.');
        await expect(message).toBeVisible();
        await expect(page.getByTestId('export-button')).toHaveCount(0);

        await pick(page, 'Espacio', 'LAB-110 · Laboratorio 110', 'LAB-312 · Laboratorio 312');
        await expect(message).toBeVisible();
        await expect(page.getByTestId('export-button')).toHaveCount(0);

        await unpick(page, 'Espacio', 'LAB-110 · Laboratorio 110');
        await expect(message).toHaveCount(0);
        await expect(appliedFilters(page)).toContainText('LAB-312');
        await expect(page.getByText(new RegExp(`^${lab312.assets.length} registros?$`))).toBeVisible();
        await expect(page.getByTestId('export-button')).toBeVisible();
    });

    test('d. Administrador, Visualizador y Reservante no ven "Exportar" en el espacio', async ({ browser }) => {
        const lab = spaceOf('LAB-110');
        for (const role of ['administrador', 'visualizador', 'reservante'] as const) {
            const page = await (await browser.newContext({ storageState: storageState(role) })).newPage();
            const response = await page.goto(`/espacios/${lab.id}`);
            test.info().annotations.push({ type: role, description: `HTTP ${response?.status()}` });
            if (response?.ok()) {
                await expect(page.getByRole('heading', { name: /LAB-110/ })).toBeVisible();
                await expect(page.getByTestId('export-button')).toHaveCount(0);
            }
        }
    });
});
