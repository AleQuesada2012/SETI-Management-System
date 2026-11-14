import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { pick, previewColumn } from '../support/qa';
import { openReport } from '../support/reports';

// CP-77 · Generar reporte de componentes de equipos (RF_77, CU_RF_77). qa-data.php: A-0001 has 2 components, A-0003 one.

test.describe('CP-77 · Generar reporte de componentes de equipos', () => {
    test.use({ storageState: storageState('reportes') });

    test('a. cada fila muestra el activo y el componente, agrupados por activo', async ({ page }) => {
        await openReport(page, 'Activos', 'components');
        for (const column of ['Código de activo', 'Placa', 'Activo', 'Componente', 'Especificaciones', 'Número de serie', 'Cantidad', 'Notas']) {
            await expect(page.getByRole('columnheader', { name: column, exact: true })).toBeVisible();
        }
        expect(await previewColumn(page, 'Código de activo')).toEqual(['A-0001', 'A-0001', 'A-0003']);
    });

    test('b. el filtro de activo solo ofrece activos con componentes', async ({ page }) => {
        await openReport(page, 'Activos', 'components');
        await pick(page, 'Activo');
        const options = await page.locator('details').filter({ hasText: 'Activo' }).first().getByRole('checkbox').evaluateAll((boxes) =>
            boxes.map((box) => box.getAttribute('label') ?? ''),
        );
        test.info().annotations.push({ type: 'opciones', description: options.join(' | ') });
        expect(options.length).toBe(2);
        await pick(page, 'Activo', options.find((o) => o.startsWith('A-0001'))!);
        expect(await previewColumn(page, 'Componente')).toEqual(expect.arrayContaining(['Memoria RAM', 'Disco SSD']));
        expect((await previewColumn(page, 'Código de activo')).length).toBe(2);
    });
});
