import { test, expect } from '../support/fixtures';
import { heading, row } from '../support/content';

test.describe('Edit form', () => {
    test('is a tabbed sidebar, and saving it updates the stage', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(heading('E2E heading')), 'form');

        await editor.open(cmsPage.id);
        const form = await editor.editFromTree('heading');

        await expect(form.title).toHaveText('Edit Heading');
        await expect(form.tabs).toHaveText(['Content', 'Advanced']);
        await expect(form.tab('Content')).toHaveAttribute('aria-selected', 'true');
        await expect(form.field('heading_text')).toBeVisible();

        await form.tab('Advanced').click();
        await expect(form.tab('Advanced')).toHaveAttribute('aria-selected', 'true');
        await expect(form.fieldset('general')).toHaveClass(/\bmageos-form-tab-hidden\b/);
        await expect(form.field('heading_text')).toBeHidden();
        await expect(form.field('css_classes')).toBeVisible();

        await form.tab('Content').click();
        await form.fill('heading_text', 'E2E edited heading');
        await form.save();

        await expect(editor.stageElements('heading')).toHaveText('E2E edited heading');
    });
});
