/*
 * Dates as the site sees them: America/Costa_Rica, whatever the clock of the machine running the tests.
 */
const TIMEZONE = 'America/Costa_Rica';

/** YYYY-MM-DD of today plus `days`, what a date input takes. */
export function isoDate(days = 0): string {
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: TIMEZONE }).format(new Date());
    const date = new Date(`${today}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}

/** DD/MM/YYYY, how the pages show a date. */
export function shownDate(iso: string): string {
    const [y, m, d] = iso.split('-');

    return `${d}/${m}/${y}`;
}

/** 0 = Sunday … 6 = Saturday. */
export function weekday(iso: string): number {
    return new Date(`${iso}T12:00:00Z`).getUTCDay();
}
