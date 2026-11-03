/**
 * Checks view/adminhtml/web/js/utils/field-values.js on its own: images and links, hostile stored values too.
 *
 * Run from the module root: node Test/Js/field-values.test.js
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const MEDIA_URL = 'https://shop.test/media/';
let fieldValues;

vm.runInNewContext(fs.readFileSync(path.join(root, 'view/adminhtml/web/js/utils/field-values.js'), 'utf8'), {
    define: function (deps, factory) {
        fieldValues = factory(
            {
                getConfig: function (key) {
                    return key === 'media_url' ? MEDIA_URL : undefined;
                }
            },
            {
                convertUrlToPathIfOtherUrlIsOnlyAPath: function (url) {
                    return url;
                }
            }
        );
    }
});

/**
 * @param {*} value A value from the vm context.
 * @returns {String}
 */
function json(value) {
    return JSON.stringify(value);
}

// Images: the path under the media directory is stored.
assert.strictEqual(fieldValues.toStored('image', [{url: MEDIA_URL + 'wysiwyg/a.png'}]), 'wysiwyg/a.png');
assert.strictEqual(fieldValues.toStored('image', []), '');
assert.strictEqual(fieldValues.fromStored('image', 'wysiwyg/a.png')[0].url, MEDIA_URL + 'wysiwyg/a.png');
assert.strictEqual(fieldValues.fromStored('image', 'wysiwyg/a.png')[0].type, 'image/png');
assert.strictEqual(fieldValues.fromStored('image', 'wysiwyg/a.gif?v=1')[0].type, 'image/gif');
// Types the upload controller refuses are not offered: an unknown extension falls back to jpeg.
assert.strictEqual(fieldValues.fromStored('image', 'wysiwyg/a.svg')[0].type, 'image/jpeg');
assert.strictEqual(fieldValues.fromStored('image', 'wysiwyg/a.webp')[0].type, 'image/jpeg');

// Links: the URL input's object, with a string type.
assert.strictEqual(
    json(fieldValues.fromStored('link', '{"type":"page","page":"3","setting":false}')),
    '{"type":"page","page":"3","setting":false}'
);
assert.strictEqual(
    fieldValues.toStored('link', {type: 'product', product: 42, setting: 'true'}),
    '{"type":"product","setting":true,"product":"42"}'
);

// Any other JSON, or no JSON, is a plain URL typed into the widget dialog.
[
    '/sale.html',
    'https://example.com/',
    '42',
    'null',
    'true',
    '[1,2]',
    '{}',
    '{"default":"/x"}',
    '{"type":1,"1":"/x"}',
    '{"type":{"x":1}}'
].forEach(function (stored) {
    assert.strictEqual(
        json(fieldValues.fromStored('link', stored)),
        json({type: 'default', 'default': stored, setting: false}),
        stored
    );
});

console.log('OK');
