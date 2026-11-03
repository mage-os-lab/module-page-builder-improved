/**
 * Runs the browser test (Test/Js/layout.html) in headless Chrome at a desktop, a tablet and a mobile width. The
 * page shows "RESULT: OK" or "RESULT: FAIL ...".
 *
 * It needs core Page Builder's admin JavaScript, RequireJS and Underscore: from a vendor/ (and Magento's lib/web)
 * above the module, or PAGE_BUILDER_WEB (vendor/magento/module-page-builder/view/adminhtml/web) and LIB_WEB, or
 * the requirejs and underscore npm packages. And puppeteer, e.g. in its official image:
 *     docker run --rm -e NODE_PATH=/home/pptruser/node_modules -v /path/to/magento:/mnt/magento:ro \
 *         -e PAGE_BUILDER_WEB=/mnt/magento/vendor/magento/module-page-builder/view/adminhtml/web \
 *         -e LIB_WEB=/mnt/magento/lib/web \
 *         -v "$PWD":/module:ro ghcr.io/puppeteer/puppeteer:24.10.0 node /module/Test/Js/browser.test.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const {pathToFileURL} = require('url');
const puppeteer = require('puppeteer');

const WIDTHS = [1280, 900, 390];

/**
 * @param {String} candidate - a path relative to each directory above the module
 * @returns {String|undefined} the first that exists
 */
function findAbove(candidate) {
    for (let dir = path.resolve(__dirname, '../..'); dir !== path.dirname(dir); dir = path.dirname(dir)) {
        if (fs.existsSync(path.join(dir, candidate))) {
            return path.join(dir, candidate);
        }
    }

    return undefined;
}

/**
 * @returns {{requirejs: String, underscore: String}} files
 */
function findLibraries() {
    const requirejs = findAbove('lib/web/requirejs/require.js') ||
            findAbove('vendor/magento/magento2-base/lib/web/requirejs/require.js'),
        lib = process.env.LIB_WEB || requirejs && path.dirname(path.dirname(requirejs));

    if (lib) {
        return {requirejs: path.join(lib, 'requirejs/require.js'), underscore: path.join(lib, 'underscore.js')};
    }

    return {
        requirejs: require.resolve('requirejs/require.js'),
        underscore: require.resolve('underscore/underscore-umd.js')
    };
}

(async () => {
    const pageBuilder = process.env.PAGE_BUILDER_WEB ||
        findAbove('vendor/magento/module-page-builder/view/adminhtml/web');

    if (!pageBuilder) {
        throw new Error('Core Page Builder not found: set PAGE_BUILDER_WEB');
    }
    const libraries = findLibraries();
    const url = pathToFileURL(path.resolve(__dirname, 'layout.html')) +
        '?pb=' + encodeURIComponent(pathToFileURL(pageBuilder)) +
        '&requirejs=' + encodeURIComponent(pathToFileURL(libraries.requirejs)) +
        '&underscore=' + encodeURIComponent(pathToFileURL(libraries.underscore).href.replace(/\.js$/, ''));
    const browser = await puppeteer.launch({args: ['--no-sandbox', '--allow-file-access-from-files']});
    let failed = false;

    try {
        for (const width of WIDTHS) {
            const page = await browser.newPage();

            await page.setViewport({width, height: 900});
            await page.goto(url);
            await page.waitForFunction(
                () => document.getElementById('result').textContent !== 'RESULT: PENDING',
                {timeout: 15000}
            );
            const result = await page.$eval('#result', (element) => element.textContent);

            console.log(`layout.html @${width}px: ${result}`);
            if (process.env.SHOW_CSS && width === WIDTHS[0]) {
                console.log(await page.evaluate(() => window.MASTER_CSS));
            }
            failed = failed || !result.startsWith('RESULT: OK');
            await page.close();
        }
    } finally {
        await browser.close();
    }
    process.exitCode = failed ? 1 : 0;
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
