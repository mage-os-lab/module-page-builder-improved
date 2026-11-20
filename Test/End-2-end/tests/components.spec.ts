import { test, expect } from '../support/fixtures';
import { hero, row } from '../support/content';

const EXAMPLES = ['Hero', 'Cards', 'USP List', 'Image & Text', 'Call to Action'];

test.describe('Components', () => {
    test('the example components are tiles under Add Content', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(hero({ title: 'E2E hero' })), 'tiles');

        await editor.open(cmsPage.id);

        const addContent = editor.page.locator('#menu-section-add_content');

        for (const label of EXAMPLES) {
            await expect(addContent.locator('.content-type-label', { hasText: new RegExp(`^${label}$`) })).toBeVisible();
        }
    });

    test('the stage previews a Hero with the storefront theme, and follows its form', async ({ seedPage, editor }) => {
        const cmsPage = await seedPage(row(hero({ eyebrow: 'E2E eyebrow', title: 'E2E hero title' })), 'hero-preview');

        await editor.open(cmsPage.id);

        const preview = editor.stage.locator('[data-content-type="mageos_hero"] iframe[data-mageos-pbc-preview]').contentFrame();

        await expect(preview.locator('.mageos-pbc-hero__title')).toHaveText('E2E hero title');
        await expect(preview.locator('.mageos-pbc__eyebrow')).toHaveText('E2E eyebrow');
        expect(await preview.locator('link[rel="stylesheet"]').evaluateAll(
            (links) => links.map((link) => (link as HTMLLinkElement).href),
        )).toEqual(expect.arrayContaining([
            expect.stringMatching(/\/frontend\/.*\/css\/styles-m\.css$/),
            expect.stringMatching(/MageOS_PageBuilderComponentsExamples\/css\/components\.css$/),
        ]));

        const form = await editor.editFromTree('mageos_hero');

        await expect(form.title).toHaveText('Edit Hero');
        await form.fill('title', 'E2E edited hero');
        await form.save();

        await expect(preview.locator('.mageos-pbc-hero__title')).toHaveText('E2E edited hero');
    });

    test('a Hero saved from the form renders on the storefront, and its values survive the round trip', async ({ seedPage, editor, storefront, page }) => {
        // Characters the widget directive can't hold as they are: the value codec has to carry them.
        const title = 'Say "hi" to {{our}} 100% {new} range';
        const buttonText = 'Shop now & save';
        const link = 'https://example.com/e2e?a=1&b=2';
        const cmsPage = await seedPage(row(hero({ title: 'E2E hero' })), 'hero-storefront');

        await editor.open(cmsPage.id);
        let form = await editor.editFromTree('mageos_hero');

        await form.fill('title', title);
        await form.fill('primaryLink[default]', link);
        await form.fill('primaryText', buttonText);
        await form.save();
        await editor.savePage();

        await editor.open(cmsPage.id);
        form = await editor.editFromTree('mageos_hero');
        await expect(form.field('title')).toHaveValue(title);
        await expect(form.field('primaryLink[default]')).toHaveValue(link);
        await expect(form.field('primaryText')).toHaveValue(buttonText);
        await form.close();

        await storefront.open(cmsPage.identifier);
        const heroElement = storefront.content().locator('.mageos-pbc-hero');

        await expect(heroElement.locator('.mageos-pbc-hero__title')).toHaveText(title);
        await expect(heroElement.locator('.mageos-pbc__button', { hasText: buttonText })).toHaveAttribute('href', link);
        expect(await storefront.stylesheets()).toEqual(expect.arrayContaining([
            expect.stringMatching(/MageOS_PageBuilderComponentsExamples\/css\/components\.css$/),
        ]));
        await expect(page.locator('.mageos-pbc-hero')).toHaveCount(1);
    });
});
