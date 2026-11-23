import { test, expect } from '../support/fixtures';
import { heading, row, text } from '../support/content';

test.describe('Panel', () => {
    test('the inserter shows core\'s sections as tiles, and search narrows them', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading')), 'inserter');

        await editor.open(cmsPage.id);

        await expect(editor.sections.locator('.menu-section-title h4')).toContainText(['Layout', 'Elements', 'Media']);
        for (const label of ['Row', 'Columns', 'Text', 'Heading']) {
            await expect(editor.tile(label)).toBeVisible();
        }

        // Core's search runs on keyup.
        await editor.search.pressSequentially('Head');
        await expect(editor.sectionList).toBeHidden();
        await expect(editor.searchResults.locator('.content-type-label')).toHaveText(['Heading']);

        await editor.page.locator('.search-content-types .close').click();
        await expect(editor.sectionList).toBeVisible();
        await expect(editor.tile('Row')).toBeVisible();
    });

    test('collapses to a strip and remembers it after a reload', async ({ page, seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading')), 'collapse');

        await editor.open(cmsPage.id);
        await expect(editor.collapseButton).toHaveAttribute('aria-expanded', 'true');

        await editor.collapseButton.click();
        await expect(editor.collapseButton).toHaveAttribute('aria-expanded', 'false');
        await expect(editor.stageWrapper).toHaveClass(/\bmageos-panel-collapsed\b/);

        await editor.open(cmsPage.id);
        await expect(editor.stageWrapper).toHaveClass(/\bmageos-panel-collapsed\b/);
        expect(await page.evaluate(() => JSON.parse(localStorage.getItem('mageos-pagebuilder-panel') || '{}')))
            .toMatchObject({ collapsed: true });

        // Leave the panel open for the next tests in this browser context.
        await editor.collapseButton.click();
        await expect(editor.stageWrapper).not.toHaveClass(/\bmageos-panel-collapsed\b/);
    });

    test('dragging a tile onto a row adds the element to the stage and the tree', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(text('<p>E2E text</p>')), 'drag');

        await editor.open(cmsPage.id);
        await expect(editor.stageElements('heading')).toHaveCount(0);

        await editor.dragTileTo('Heading', editor.stage.locator('[data-content-type="row"] [data-element="inner"]').first());

        await expect(editor.stageElements('heading')).toHaveCount(1);
        await editor.openTree();
        await expect(editor.treeNode('heading')).toHaveCount(1);
    });
});
