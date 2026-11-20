import { expect, test } from '@playwright/test';

const adminPath = process.env.ADMIN_PATH || 'admin';

test.describe('Edit with Page Builder', () => {
    test('a new CMS page opens in Page Builder with the improved panel', async ({ page }) => {
        await page.goto(`${adminPath}/cms/page/new/#edit-with-page-builder`, { waitUntil: 'networkidle' });

        const panel = page.locator('.mageos-panel');
        await expect(panel).toBeVisible({ timeout: 60_000 });

        await expect(panel.getByRole('tab', { name: 'Add element' })).toHaveAttribute('aria-selected', 'true');
        await expect(panel.getByRole('tab', { name: 'Current tree' })).toBeVisible();
    });
});
