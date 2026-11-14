import { defineConfig, devices } from '@playwright/test';

/*
 * Alejandro's cases of the test plan: Security (CP-00-1 to CP-00-4) and Reports (CP-60 to CP-84 without Jose's
 * CP-65 and CP-79 and Diego's CP-74), in a real browser against the local Docker stack.
 *
 *   cd tests/e2e && npx playwright test -c qa.config.ts            # every case
 *   npx playwright test -c qa.config.ts cp-63                      # one case
 *
 * It resets the local database (qa-global-setup.ts). E2E_BASE_URL changes the address (default http://localhost:8000).
 */
export default defineConfig({
    testDir: '.',
    globalSetup: './qa-global-setup.ts',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report-qa' }], ['json', { outputFile: 'qa-results.json' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        locale: 'es-CR',
        timezoneId: 'America/Costa_Rica',
        testIdAttribute: 'data-test',
        acceptDownloads: true,
        screenshot: 'on',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.ts/ },
        {
            name: 'qa',
            testMatch: [/security\/.*\.spec\.ts/, /reports\/cp-(6[0-46-9]|7[0-35-8]|74|8[0-4])-.*\.spec\.ts/, /reports\/accesibilidad-.*\.spec\.ts/],
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
            dependencies: ['setup'],
        },
    ],
});
