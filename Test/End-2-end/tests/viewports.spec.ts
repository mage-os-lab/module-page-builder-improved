import { test, expect } from '../support/fixtures';
import { heading, row } from '../support/content';

test.describe('Viewports', () => {
    test('the switcher has a Tablet view between Desktop and Mobile, at 1023 px', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading')), 'viewports');

        await editor.open(cmsPage.id);

        await expect(editor.page.locator('.page-builder-viewport')).toHaveCount(3);
        expect(await editor.page.locator('.page-builder-viewport').evaluateAll(
            (buttons) => buttons.map((button) => button.className.match(/\b(\w+)-switcher\b/)?.[1]),
        )).toEqual(['desktop', 'tablet', 'mobile']);

        await editor.switchViewport('tablet');
        await expect(editor.canvas).toHaveCSS('width', '1023px');

        await editor.switchViewport('mobile');
        await expect(editor.canvas).toHaveCSS('width', '768px');

        await editor.switchViewport('desktop');
    });
});
