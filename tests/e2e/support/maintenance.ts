import { expect, type Locator, type Page } from '@playwright/test';

/*
 * The "Mantenimientos" card of an asset's page (components/assets/asset-maintenance). Demo assets A-0001 to A-0021 sit
 * in LAB-110, managed by Mario Solís (administrador). A-0017 (projector) has a scheduled preventive maintenance and
 * A-0028 (air conditioner, LAB-312) a repair in progress.
 */
export function maintenanceCard(page: Page): Locator {
    return page.getByTestId('asset-maintenance');
}

/** The rows of the card's table. */
export function maintenanceRows(page: Page): Locator {
    return page.getByTestId('maintenance-row');
}

/** Fills and sends "Registrar mantenimiento" (a maintenance already done, RF_10 and RF_26). */
export async function registerMaintenance(
    page: Page,
    { kind, date, performedBy, description }: { kind: 'Preventivo' | 'Correctivo'; date: string; performedBy: string; description: string },
): Promise<void> {
    await page.getByTestId('register-maintenance-button').click();
    // Wait for the server to open the form with its default ("Correctivo") before choosing, or the reply overwrites it.
    await expect(page.getByTestId('maintenance-kind')).toHaveValue('corrective');
    await page.getByTestId('maintenance-kind').selectOption({ label: kind });
    await page.getByTestId('maintenance-performed-on').fill(date);
    await page.getByTestId('maintenance-performed-by').fill(performedBy);
    await page.getByTestId('maintenance-description').fill(description);
    await page.getByTestId('save-maintenance').click();
}

/** The browser's own validation message of an input (min, max, required), empty when the value is valid. */
export async function validationMessage(input: Locator): Promise<string> {
    return input.evaluate((element) => (element as HTMLInputElement).validationMessage);
}

/** Registers a damage through "Registrar evento", so the asset can be repaired (RF_13, used by RF_11). */
export async function registerDamage(page: Page, date: string, description: string): Promise<void> {
    await page.getByTestId('register-event-button').click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByTestId('event-kind').selectOption({ label: 'Daño' });
    await page.getByTestId('event-occurred-on').fill(date);
    await page.getByTestId('event-description').fill(description);
    await page.getByTestId('event-condition').selectOption({ label: 'Dañado' });
    await page.getByTestId('save-event').click();
    await expect(page.getByText(/Se registró el daño del activo/)).toBeVisible();
}
