import { defineConfig, devices } from '@playwright/test';
import { env } from './support/env';

/**
 * End-to-end tests for every module of mage-os/module-page-builder-improved, against a live store: see the
 * package README ("End-to-end tests") for the store they need and the environment variables.
 *
 * One worker: the specs share one admin user and one store.
 */
export default defineConfig({
    globalSetup: require.resolve('./global-setup'),

    testDir: './tests/',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    maxFailures: process.env.CI ? 10 : undefined,

    reporter: process.env.CI
        ? [['html', { open: 'never' }], ['list'], ['github'], ['junit', { outputFile: './test-results/junit-report.xml' }]]
        : [['list'], ['html', { open: 'never' }]],

    use: {
        baseURL: env.baseURL,
        storageState: './test-results/admin-session.json',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        // Video needs Playwright's ffmpeg, which `playwright install chromium` brings, but not on Alpine.
        video: process.env.CI ? 'retain-on-failure' : 'off',
        ignoreHTTPSErrors: true,
        actionTimeout: 15_000,
    },

    timeout: 120_000,
    expect: { timeout: 15_000 },

    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1600, height: 1000 },
                launchOptions: { executablePath: env.chromiumPath },
            },
        },
    ],
});
