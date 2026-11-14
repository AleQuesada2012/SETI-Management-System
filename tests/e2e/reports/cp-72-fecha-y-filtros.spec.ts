import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { exportAndRead, livewire, pick, settle } from '../support/qa';
import { openReport } from '../support/reports';

// CP-72 · Mostrar fecha de generación y filtros aplicados (RF_72, CU_RF_72).

test.describe('CP-72 · Mostrar fecha de generación y filtros aplicados', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. con filtros: el PDF muestra la fecha y hora y el filtro Vigente', async ({ page }) => {
        await openReport(page, 'Activos', 'warranties');
        await pick(page, 'Vigencia de la garantía', 'Vigente');
        const { text } = await exportAndRead(page, 'PDF');
        expect(text).toMatch(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
        expect(text).toMatch(/Vigencia de la garantía: Vigente/);
    });

    test('b. sin filtros: el PDF y el XLSX indican que contienen la totalidad de los registros', async ({ page }) => {
        await openReport(page, 'Activos', 'warranties');
        await pick(page, 'Vigencia de la garantía', 'Vigente');
        await livewire(page, () => page.getByRole('button', { name: 'Limpiar filtros' }).click());
        const pdf = await exportAndRead(page, 'PDF');
        const xlsx = await exportAndRead(page, 'Excel (XLSX)');
        expect(pdf.text.replace(/\s+/g, ' ')).toContain('ninguno (contiene la totalidad de los registros)');
        expect(xlsx.text).toContain('ninguno (contiene la totalidad de los registros)');
    });
});
