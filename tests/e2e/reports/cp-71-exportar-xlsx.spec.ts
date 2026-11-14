import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { exportAndRead, pick, previewColumn } from '../support/qa';
import { openReport } from '../support/reports';

// CP-71 · Exportar reportes en formato XLSX (RF_71, CU_RF_71).

test.describe('CP-71 · Exportar reportes en formato XLSX', () => {
    test.use({ storageState: storageState('reportes') });

    test('a y b. el XLSX tiene la hoja Datos con los registros del filtro y la hoja Información', async ({ page }) => {
        await openReport(page, 'Usuarios', 'users');
        await pick(page, 'Rol', 'Reservante');
        const emails = await previewColumn(page, 'Correo');

        const { download, text } = await exportAndRead(page, 'Excel (XLSX)');
        expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
        const [datos, informacion] = [text.split('### hoja Información')[0], text.split('### hoja Información')[1] ?? ''];
        expect(text).toContain('### hoja Datos');
        expect(text).toContain('### hoja Información');
        for (const header of ['Nombre', 'Correo', 'Roles', 'Estado de cuenta', 'Invitación pendiente']) {
            expect(datos).toContain(header);
        }
        for (const email of emails) {
            expect(datos).toContain(email);
        }
        expect(datos.match(/@seti\.test/g)?.length).toBe(emails.length);
        expect(informacion).toContain('Andrés Mora');
        expect(informacion).toContain('Reservante');
        expect(informacion).toMatch(/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
        test.info().annotations.push({ type: 'hoja Información', description: informacion.trim() });
    });
});
