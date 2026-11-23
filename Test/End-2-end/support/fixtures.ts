import { test as base, expect } from '@playwright/test';
import { MagentoApi, type CmsPage } from './api';
import { PageBuilderEditor } from './pages/editor';
import { StorefrontPage } from './pages/storefront';

export interface SeededPage extends CmsPage {
    /** A word of the title, plain letters and digits, unique to this page: what the grid's search finds it by. */
    titleWord: string;
}

interface TestFixtures {
    /** Creates a CMS page with the given Page Builder content; it is deleted after the test. */
    seedPage: (content: string, name?: string) => Promise<SeededPage>;
    editor: PageBuilderEditor;
    storefront: StorefrontPage;
    /** Fails the test on a script error in the page, or on a module's static file that didn't load. */
    errorGuard: void;
    knownBugScreenshot: void;
}

interface WorkerFixtures {
    api: MagentoApi;
}

export const test = base.extend<TestFixtures, WorkerFixtures>({
    api: [async ({}, use) => {
        const api = await MagentoApi.create();

        await use(api);
        await api.dispose();
    }, { scope: 'worker' }],

    seedPage: async ({ api }, use, testInfo) => {
        const created: number[] = [];

        await use(async (content, name = 'page') => {
            const titleWord = `e2e${Date.now().toString(36)}${testInfo.workerIndex}${created.length}`;
            const page = await api.createPage({
                identifier: `e2e-${name}-${titleWord}`,
                title: `E2E ${name} ${titleWord}`,
                content,
            });

            created.push(page.id);

            return { ...page, titleWord };
        });
        for (const id of created) {
            await api.deletePage(id).catch((error: Error) => console.warn(`Could not delete CMS page ${id}: ${error.message}`));
        }
    },

    editor: async ({ page }, use) => {
        await use(new PageBuilderEditor(page));
    },

    storefront: async ({ page }, use) => {
        await use(new StorefrontPage(page));
    },

    errorGuard: [async ({ page }, use) => {
        const errors: string[] = [];

        page.on('pageerror', (error) => errors.push(`script error: ${error.message}`));
        page.on('response', (response) => {
            if (response.status() >= 400 && /\/static\/.*\/MageOS_PageBuilder/.test(response.url())) {
                errors.push(`${response.status()} ${response.url()}`);
            }
        });
        await use();
        expect(errors, 'errors in the page').toEqual([]);
    }, { auto: true }],

    /**
     * A screenshot of a known bug's test (test.fail()) as it fails. Playwright's screenshot: 'only-on-failure'
     * takes one only when a test doesn't end as expected, which leaves these with no picture of the bug.
     */
    knownBugScreenshot: [async ({ page }, use, testInfo) => {
        await use();
        if (testInfo.expectedStatus === 'failed' && testInfo.status === 'failed' && !page.isClosed()) {
            // In test-results/<test>/, next to the error context, and in the HTML report.
            const path = testInfo.outputPath('known-bug.png');

            await page.screenshot({ path });
            await testInfo.attach('known-bug', { path, contentType: 'image/png' });
        }
    }, { auto: true }],
});

export { expect };
