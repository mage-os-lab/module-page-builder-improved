/**
 * The store under test, from the environment. Defaults match the CI job (.github/workflows/end-2-end.yml).
 */
function withSlash(url: string): string {
    return url.endsWith('/') ? url : url + '/';
}

export const env = {
    /** Storefront base URL; the admin is under it, at ADMIN_PATH. */
    baseURL: withSlash(process.env.BASE_URL || 'http://localhost/'),
    adminPath: (process.env.ADMIN_PATH || 'admin').replace(/^\/+|\/+$/g, ''),
    adminUser: process.env.ADMIN_USER || 'e2e_admin',
    adminPassword: process.env.ADMIN_PASSWORD || 'E2eAdminPassword1',
    /** A Chromium to use instead of Playwright's own, e.g. /usr/bin/chromium on Alpine. */
    chromiumPath: process.env.E2E_CHROMIUM_PATH || undefined,
};

/**
 * An admin URL without a secret key: the store needs admin/security/use_form_key set to 0 (global-setup.ts checks).
 *
 * @param path E.g. 'cms/page/edit/page_id/12/'.
 */
export function adminUrl(path = ''): string {
    return new URL(`${env.adminPath}/${path.replace(/^\/+/, '')}`, env.baseURL).toString();
}

/** A storefront URL, e.g. a CMS page's identifier. */
export function storefrontUrl(path = ''): string {
    return new URL(path.replace(/^\/+/, ''), env.baseURL).toString();
}
