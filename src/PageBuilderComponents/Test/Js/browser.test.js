/**
 * Runs the browser tests (Test/Js/*.html) in headless Chrome, in real time so ResizeObserver and animation
 * frames run, at the widths each one needs. Each page shows "RESULT: OK" or "RESULT: FAIL ...".
 *
 * Needs puppeteer, e.g. in its official image, from the module root:
 *     docker run --rm -e NODE_PATH=/home/pptruser/node_modules -v "$PWD":/module:ro \
 *         ghcr.io/puppeteer/puppeteer:24.10.0 node /module/Test/Js/browser.test.js
 */
'use strict';

const path = require('path');
const puppeteer = require('puppeteer');

const PAGES = [
    {file: 'preview-frame.html', widths: [1280]},
    {file: 'empty-preview.html', widths: [1280]}
];

(async () => {
    const browser = await puppeteer.launch({args: ['--no-sandbox', '--allow-file-access-from-files']});
    let failed = false;
    try {
        for (const {file, widths} of PAGES) {
            for (const width of widths) {
                const page = await browser.newPage();
                await page.setViewport({width, height: 900});
                await page.goto('file://' + path.resolve(__dirname, file));
                await page.waitForFunction(
                    () => document.getElementById('result').textContent !== 'RESULT: PENDING',
                    {timeout: 15000}
                );
                const result = await page.$eval('#result', (element) => element.textContent);
                console.log(`${file} @${width}px: ${result}`);
                failed = failed || !result.startsWith('RESULT: OK');
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
    process.exitCode = failed ? 1 : 0;
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
