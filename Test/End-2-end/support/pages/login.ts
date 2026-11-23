import { expect, type Page } from '@playwright/test';
import { adminUrl, env } from '../env';

/** Signs in through the admin's login form. */
export async function logIn(page: Page): Promise<void> {
    await page.goto(adminUrl());
    await page.locator('#username').fill(env.adminUser);
    await page.locator('#login').fill(env.adminPassword);
    await page.locator('.action-login').click();
    await page.waitForLoadState('load');

    if (await page.locator('#username').count()) {
        const error = await page.locator('.message-error').allTextContents();

        throw new Error(`Admin login failed for "${env.adminUser}": ${error.join(' ').trim() || 'still on the login form'}`);
    }
    await expect(page.locator('.page-wrapper')).toBeVisible();
}
