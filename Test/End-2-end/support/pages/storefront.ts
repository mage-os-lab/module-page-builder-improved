import { type Locator, type Page } from '@playwright/test';
import { storefrontUrl } from '../env';

/** A CMS page on the storefront. */
export class StorefrontPage {
    constructor(readonly page: Page) {}

    async open(identifier: string, width = 1440): Promise<void> {
        await this.page.setViewportSize({ width, height: 900 });
        await this.page.goto(storefrontUrl(identifier));
    }

    /** The CMS page's content, without the theme's header and footer. */
    content(): Locator {
        return this.page.locator('.column.main, #maincontent').first();
    }

    stylesheets(): Promise<string[]> {
        return this.page.locator('link[rel="stylesheet"]').evaluateAll(
            (links) => links.map((link) => (link as HTMLLinkElement).href),
        );
    }
}
