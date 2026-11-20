import { request, type APIRequestContext } from '@playwright/test';
import { env } from './env';

export interface CmsPage {
    id: number;
    identifier: string;
    title: string;
    content: string;
    active: boolean;
}

/**
 * Magento's REST API as the e2e admin user: CMS pages are seeded and read back through it, so the specs don't
 * build content by dragging (slow and flaky) and can check what a save stored.
 */
export class MagentoApi {
    private constructor(private readonly context: APIRequestContext) {}

    static async create(): Promise<MagentoApi> {
        const anonymous = await request.newContext({ baseURL: env.baseURL, ignoreHTTPSErrors: true });
        const response = await anonymous.post('rest/V1/integration/admin/token', {
            data: { username: env.adminUser, password: env.adminPassword },
        });

        if (!response.ok()) {
            throw new Error(`Admin REST token for "${env.adminUser}" failed: ${response.status()} ${await response.text()}`);
        }
        const token = (await response.json()) as string;

        await anonymous.dispose();

        return new MagentoApi(await request.newContext({
            baseURL: env.baseURL,
            ignoreHTTPSErrors: true,
            extraHTTPHeaders: { Authorization: `Bearer ${token}` },
        }));
    }

    async createPage(page: { identifier: string; title: string; content: string }): Promise<CmsPage> {
        return this.call<CmsPage>('POST', 'cmsPage', {
            page: { ...page, active: true, page_layout: '1column' },
        });
    }

    async getPage(id: number): Promise<CmsPage> {
        return this.call<CmsPage>('GET', `cmsPage/${id}`);
    }

    async deletePage(id: number): Promise<void> {
        await this.call('DELETE', `cmsPage/${id}`);
    }

    async dispose(): Promise<void> {
        await this.context.dispose();
    }

    private async call<T>(method: string, path: string, data?: unknown): Promise<T> {
        const response = await this.context.fetch(`rest/V1/${path}`, { method, data });

        if (!response.ok()) {
            throw new Error(`${method} ${path}: ${response.status()} ${await response.text()}`);
        }

        return (await response.json()) as T;
    }
}
