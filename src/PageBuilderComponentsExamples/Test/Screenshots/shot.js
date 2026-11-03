/**
 * Photographs each component of the rendered page (render.php) in headless Chrome: every one at 1280 px, and
 * some at 390 px too, as <out>/<name>.png and <name>-mobile.png. Fails on a missing image or stylesheet.
 *
 *     node shot.js <page dir> <out dir>
 */
'use strict';

const path = require('path');
const puppeteer = require('puppeteer');

const [pageDir, outDir] = process.argv.slice(2);
const MOBILE = ['hero', 'cards', 'image-text', 'call-to-action-split'];

(async () => {
    const browser = await puppeteer.launch({args: ['--no-sandbox', '--allow-file-access-from-files']});

    try {
        for (const [width, suffix] of [[1280, ''], [390, '-mobile']]) {
            const page = await browser.newPage();
            const failures = [];

            page.on('requestfailed', (request) => failures.push(request.url()));
            await page.setViewport({width, height: 900, deviceScaleFactor: 1});
            await page.goto('file://' + path.resolve(pageDir, 'index.html'), {waitUntil: 'load'});
            if (failures.length) {
                throw new Error('Not loaded: ' + failures.join(', '));
            }
            const hasStyles = await page.evaluate(
                () => getComputedStyle(document.querySelector('.mageos-pbc__container')).maxWidth !== 'none'
            );
            if (!hasStyles) {
                throw new Error('components.css did not apply');
            }
            // Lazy images would load while an element is photographed, after its size was taken.
            await page.addStyleTag({content: 'html { scrollbar-width: none; }'});
            await page.evaluate(() => Promise.all(Array.from(document.images, (image) => {
                image.loading = 'eager';

                return image.decode();
            })));

            for (const element of await page.$$('[data-shot]')) {
                const name = await element.evaluate((node) => node.getAttribute('data-shot'));

                if (suffix && !MOBILE.includes(name)) {
                    continue;
                }
                await element.screenshot({path: path.join(outDir, name + suffix + '.png')});
                console.log(name + suffix + '.png');
            }
            await page.close();
        }
    } finally {
        await browser.close();
    }
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
