import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate } from '../support/dates';

// CP-08 · Filtrado de activos por métricas (RF_08; CU_RF_08), peer review by Diego Zamora. In the local demo data the Cisco
// assets are in LAIMI and the Dell ones in LAB-110 and LAB-312, so the case's LAB-101 is LAB-110.

test.describe('CP-08 · Filtrado de activos por métricas', () => {
    test.use({ storageState: storageState('visualizador') });

    const filters = (page: import('@playwright/test').Page) => page.getByTestId('asset-filters');

    test('a. filtra por un criterio', async ({ page }) => {
        await page.goto('/activos');
        await filters(page).getByLabel('Marca').selectOption({ label: 'Cisco' });

        await expect(page.getByRole('row').filter({ hasText: 'Cisco' }).first()).toBeVisible();
        const total = await page.getByRole('row').count();
        expect(await page.getByRole('row').filter({ hasText: 'Cisco' }).count(), 'solo Cisco').toBe(total - 1);
        await expect(page.getByRole('row').filter({ hasText: 'LAIMI' }).first()).toBeVisible();
    });

    test('b. combina marca, espacio y color de la condición', async ({ page }) => {
        await page.goto('/activos');
        await filters(page).getByLabel('Marca').selectOption({ label: 'Dell' });
        const dell = await page.getByText(/^\d+ activos$/).innerText();

        await filters(page).getByLabel('Espacio').selectOption({ label: 'LAB-110 · Laboratorio 110' });
        await filters(page).getByLabel('Color de la condición').selectOption({ label: 'Verde' });
        await expect(page.getByText(/^\d+ activos$/)).not.toHaveText(dell);

        const rows = page.getByRole('row').filter({ hasText: 'Dell' });
        const total = await page.getByRole('row').count();
        expect(await rows.filter({ hasText: 'LAB-110' }).count()).toBe(total - 1);
        test.info().annotations.push({ type: 'contador', description: `Dell: ${dell} → Dell + LAB-110 + Verde: ${await page.getByText(/^\d+ activos$/).innerText()}` });
    });

    test('c. un rango de fechas invertido se rechaza y no lista nada', async ({ page }) => {
        await page.goto('/activos');
        await filters(page).getByLabel('Adquirido desde').fill(isoDate(0));
        await filters(page).getByLabel('Adquirido hasta').fill(isoDate(-30));

        const callout = page.getByTestId('invalid-filters');
        await expect(callout).toContainText('Revisa los filtros: hay valores que no son válidos.');
        await expect(callout).toContainText('La fecha final no puede ser anterior a la inicial.');
        await expect(page.getByRole('row')).toHaveCount(0);
    });

    test('d. sin coincidencias, y e. limpiar filtros restablece el inventario', async ({ page }) => {
        await page.goto('/activos');
        const all = await page.getByText(/^\d+ activos$/).innerText();

        await filters(page).getByLabel('Marca').selectOption({ label: 'Cisco' });
        await filters(page).getByLabel('Espacio').selectOption({ label: 'LAB-110 · Laboratorio 110' });
        await expect(page.getByTestId('empty-inventory')).toContainText('No existe ningún activo que cumpla con los filtros seleccionados.');

        await page.getByTestId('clear-filters').click();
        await expect(page.getByText(all)).toBeVisible();
        await expect(filters(page).getByLabel('Marca')).toHaveValue('');
    });
});
