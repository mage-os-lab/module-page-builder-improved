import { test, expect } from '../support/fixtures';
import { heading, row, text, twoColumns } from '../support/content';
import type { Page } from '@playwright/test';

/** The storefront columns' boxes: side by side when they share a top. */
async function columnBoxes(page: Page): Promise<{ top: number; left: number; width: number }[]> {
    return page.locator('.column.main [data-content-type="column"]').evaluateAll((columns) => columns.map((column) => {
        const box = column.getBoundingClientRect();

        return { top: Math.round(box.top), left: Math.round(box.left), width: Math.round(box.width) };
    }));
}

test.describe('Layout settings', () => {
    test('Columns: the gap and columns per line set on Desktop apply on the storefront', async ({ seedPage, editor, storefront, page }) => {
        const cmsPage = await seedPage(twoColumns(), 'columns');

        await editor.open(cmsPage.id);
        const form = await editor.editFromTree('column-group');

        await expect(form.tab('Layout')).toBeVisible();
        await form.tab('Layout').click();
        await form.select('mageos_column_gap', '24px');
        await form.select('mageos_columns_per_line', '1');
        await form.save();
        await editor.savePage();

        await storefront.open(cmsPage.identifier, 1440);
        const group = page.locator('.column.main [data-content-type="column-group"]');

        await expect(group).toHaveAttribute('data-mageos-layout', 'columns');
        await expect(group.locator('[data-content-type="column-line"]')).toHaveCSS('column-gap', '24px');
        const [first, second] = await columnBoxes(page);

        // One column per line: the second wraps under the first, a row gap (the column gap) below it.
        expect(second.left).toBe(first.left);
        expect(second.top).toBeGreaterThan(first.top);
    });

    test('Columns: a setting made in the Tablet view applies at tablet widths only', async ({ seedPage, editor, storefront, page }) => {
        const cmsPage = await seedPage(twoColumns(), 'columns-tablet');

        await editor.open(cmsPage.id);
        await editor.switchViewport('tablet');
        const form = await editor.editFromTree('column-group');

        await form.tab('Layout').click();
        await form.select('mageos_columns_per_line', '1');
        await form.save();
        await editor.switchViewport('desktop');
        await editor.savePage();

        await storefront.open(cmsPage.identifier, 900);
        let [first, second] = await columnBoxes(page);

        expect(second.top, 'stacked at 900 px').toBeGreaterThan(first.top);

        await storefront.open(cmsPage.identifier, 1440);
        [first, second] = await columnBoxes(page);
        expect(second.top, 'side by side at 1440 px').toBe(first.top);
        expect(second.left).toBeGreaterThan(first.left);
    });

    test('Columns: saving them raises no restricted HTML warning', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(twoColumns(), 'columns-validator');

        await editor.open(cmsPage.id);
        await editor.savePage();

        await expect(editor.messages().filter({ hasText: 'restricted elements' })).toHaveCount(0);
    });

    test('Row: "Side by side" content layout applies on the storefront, and the form reads it back', async ({ seedPage, editor, storefront, page }) => {
        const cmsPage = await seedPage(row(heading('E2E heading'), text('<p>E2E text</p>')), 'row-layout');

        await editor.open(cmsPage.id);
        let form = await editor.editFromTree('row');

        await form.tab('Content Layout').click();
        await form.select('mageos_layout', 'row');
        await form.select('mageos_gap', '16px');
        await form.save();
        await editor.savePage();

        await editor.open(cmsPage.id);
        form = await editor.editFromTree('row');
        await form.tab('Content Layout').click();
        await expect(form.field('mageos_layout')).toHaveValue('row');
        await expect(form.field('mageos_gap')).toHaveValue('16px');
        await form.close();

        await storefront.open(cmsPage.identifier, 1440);
        const inner = page.locator('.column.main [data-content-type="row"] > [data-element="inner"]');

        await expect(inner).toHaveCSS('display', 'flex');
        await expect(inner).toHaveCSS('flex-direction', 'row');
        await expect(inner).toHaveCSS('column-gap', '16px');
    });
});
