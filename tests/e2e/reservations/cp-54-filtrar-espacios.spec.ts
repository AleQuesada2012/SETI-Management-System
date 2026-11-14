import { expect, test } from '@playwright/test';
import { storageState } from '../support/accounts';

// CP-54 · Filtrar espacios por atributos (RF_54, CU_RF_54). Demo data: five Laboratorios (LAB-316 "En mantenimiento"),
// one Sala and one Oficina.

test.describe('CP-54 · Filtrar espacios por atributos', () => {
    test.use({ storageState: storageState('reservante') });

    test('a. filtra por tipo', async ({ page }) => {
        await page.goto('/espacios');
        await page.getByTestId('filter-space_type').getByRole('checkbox', { name: 'Laboratorio', exact: true }).check();

        await expect(page.getByText('5 espacios', { exact: true })).toBeVisible();
        await expect(page.getByTestId('active-filters')).toContainText('Laboratorio');
        await expect(page.getByRole('row').filter({ hasText: 'SAL-01' })).toHaveCount(0);
    });

    test('b. combina tipo y estado', async ({ page }) => {
        await page.goto('/espacios');
        await page.getByTestId('filter-space_type').getByRole('checkbox', { name: 'Laboratorio', exact: true }).check();
        await page.getByTestId('filter-space_status').getByRole('checkbox', { name: 'En mantenimiento', exact: true }).check();

        await expect(page.getByText('1 espacio', { exact: true })).toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'LAB-316' })).toBeVisible();
    });

    test('c. avisa cuando ningún espacio cumple los criterios', async ({ page }) => {
        await page.goto('/espacios');
        await page.getByTestId('filter-space_type').getByRole('checkbox', { name: 'Oficina', exact: true }).check();
        await page.getByTestId('filter-space_status').getByRole('checkbox', { name: 'En mantenimiento', exact: true }).check();

        await expect(page.getByTestId('spaces-empty')).toHaveText('No se encontraron espacios con los criterios aplicados.');
    });

    test('d. "Limpiar filtros" restaura el listado completo', async ({ page }) => {
        await page.goto('/espacios');
        await page.getByTestId('filter-space_type').getByRole('checkbox', { name: 'Sala', exact: true }).check();
        await expect(page.getByText('1 espacio', { exact: true })).toBeVisible();

        await page.getByTestId('clear-filters').click();

        await expect(page.getByText('7 espacios', { exact: true })).toBeVisible();
        await expect(page.getByTestId('active-filters')).toHaveCount(0);
    });
});
