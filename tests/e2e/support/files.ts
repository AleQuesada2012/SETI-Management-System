import { crc32, deflateSync } from 'node:zlib';

/*
 * Files for the upload cases, built in memory so the repository carries no binaries. Every helper returns what
 * Playwright's setInputFiles() takes.
 */
export type TestFile = { name: string; mimeType: string; buffer: Buffer };

const MB = 1024 * 1024;

/** A tiny but valid PDF, padded with `extraBytes` of zeros after the end marker when a size is needed. */
export function pdf(name: string, extraBytes = 0): TestFile {
    const body = '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 100]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n';

    return { name, mimeType: 'application/pdf', buffer: Buffer.concat([Buffer.from(body), Buffer.alloc(extraBytes)]) };
}

/** A PDF bigger than the 20 MB a document may weigh. */
export function oversizedPdf(name: string): TestFile {
    return pdf(name, 21 * MB);
}

export function text(name: string): TestFile {
    return { name, mimeType: 'text/plain', buffer: Buffer.from('archivo de texto de prueba') };
}

export function svg(name: string): TestFile {
    return { name, mimeType: 'image/svg+xml', buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>') };
}

function chunk(type: string, data: Buffer): Buffer {
    const head = Buffer.alloc(4);
    head.writeUInt32BE(data.length);
    const tail = Buffer.alloc(4);
    tail.writeUInt32BE(crc32(Buffer.concat([Buffer.from(type), data])));

    return Buffer.concat([head, Buffer.from(type), data, tail]);
}

/**
 * A valid PNG. With `noise` the pixels are random and the data is stored uncompressed, so the file weighs about
 * width × height × 3 bytes: 2400 × 1600 is 11.5 MB, more than the 10 MB a photo may weigh.
 */
export function png(name: string, width = 40, height = 30, noise = false): TestFile {
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    header.set([8, 2, 0, 0, 0], 8);

    const rows: Buffer[] = [];
    for (let y = 0; y < height; y++) {
        const pixels = noise ? Buffer.from(Array.from({ length: width * 3 }, () => Math.floor(Math.random() * 256))) : Buffer.alloc(width * 3, 150);
        rows.push(Buffer.concat([Buffer.from([0]), pixels]));
    }

    const data = deflateSync(Buffer.concat(rows), { level: noise ? 0 : 6 });

    return {
        name,
        mimeType: 'image/png',
        buffer: Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', header), chunk('IDAT', data), chunk('IEND', Buffer.alloc(0))]),
    };
}

/** A PNG over the 10 MB a photo may weigh. */
export function oversizedPng(name: string): TestFile {
    return png(name, 2400, 1600, true);
}
