import { expect, type Page, test } from '@playwright/test';
import { storageState } from '../support/accounts';
import { isoDate } from '../support/dates';

// CP-59 · Consultar ocupación de espacios por período (RF_59, CU_RF_59). Between a week ago and tomorrow the demo
// data has two reservations in LAB-110 (2 h and 1 h 30 min), one in LAIMI and one in LAB-312.

async function period(page: Page, from: string, to: string): Promise<void> {
    await page.goto('/reservas/ocupacion');
    await page.getByLabel('Fecha inicial').fill(from);
    await page.getByLabel('Fecha final').fill(to);
}

test.describe('CP-59 · Consultar ocupación de espacios por período', () => {
    test.use({ storageState: storageState('reservante') });

    test('a. muestra los espacios con reservas en el período y cuántas tuvo cada uno', async ({ page }) => {
        await period(page, isoDate(-7), isoDate(1));

        await expect(page.getByText('3 espacios con reservas', { exact: true })).toBeVisible();
        const first = page.getByRole('row').nth(1);
        await expect(first).toContainText('LAB-110 · Laboratorio 110');
        await expect(first.getByRole('cell').nth(1)).toHaveText('2');
        await expect(first.getByRole('cell').nth(2)).toHaveText('3 h 30 min');
    });

    test('b. cambia el orden a "Menos reservas primero"', async ({ page }) => {
        await period(page, isoDate(-7), isoDate(1));
        await page.getByLabel('Orden').selectOption({ label: 'Menos reservas primero' });

        await expect(page.getByRole('row').nth(1).getByRole('cell').nth(1)).toHaveText('1');
        await expect(page.getByRole('row').last()).toContainText('LAB-110');
    });

    test('c. indica cuando ningún espacio tuvo reservas en el período', async ({ page }) => {
        await period(page, isoDate(400), isoDate(410));

        await expect(page.getByText('Ningún espacio registra reservas en el período seleccionado.')).toBeVisible();
    });

    test('d. avisa cuando el período no es válido', async ({ page }) => {
        await period(page, isoDate(10), isoDate(1));

        await expect(page.getByText('El período seleccionado no es válido')).toBeVisible();
    });
});
