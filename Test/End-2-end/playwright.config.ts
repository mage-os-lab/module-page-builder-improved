import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    globalSetup: require.resolve('./global-setup'),

    testDir: './tests/',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,

    reporter: process.env.CI
        ? [['html', { open: 'never' }], ['list'], ['github'], ['junit', { outputFile: './test-results/junit-report.xml' }]]
        : [['list'], ['html', { open: 'never' }]],

    use: {
        baseURL: process.env.BASE_URL || 'http://localhost/',
        storageState: './test-results/admin-session.json',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        ignoreHTTPSErrors: true,
        actionTimeout: 15_000,
    },

    timeout: 120_000,

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1600, height: 1000 } },
        },
    ],
});
