import { test, expect } from '../support/fixtures';
import { heading, row } from '../support/content';
import { adminUrl } from '../support/env';
import { CmsPageGrid } from '../support/pages/cms-grid';

test.describe('Edit with Page Builder', () => {
    test('a new CMS page opens in Page Builder with the improved panel', async ({ page, editor }) => {
        await page.goto(adminUrl('cms/page/new/#edit-with-page-builder'), { waitUntil: 'networkidle' });

        const panel = page.locator('.mageos-panel');
        await expect(panel).toBeVisible({ timeout: 60_000 });

        await expect(panel.getByRole('tab', { name: 'Add element' })).toHaveAttribute('aria-selected', 'true');
        await expect(panel.getByRole('tab', { name: 'Current tree' })).toBeVisible();
        await expect(editor.stageWrapper).toHaveClass(/\bstage-full-screen\b/);
    });

    test('the CMS page grid action opens Page Builder in full screen', async ({ page, seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading')), 'grid');
        const grid = new CmsPageGrid(page);

        await grid.open();
        const gridRow = await grid.findRow(cmsPage.titleWord, cmsPage.identifier);
        const action = await grid.rowAction(gridRow, 'Edit with Page Builder');

        await expect(action).toHaveAttribute('href', new RegExp(`/cms/page/edit/page_id/${cmsPage.id}/.*#edit-with-page-builder$`));
        await action.click();

        await editor.waitForStage();
        await expect(page).toHaveURL(new RegExp(`/cms/page/edit/page_id/${cmsPage.id}/`));
        await expect(editor.stageWrapper).toHaveClass(/\bstage-full-screen\b/);
        await expect(editor.fullScreenButton).toHaveAttribute('aria-label', 'Close Full Screen');
        // The request is consumed: a reload doesn't open full screen again.
        await expect.poll(() => page.evaluate(() => window.location.hash)).toBe('');

        await grid.open();
        await grid.clearSearch();
    });

    test('a plain edit shows the stage inline; its button opens full screen with the panel', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading')), 'inline');

        await editor.open(cmsPage.id, { fullScreen: false });

        await expect(editor.stageWrapper).not.toHaveClass(/\bstage-full-screen\b/);
        await expect(editor.stageElements('heading')).toHaveText('E2E heading');

        await editor.page.getByRole('button', { name: 'Edit with Page Builder' }).click();
        await editor.waitForStage();
        await expect(editor.stageWrapper).toHaveClass(/\bstage-full-screen\b/);
        await expect(editor.tab('add')).toHaveAttribute('aria-selected', 'true');
        await expect(editor.savePageButton).toBeVisible();
    });
});
