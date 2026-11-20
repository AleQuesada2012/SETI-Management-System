import { expect, type Page } from '@playwright/test';

/*
 * The peer review of the assets cases (CP-01 to CP-31) runs on the local demo data. David's cases name LAB-101 and the
 * PRUEBA- tags of the deployed site; here the equivalents are LAB-110 (id 1, managed by Mario Solís) and the A-00xx codes.
 * Each run adds its own assets, with a tag that carries the run, so two runs never collide on the unique tag.
 */
export const RUN = Date.now().toString(36);

/** The space of the assets, managed by Mario Solís (administrador). */
export const ASSET_SPACE = { id: 1, label: 'LAB-110 · Laboratorio 110' };

/** A demo asset in LAB-110 that nobody edits or deletes. */
export const DEMO_ASSET = { id: 1, code: 'A-0001' };

export type NewAsset = {
    /** The tag (placa), unique per run. */
    tag: string;
    name?: string;
    model?: string;
    condition?: 'Buen estado (Verde)' | 'Regular (Ámbar)' | 'Dañado (Rojo)';
};

export function tag(suffix: string): string {
    return `PRUEBA-DZ-${RUN}-${suffix}`;
}

/** Registers an asset in LAB-110 with the required fields only, and returns its id and code. */
export async function registerAsset(page: Page, asset: NewAsset): Promise<{ id: number; code: string }> {
    await page.goto('/activos/nuevo');
    await page.getByTestId('asset-space').selectOption({ label: ASSET_SPACE.label });
    await page.getByLabel('Tipo de activo').selectOption({ index: 1 });
    await page.getByLabel(/Condición/).selectOption({ label: asset.condition ?? 'Buen estado (Verde)' });
    await page.getByLabel('Placa').fill(asset.tag);
    if (asset.name) await page.getByLabel('Nombre', { exact: true }).fill(asset.name);
    if (asset.model) await page.getByLabel('Modelo').fill(asset.model);
    await page.getByTestId('create-asset-submit').click();

    await expect(page).toHaveURL(/\/activos\/\d+$/);
    const toast = await page.getByText(/^El activo A-\d+ se registró correctamente\.$/).first().innerText();

    return { id: Number(page.url().split('/').pop()), code: toast.match(/A-\d+/)![0] };
}

/** Registers an event (Pérdida, Recuperación, Baja…) on the asset's page and waits for its notice. */
export async function registerEvent(page: Page, asset: { id: number; code: string }, kind: 'Pérdida' | 'Recuperación' | 'Baja', description: string): Promise<void> {
    const done = { 'Pérdida': 'la pérdida', 'Recuperación': 'la recuperación', 'Baja': 'la baja' }[kind];
    await page.goto(`/activos/${asset.id}`);
    await page.getByTestId('register-event-button').click();
    await page.getByTestId('event-kind').selectOption({ label: kind });
    await page.getByTestId('event-description').fill(description);
    await page.getByTestId('save-event').click();
    await expect(page.getByText(`Se registró ${done} del activo ${asset.code}.`).first()).toBeVisible();
}
