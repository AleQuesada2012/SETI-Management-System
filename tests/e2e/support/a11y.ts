import type { Page } from '@playwright/test';

/*
 * What a browser can check about accessibility without an extra tool (WCAG 2.2, level A), as plain data a test can assert
 * on or leave as evidence in an annotation.
 */
export type Audit = {
    /** Visible controls with no accessible name. */
    unnamed: string[];
    /** Images with no alt attribute. */
    imagesWithoutAlt: string[];
    duplicatedIds: string[];
    /** "H1:Espacios", "H2:…" in page order. */
    headings: string[];
    /** The landmark elements of the page: NAV, HEADER, MAIN… */
    landmarks: string[];
    tablesWithoutHeaders: number;
    /** Pixels the page is wider than the screen (0 or less: no sideways scroll). */
    overflow: number;
};

export async function audit(page: Page): Promise<Audit> {
    return page.evaluate(() => {
        const visible = (el: Element): boolean => {
            const box = (el as HTMLElement).getBoundingClientRect();
            return box.width > 0 && box.height > 0 && getComputedStyle(el).visibility !== 'hidden';
        };
        const named = (el: Element): boolean => {
            const html = el as HTMLInputElement;
            const labelledby = el.getAttribute('aria-labelledby');

            return Boolean(
                el.getAttribute('aria-label')?.trim() ||
                    (labelledby && labelledby.split(' ').some((id) => document.getElementById(id)?.textContent?.trim())) ||
                    el.getAttribute('title')?.trim() ||
                    (html.labels && html.labels.length > 0) ||
                    el.textContent?.trim() ||
                    el.querySelector('img[alt]:not([alt=""])'),
            );
        };

        const ids = [...document.querySelectorAll('[id]')].map((el) => el.id).filter(Boolean);

        return {
            unnamed: [...document.querySelectorAll('input:not([type=hidden]), select, textarea, button, a[href], summary')]
                .filter(visible)
                .filter((el) => !named(el))
                .map((el) => el.outerHTML.slice(0, 140)),
            imagesWithoutAlt: [...document.querySelectorAll('img')].filter((img) => !img.hasAttribute('alt')).map((img) => img.outerHTML.slice(0, 140)),
            duplicatedIds: [...new Set(ids.filter((id, i) => ids.indexOf(id) !== i))],
            headings: [...document.querySelectorAll('h1, h2, h3, h4')].filter(visible).map((h) => `${h.tagName}:${h.textContent?.trim().slice(0, 40)}`),
            landmarks: [...document.querySelectorAll('main, nav, header, aside, footer, [role=main], [role=navigation]')].map((el) => el.tagName),
            tablesWithoutHeaders: [...document.querySelectorAll('table')].filter((t) => !t.querySelector('th')).length,
            overflow: document.documentElement.scrollWidth - window.innerWidth,
        };
    });
}

export type LowContrast = { text: string; ratio: number; needed: number };

/**
 * The contrast ratio (1.4.3, level AA) of every visible text against its background, composited through the
 * semi-transparent layers behind it. Returns the lowest ratio and the texts under their threshold (4.5:1, or 3:1 for
 * large text).
 */
export async function contrast(page: Page): Promise<{ lowest: number; below: LowContrast[] }> {
    return page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const ctx = canvas.getContext('2d', { willReadFrequently: true })!;
        type Color = { r: number; g: number; b: number; a: number };

        const toRgb = (css: string): Color => {
            ctx.clearRect(0, 0, 1, 1);
            ctx.fillStyle = css;
            ctx.fillRect(0, 0, 1, 1);
            const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;

            return { r, g, b, a: a / 255 };
        };
        const luminance = ({ r, g, b }: { r: number; g: number; b: number }): number => {
            const channel = (v: number): number => {
                const s = v / 255;
                return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
            };
            return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
        };
        const backgroundOf = (el: Element): Color => {
            const layers: Color[] = [];
            for (let node: Element | null = el; node; node = node.parentElement) {
                const color = toRgb(getComputedStyle(node).backgroundColor);
                if (color.a > 0) {
                    layers.push(color);
                    if (color.a >= 1) break;
                }
            }
            if (!layers.length || layers[layers.length - 1].a < 1) layers.push({ r: 255, g: 255, b: 255, a: 1 });

            let out = layers[layers.length - 1];
            for (let i = layers.length - 2; i >= 0; i--) {
                const l = layers[i];
                out = { r: l.r * l.a + out.r * (1 - l.a), g: l.g * l.a + out.g * (1 - l.a), b: l.b * l.a + out.b * (1 - l.a), a: 1 };
            }
            return out;
        };

        let lowest = 21;
        const below: { text: string; ratio: number; needed: number }[] = [];
        const seen = new Set<Element>();
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);

        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const text = node.textContent?.trim();
            const el = node.parentElement;
            if (!text || !el || seen.has(el) || ['SCRIPT', 'STYLE'].includes(el.tagName)) continue;
            seen.add(el);

            const box = el.getBoundingClientRect();
            const style = getComputedStyle(el);
            if (box.width === 0 || box.height === 0 || style.visibility === 'hidden' || style.opacity === '0') continue;

            const bg = backgroundOf(el);
            const fg = toRgb(style.color);
            const mixed = { r: fg.r * fg.a + bg.r * (1 - fg.a), g: fg.g * fg.a + bg.g * (1 - fg.a), b: fg.b * fg.a + bg.b * (1 - fg.a) };
            const l1 = luminance(mixed);
            const l2 = luminance(bg);
            const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
            const size = parseFloat(style.fontSize);
            const large = size >= 24 || (size >= 18.66 && parseInt(style.fontWeight) >= 700);
            const needed = large ? 3 : 4.5;

            lowest = Math.min(lowest, ratio);
            if (ratio < needed) below.push({ text: text.slice(0, 30), ratio: Math.round(ratio * 100) / 100, needed });
        }

        return { lowest: Math.round(lowest * 100) / 100, below };
    });
}
