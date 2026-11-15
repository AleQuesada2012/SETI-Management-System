import { expect, type Locator, type Page } from '@playwright/test';
import { isoDate, shownDate, weekday } from './dates';

/** Spaces of the demo data. LAB-110 is managed by Mario Solís (administrador), with capacity 30. */
export const LAB_110 = { id: 1, code: 'LAB-110', label: 'LAB-110 · Laboratorio 110' };
export const LAB_312 = { id: 2, code: 'LAB-312', label: 'LAB-312 · Laboratorio 312' };

export type Slot = {
    /** YYYY-MM-DD, what the date inputs take. */
    date: string;
    /** DD/MM/YYYY, how the pages show it. */
    shownDate: string;
    start: string;
    end: string;
};

const HOURS_A_DAY = 14; // one-hour blocks from 07:00 to 21:00
const FIRST_DAY = 30; // past the demo reservations, the farthest of which is in 21 days

/**
 * Each call hands out the next one-hour block inside the allowed hours (Monday to Saturday, 07:00 to 22:00), from 30
 * days ahead, so the cases of a run never book the same block twice. The first block is random, so a worker that
 * restarts after a failure does not reuse the blocks of the one before.
 */
let nextBlock = Math.floor(Math.random() * 2000);

export function freeSlot(): Slot {
    let block = nextBlock++;

    if (weekday(isoDate(FIRST_DAY + Math.floor(block / HOURS_A_DAY))) === 0) {
        block = (Math.floor(block / HOURS_A_DAY) + 1) * HOURS_A_DAY;
        nextBlock = block + 1;
    }

    const date = isoDate(FIRST_DAY + Math.floor(block / HOURS_A_DAY));
    const hour = 7 + (block % HOURS_A_DAY);
    const pad = (n: number): string => String(n).padStart(2, '0');

    return { date, shownDate: shownDate(date), start: `${pad(hour)}:00`, end: `${pad(hour + 1)}:00` };
}

/**
 * A reservation title that is unique to this run, for the cases that look it up in the bell: with E2E_KEEP_DB=1 the
 * bell still holds the notifications of earlier runs.
 */
const RUN = Date.now().toString(36);

export function uniqueTitle(label: string): string {
    return `PRUEBA E2E · ${label} · ${RUN}`;
}

/** Picks a space in a select whose options read "CODE · Name". */
export async function selectSpace(select: Locator, code: string): Promise<void> {
    const value = await select.locator('option', { hasText: new RegExp(`^${code} · `) }).getAttribute('value');
    expect(value, `the space ${code} is not offered`).not.toBeNull();
    await select.selectOption(value!);
}

/** Fills "Nueva reserva"; the caller submits it. Returns the space's capacity from its hint. */
export async function fillReservationForm(
    page: Page,
    slot: Slot,
    { title, attendees = 5, space = LAB_110.code }: { title: string; attendees?: number; space?: string },
): Promise<number> {
    await page.goto('/reservas/nueva');

    await selectSpace(page.getByTestId('reservation-space'), space);
    const hint = page.getByText(/Capacidad: \d+ personas/);
    await expect(hint).toBeVisible();
    const capacity = Number((await hint.textContent())!.match(/Capacidad: (\d+)/)![1]);

    await page.getByLabel('Fecha').fill(slot.date);
    await page.getByTestId('reservation-start').selectOption(slot.start);
    await page.getByTestId('reservation-end').selectOption(slot.end);
    await page.getByLabel('Motivo').fill(title);
    await page.getByLabel('Cantidad de personas').fill(String(attendees));
    await page.getByLabel('Notas').fill('Reserva creada por las pruebas automatizadas.');

    return capacity;
}

/** Books the slot through "Reservar espacio" and returns the path of the new reservation's detail. */
export async function createReservation(page: Page, slot: Slot, title: string, space = LAB_110.code): Promise<string> {
    await fillReservationForm(page, slot, { title, space });
    await page.getByTestId('reservation-submit').click();
    await expect(page).toHaveURL(/\/reservas\/\d+$/);

    return new URL(page.url()).pathname;
}

/**
 * Cancels a reservation through its detail page: "Cancelar" and then "Cancelar reserva" (RF_50). The page goes back
 * to the calendar on the reservation's day and confirms it with a toast.
 */
export async function cancelReservation(page: Page, detailPath: string, reason?: string): Promise<void> {
    if (new URL(page.url()).pathname !== detailPath) {
        await page.goto(detailPath);
    }

    await page.getByTestId('reservation-cancel').click();
    await expect(page.getByText('¿Cancelar esta reserva?')).toBeVisible();

    if (reason) {
        await page.getByLabel('Motivo (opcional)').fill(reason);
    }

    await page.getByTestId('confirm-cancel').click();
    await expect(page).toHaveURL(/\/reservas\?mes=/);
    await expect(page.getByText(/Se canceló la reserva de .* y el horario quedó libre\./)).toBeVisible();
}

/** Opens the bell in the top bar (RF_47, RF_48) and returns its menu. */
export async function openNotifications(page: Page): Promise<Locator> {
    await page.goto('/reservas');
    await page.getByTestId('notification-bell').click();
    const menu = page.getByRole('menu');
    await expect(menu).toBeVisible();

    return menu;
}

/**
 * Waits for a notification in the bell and returns it. Reservation notifications go through the queue
 * (ShouldQueueAfterCommit), so they arrive a few seconds after the booking: reopen the bell until it shows up.
 */
export async function waitForNotification(page: Page, ...texts: string[]): Promise<Locator> {
    let entry!: Locator;

    await expect(async () => {
        const menu = await openNotifications(page);
        entry = texts.reduce((found, text) => found.filter({ hasText: text }), menu.getByRole('menuitem'));
        await expect(entry).toBeVisible({ timeout: 1_000 });
    }).toPass({ timeout: 30_000 });

    return entry;
}
