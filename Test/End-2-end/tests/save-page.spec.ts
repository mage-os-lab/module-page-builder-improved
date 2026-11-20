import { test, expect } from '../support/fixtures';
import { heading, row } from '../support/content';

test.describe('Save page', () => {
    test('saves the page\'s form with the edited content', async ({ api, seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading')), 'save');

        await editor.open(cmsPage.id);
        const form = await editor.editFromTree('heading');

        await form.fill('heading_text', 'E2E saved heading');
        await form.save();
        await editor.savePage();

        expect((await api.getPage(cmsPage.id)).content).toContain('>E2E saved heading</h2>');
    });

    test('saved from full screen, reopens Page Builder in full screen', async ({ page, seedPage, editor }) => {
        // Core's CMS save redirects to .../page_id/<id>/back/edit/, and describePage() in
        // edit-with-page-builder.js takes the last "edit" segment for the form action, so the reopen flag never
        // matches that page. Remove this line once it is fixed.
        test.fail(true, 'Known bug: the reopen flag does not match the .../back/edit/ URL a CMS page save redirects to');

        const cmsPage = await seedPage(row(heading('E2E heading')), 'reopen');

        await editor.open(cmsPage.id);
        await editor.savePage();

        await editor.waitForStage();
        await expect(editor.stageWrapper).toHaveClass(/\bstage-full-screen\b/);
        expect(await page.evaluate(() => sessionStorage.getItem('mageos-pagebuilder-reopen'))).toBeNull();
    });
});
