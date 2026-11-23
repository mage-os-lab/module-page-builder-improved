import { chromium, type FullConfig } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { adminUrl, env } from './support/env';
import { logIn } from './support/pages/login';

const STORAGE_STATE = path.resolve(__dirname, 'test-results/admin-session.json');

/**
 * Sign in to the admin once and hand every spec the session: the admin login is rate limited and its form key
 * rotates, so logging in per test is slow and fails for reasons unrelated to the test.
 *
 * Checks the store first, and says what to fix: the admin must take the e2e user's password without two-factor
 * authentication, and must open admin URLs without a secret key, as the specs open edit pages by their URL.
 */
async function globalSetup(_config: FullConfig): Promise<void> {
    const browser = await chromium.launch({ executablePath: env.chromiumPath });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    try {
        const response = await page.goto(adminUrl(), { waitUntil: 'networkidle' }).catch((error: Error) => {
            throw new Error(`The admin at ${adminUrl()} is not reachable (BASE_URL, ADMIN_PATH): ${error.message}`);
        });

        if (!response || response.status() >= 400) {
            throw new Error(`The admin at ${adminUrl()} answered ${response?.status()} (BASE_URL, ADMIN_PATH).`);
        }
        await logIn(page);
        if (page.url().includes('tfa')) {
            throw new Error('The admin asks for two-factor authentication: disable Magento_TwoFactorAuth on the test store.');
        }

        await page.goto(adminUrl('cms/page/'));
        if (!page.url().includes('cms/page')) {
            throw new Error(
                `Opening ${adminUrl('cms/page/')} ended on ${page.url()}: admin URLs need a secret key. Run `
                + '"bin/magento config:set admin/security/use_form_key 0" on the test store.',
            );
        }

        fs.mkdirSync(path.dirname(STORAGE_STATE), { recursive: true });
        await context.storageState({ path: STORAGE_STATE });
    } finally {
        await browser.close();
    }
}

export default globalSetup;
