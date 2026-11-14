import { defineConfig, devices } from '@playwright/test';

/*
 * End-to-end tests of the test plan's cases (CP-xx), run in a real browser against the local Docker stack
 * (http://localhost:8000). Every run starts from the demo data: global-setup.ts runs `migrate:fresh --seed` in the app
 * container, so it empties your local database first (set E2E_KEEP_DB=1 to skip it).
 *
 *   docker compose up -d                                   # from the repository root
 *   cd tests/e2e && npm ci && npx playwright install chromium
 *   npx playwright test                                    # every case
 *   npx playwright test cp-46                              # one case
 *   npx playwright show-report                             # HTML report with a screenshot of every test
 *
 * One worker: the cases share the demo accounts and spaces.
 */
export default defineConfig({
    testDir: '.',
    globalSetup: './global-setup.ts',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        locale: 'es-CR',
        timezoneId: 'America/Costa_Rica',
        // The views mark elements with data-test="…" (the Pest tests use the same hooks).
        testIdAttribute: 'data-test',
        screenshot: 'on',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.ts/ },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
            dependencies: ['setup'],
        },
    ],
});
