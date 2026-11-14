import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

/**
 * Resets the local database to the demo data (README: `migrate:fresh --seed`), so every run starts from the same
 * spaces, assets, maintenance and reservations. E2E_KEEP_DB=1 skips it.
 */
export default function globalSetup(): void {
    if (process.env.E2E_KEEP_DB === '1') {
        return;
    }

    const repositoryRoot = fileURLToPath(new URL('../../', import.meta.url));

    execFileSync('docker', ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'migrate:fresh', '--seed', '--force'], {
        cwd: repositoryRoot,
        stdio: 'inherit',
    });
}
