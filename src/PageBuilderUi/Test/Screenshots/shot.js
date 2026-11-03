/**
 * Screenshots of the harness page (index.html), one per scenario: node shot.js <out dir> [scenario...]
 *
 * Run through run.sh, which compiles the styles and links the files the page needs. A scenario's `check` runs
 * in the page after its clicks (`click`, at 100 ms intervals, or `fastClick`, at once) and keys (`focus`, then
 * `keys`), and returns an error message, or ''. Exits with 1 when a page has a script error,
 * a missing file or a failed check.
 */
'use strict';

const http = require('http');
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const root = __dirname;
const out = path.resolve(process.argv[2] || path.join(root, 'shots'));
const only = process.argv.slice(3);
const TYPES = {'.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml',
    '.woff2': 'font/woff2', '.woff': 'font/woff', '.png': 'image/png'};

const SCENARIOS = [
    {name: 'add', query: 'tab=add&toolbar=heading-1', hover: '#heading-1 .pagebuilder-heading'},
    {name: 'add-search', query: 'tab=add', type: 'im'},
    {name: 'tree', query: 'tab=tree', hover: '.mageos-tree-node:nth-child(3)'},
    {name: 'tree-select', query: 'tab=tree', click: '.mageos-tree-node:nth-child(2) .mageos-tree-name'},
    {name: 'stage-hover', query: 'tab=tree', hover: '#text-6 .pagebuilder-text'},
    {name: 'tree-empty', query: 'tab=tree&empty=1'},
    {name: 'collapsed', query: 'tab=tree&collapsed=1'},
    {name: 'fullscreen', query: 'tab=add&fullscreen=1'},
    {name: 'fullscreen-tree', query: 'tab=tree&fullscreen=1&toolbar=heading-1'},
    {name: 'fullscreen-collapsed', query: 'tab=add&fullscreen=1&collapsed=1'},
    {name: 'templates-menu', query: 'tab=add&fullscreen=1', click: '.mageos-header-menu-toggle',
        check: `getComputedStyle(document.querySelector('.mageos-header-menu-items')).display === 'none'
            ? 'the menu did not open' : ''`},
    {name: 'templates-apply', query: 'tab=add', click: ['.mageos-header-menu-toggle', '.mageos-header-menu-items li:first-child button'],
        check: `harnessLog.join() !== 'apply template' ? 'logged: ' + harnessLog.join()
            : getComputedStyle(document.querySelector('.mageos-header-menu-items')).display !== 'none' ? 'the menu stayed open' : ''`},
    // The page's Save, not the modal's that comes first in the document.
    {name: 'save-page', query: 'tab=add', click: '.mageos-save-page',
        check: `harnessLog.join() !== 'page saved' ? 'logged: ' + harnessLog.join() : ''`},
    {name: 'templates-escape', query: 'tab=add', click: '.mageos-header-menu-toggle', keys: ['Escape'],
        check: `getComputedStyle(document.querySelector('.mageos-header-menu-items')).display !== 'none' ? 'the menu stayed open'
            : !document.activeElement.classList.contains('mageos-header-menu-toggle') ? 'focus on ' + document.activeElement.className
            : harnessMenuHandlers() ? 'handlers left: ' + harnessMenuHandlers() : ''`},
    // Toggled fast: one set of handlers, which a click elsewhere then drops with the menu.
    {name: 'templates-toggle', query: 'tab=add', fastClick: ['.mageos-header-menu-toggle', '.mageos-header-menu-toggle', '.mageos-header-menu-toggle'],
        check: `getComputedStyle(document.querySelector('.mageos-header-menu-items')).display === 'none' ? 'the menu did not open'
            : harnessMenuHandlers() !== 2 ? 'handlers: ' + harnessMenuHandlers() : (document.querySelector('.pagebuilder-stage').click(),
                getComputedStyle(document.querySelector('.mageos-header-menu-items')).display !== 'none' ? 'a click elsewhere left it open'
                : harnessMenuHandlers() ? 'handlers left: ' + harnessMenuHandlers() : '')`},
    {name: 'fullscreen-button', query: 'tab=add', focus: '.mageos-fullscreen-toggle', keys: ['Enter'],
        check: `(function () {
            var button = document.querySelector('.mageos-fullscreen-toggle');
            if (button.tagName !== 'BUTTON' || button.type !== 'button') { return 'not a button'; }
            if (!harness.pageBuilder.isFullScreen()) { return 'Enter did not open full screen'; }
            if (button.getAttribute('aria-label') !== 'Close Full Screen') { return 'label: ' + button.getAttribute('aria-label'); }
            return button.getBoundingClientRect().width !== 36 ? 'width: ' + button.getBoundingClientRect().width : '';
        }())`},
    {name: 'panel-tabs-keys', query: 'tab=add', focus: '.mageos-panel-tab[data-tab="add"]', keys: ['ArrowRight'],
        check: `(function () {
            var tabs = document.querySelectorAll('.mageos-panel-tab'), pane = document.querySelector('.mageos-panel-tree');
            if (document.activeElement !== tabs[1]) { return 'focus on ' + document.activeElement.className; }
            if (harness.panel.activeTab() !== 'tree' || getComputedStyle(pane).display === 'none') { return 'tree not open'; }
            if ([tabs[0].tabIndex, tabs[1].tabIndex].join() !== '-1,0') { return 'tabindex: ' + [tabs[0].tabIndex, tabs[1].tabIndex]; }
            if (tabs[1].getAttribute('aria-controls') !== pane.id || pane.getAttribute('aria-labelledby') !== tabs[1].id) { return 'tab and pane not tied'; }
            return document.querySelector('.mageos-panel-pane-add').getAttribute('aria-labelledby') !== tabs[0].id ? 'add pane not labelled' : '';
        }())`},
    {name: 'tree-list', query: 'tab=tree', click: '.mageos-tree-node:nth-child(1) .mageos-tree-name',
        check: `(function () {
            if (document.querySelector('.mageos-panel-tree [role="tree"], .mageos-panel-tree [role="treeitem"]')) { return 'tree roles left'; }
            if (document.querySelector('.mageos-tree-node:nth-child(1) .mageos-tree-toggle').getAttribute('aria-expanded') !== 'true') { return 'toggle not expanded'; }
            if (document.querySelector('.mageos-tree-node:nth-child(3) .mageos-tree-toggle').hasAttribute('aria-expanded')) { return 'leaf toggle expanded'; }
            return document.querySelectorAll('.mageos-tree-label[aria-current="true"]').length !== 1 ? 'selected node not current' : '';
        }())`},
    {name: 'edit-with-page-builder', query: 'tab=add#edit-with-page-builder',
        check: `!harness.pageBuilder.isFullScreen() ? 'not full screen' : location.hash ? 'hash kept: ' + location.hash : ''`},
    {name: 'reopen-after-save', query: 'tab=add&reopen=1',
        check: `!harness.pageBuilder.isFullScreen() ? 'not full screen'
            : sessionStorage.getItem('mageos-pagebuilder-reopen') ? 'flag kept' : ''`},
    // A save that failed its validation left the flag on another page.
    {name: 'reopen-other-page', query: 'tab=add&reopen=other',
        check: `harness.pageBuilder.isFullScreen() ? 'full screen on a flag another page left' : ''`},
    {name: 'edit-form', query: 'tab=tree&form=1',
        check: `(function () {
            var tabs = [].map.call(document.querySelectorAll('.mageos-form-tab-button'), (tab) => tab.textContent).join();
            if (tabs !== 'Content,Advanced') { return 'tabs: ' + tabs; }
            if (document.querySelector('.mageos-form-tab-button.has-error')) { return 'a tab is marked invalid'; }
            if (!document.querySelector('[data-index="advanced"]').classList.contains('mageos-form-tab-hidden')) { return 'Advanced shows'; }
            return document.querySelector('[data-index="general"] .admin__field-label').getBoundingClientRect().width < 400 ? 'labels not stacked' : '';
        }())`},
    {name: 'edit-form-advanced', query: 'tab=tree&form=1', click: '.mageos-form-tab-button[data-tab="advanced"]',
        check: `(function () {
            var wrap = document.querySelector('.mageos-pb-edit-modal .modal-inner-wrap');
            if (!document.querySelector('[data-index="general"]').classList.contains('mageos-form-tab-hidden')) { return 'Content shows'; }
            if (document.querySelectorAll('[data-index="advanced"] .admin__field').length !== 5) { return 'Advanced not opened'; }
            if (document.querySelector('.mageos-form-tab-button.has-error')) { return 'a tab is marked invalid'; }
            return wrap.scrollWidth > wrap.clientWidth ? 'scrolls sideways: ' + wrap.scrollWidth + ' > ' + wrap.clientWidth : '';
        }())`},
    {name: 'edit-form-keys', query: 'tab=tree&form=1', focus: '.mageos-form-tab-button[data-tab="general"]', keys: ['End'],
        check: `(function () {
            var tabs = document.querySelectorAll('.mageos-form-tab-button'), advanced = document.querySelector('[data-index="advanced"]');
            if (document.activeElement !== tabs[1]) { return 'focus on ' + document.activeElement.className; }
            if (advanced.classList.contains('mageos-form-tab-hidden')) { return 'Advanced not open'; }
            if ([tabs[0].tabIndex, tabs[1].tabIndex].join() !== '-1,0') { return 'tabindex: ' + [tabs[0].tabIndex, tabs[1].tabIndex]; }
            if (tabs[1].getAttribute('aria-controls') !== advanced.id || advanced.getAttribute('role') !== 'tabpanel'
                || advanced.getAttribute('aria-labelledby') !== tabs[1].id) { return 'tab and fieldset not tied'; }
            return document.querySelector('[data-index="appearance_fieldset"]').hasAttribute('role') ? 'hidden fieldset is a tab panel' : '';
        }())`},
    {name: 'edit-form-enter', query: 'tab=tree&form=1', focus: '.mageos-form-tab-button[data-tab="advanced"]', keys: ['Enter', 'ArrowLeft'],
        check: `document.activeElement.getAttribute('data-tab') !== 'general' ? 'focus on ' + document.activeElement.getAttribute('data-tab')
            : document.querySelector('[data-index="general"]').classList.contains('mageos-form-tab-hidden') ? 'Content not open' : ''`},
    // The error turns up after the click on Advanced.
    {name: 'edit-form-error', query: 'tab=tree&form=1&formerror=1', click: '.mageos-form-tab-button[data-tab="advanced"]', wait: 2000,
        check: `(function () {
            var marked = [].map.call(document.querySelectorAll('.mageos-form-tab-button.has-error'), (tab) => tab.textContent).join();
            if (marked !== 'Content') { return 'marked: ' + marked; }
            return document.querySelector('.mageos-form-tab-button.active').textContent !== 'Content' ? 'did not switch to Content' : '';
        }())`}
];

const server = http.createServer(function (request, response) {
    const file = path.join(root, decodeURIComponent(request.url.split('?')[0]));

    fs.readFile(file, function (error, content) {
        if (error) {
            response.writeHead(404);
            response.end();

            return;
        }
        response.writeHead(200, {'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream'});
        response.end(content);
    });
});

(async function () {
    await new Promise((resolve) => server.listen(0, resolve));
    const base = 'http://localhost:' + server.address().port + '/index.html?';
    const browser = await puppeteer.launch({args: ['--no-sandbox']});

    try {
        fs.mkdirSync(out, {recursive: true});
        for (const scenario of SCENARIOS) {
            if (only.length && !only.includes(scenario.name)) {
                continue;
            }
            const page = await browser.newPage();
            const errors = [];

            page.on('pageerror', (error) => errors.push(error.message));
            page.on('response', function (response) {
                if (response.status() >= 400 && !response.url().endsWith('/favicon.ico')) {
                    errors.push(response.status() + ' ' + response.url());
                }
            });
            await page.setViewport({width: 1440, height: 860});
            await page.goto(base + scenario.query, {waitUntil: 'networkidle0'});
            try {
                await page.waitForSelector('body.harness-ready', {timeout: 5000});
            } catch (error) {
                console.log(scenario.name, 'ERRORS: the page did not start | ' + errors.join(' | '));
                process.exitCode = 1;
                await page.close();
                continue;
            }
            // The tree is rate limited: let it settle before clicking in it.
            await new Promise((resolve) => setTimeout(resolve, 300));
            if (scenario.type) {
                await page.type('#search-content-types-input', scenario.type);
            }
            for (const selector of [].concat(scenario.click || [])) {
                await page.click(selector);
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            if (scenario.fastClick) {
                await page.evaluate(function (selectors) {
                    selectors.forEach((selector) => document.querySelector(selector).click());
                }, scenario.fastClick);
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            if (scenario.focus) {
                await page.focus(scenario.focus);
            }
            for (const key of scenario.keys || []) {
                await page.keyboard.press(key);
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            if (scenario.hover) {
                await page.hover(scenario.hover);
            }
            await new Promise((resolve) => setTimeout(resolve, scenario.wait || 900));
            if (scenario.check) {
                const failed = await page.evaluate(scenario.check);

                if (failed) {
                    errors.push('check: ' + failed);
                }
            }
            await page.screenshot({path: path.join(out, scenario.name + '.png')});
            console.log(scenario.name, errors.length ? 'ERRORS: ' + errors.join(' | ') : 'ok');
            if (errors.length) {
                process.exitCode = 1;
            }
            await page.close();
        }
    } catch (error) {
        console.error(error);
        process.exitCode = 1;
    }
    await browser.close();
    server.close();
    process.exit(process.exitCode || 0);
}());
