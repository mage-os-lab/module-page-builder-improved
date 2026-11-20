import { expect, type Locator, type Page } from '@playwright/test';
import { adminUrl } from '../env';

/** The CMS pages grid (cms_page_listing). */
export class CmsPageGrid {
    readonly grid: Locator;
    readonly search: Locator;

    constructor(readonly page: Page) {
        this.grid = page.locator('[data-role="grid-wrapper"]');
        this.search = page.getByRole('textbox', { name: 'Search by keyword' }).first();
    }

    async open(): Promise<void> {
        await this.page.goto(adminUrl('cms/page/'));
        await expect(this.grid).toBeVisible({ timeout: 60_000 });
    }

    /**
     * Finds a page's row by a word of its title. The grid's keyword search is MySQL's full text search in
     * boolean mode, so the word has to be plain letters and digits: a hyphen would read as "without".
     */
    async findRow(titleWord: string, identifier: string): Promise<Locator> {
        await this.search.fill(titleWord);
        await this.search.press('Enter');
        const row = this.grid.locator('tbody tr.data-row').filter({ hasText: identifier });

        await expect(row).toHaveCount(1, { timeout: 30_000 });

        return row;
    }

    /** A row action's link, after opening the row's "Select" menu. */
    async rowAction(row: Locator, label: string): Promise<Locator> {
        await row.locator('.action-select').click();
        const action = row.locator('.action-menu-item', { hasText: new RegExp(`^\\s*${label}\\s*$`) });

        await expect(action).toBeVisible();

        return action;
    }

    /** Clears the keyword search, which the grid keeps for the admin user. */
    async clearSearch(): Promise<void> {
        const clear = this.page.getByRole('button', { name: 'Clear all' });

        if (await clear.isVisible()) {
            await clear.click();
        }
    }
}
