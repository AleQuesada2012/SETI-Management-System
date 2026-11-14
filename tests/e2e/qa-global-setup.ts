import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

/**
 * The Security and Reports cases: the demo data (migrate:fresh --seed) plus support/qa-data.php (custom roles, a
 * two-factor account, finished maintenance, components, warranties, a decommission and a loss). E2E_KEEP_DB=1 skips it.
 */
export default function qaGlobalSetup(): void {
    if (process.env.E2E_KEEP_DB === '1') {
        return;
    }

    const repositoryRoot = fileURLToPath(new URL('../../', import.meta.url));
    const artisan = (...args: string[]) =>
        execFileSync('docker', ['compose', 'exec', '-T', 'app', 'php', 'artisan', ...args], { cwd: repositoryRoot, stdio: 'inherit' });

    artisan('migrate:fresh', '--seed', '--force');
    artisan('tinker', `--execute=require 'tests/e2e/support/qa-data.php';`);
    artisan('cache:clear');
}
