/**
 * Runs the stage's mass converter (with core's widget-directive-abstract) on a component with one field of each
 * value type, and checks the directive against Test/Unit/_files/stage-directive.txt, which the PHP side parses
 * and hydrates in ValueHydratorTest.
 *
 * Run from the module root: node Test/Js/widget-directive.test.js [path/to/magento/module-page-builder]
 * Without a path, ../../../vendor/magento/module-page-builder and vendor/magento/module-page-builder are tried.
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const pageBuilder = [
    process.argv[2],
    path.join(root, 'vendor/magento/module-page-builder'),
    path.resolve(root, '../../../../vendor/magento/module-page-builder')
].find(function (dir) {
    return dir && fs.existsSync(path.join(dir, 'view/adminhtml/web/js/mass-converter/widget-directive-abstract.js'));
});

assert.ok(pageBuilder, 'Magento_PageBuilder not found; pass its path as the first argument');

const MEDIA_URL = 'https://shop.test/media/';
const CONTENT_TYPE = {
    'additional_data': {
        'mageos_component': {
            name: 'test_all_fields',
            'block_class': 'MageOS\\PageBuilderComponents\\Test\\Unit\\Fixture\\AllFieldsComponent',
            fields: {title: 'text', image: 'image', link: 'link', overlay: 'text', content: 'text'}
        }
    }
};

const modules = {
    'underscore': {
        each: function (object, callback) {
            Object.keys(object).forEach(function (key) {
                callback(object[key], key);
            });
        }
    },
    'Magento_PageBuilder/js/config': {
        getConfig: function (key) {
            return key === 'media_url' ? MEDIA_URL : undefined;
        },
        getContentTypeConfig: function (name) {
            return name === 'test_all_fields' ? CONTENT_TYPE : undefined;
        }
    },
    'Magento_PageBuilder/js/utils/url': {
        convertUrlToPathIfOtherUrlIsOnlyAPath: function (url) {
            return url;
        }
    },
    'Magento_PageBuilder/js/utils/object': {
        get: function (object, key) {
            return object[key];
        },
        set: function (object, key, value) {
            object[key] = value;
        }
    }
};

/**
 * Evaluate an AMD module whose dependencies are registered in `modules`.
 *
 * @param {String} name
 * @param {String} file
 */
function load(name, file) {
    vm.runInNewContext(fs.readFileSync(file, 'utf8'), {
        Object: Object,
        define: function (deps, factory) {
            modules[name] = factory.apply(null, deps.map(function (dep) {
                assert.ok(dep in modules, name + ' needs ' + dep);

                return modules[dep];
            }));
        }
    });
}

const js = path.join(root, 'view/adminhtml/web/js');

load('Magento_PageBuilder/js/mass-converter/widget-directive-abstract',
    path.join(pageBuilder, 'view/adminhtml/web/js/mass-converter/widget-directive-abstract.js'));
load('MageOS_PageBuilderComponents/js/utils/directive-value-codec', path.join(js, 'utils/directive-value-codec.js'));
load('MageOS_PageBuilderComponents/js/utils/field-values', path.join(js, 'utils/field-values.js'));
load('converter', path.join(js, 'mass-converter/widget-directive.js'));

const Converter = modules.converter;
const config = {'html_variable': 'html', 'content_type': 'test_all_fields'};
const formData = {
    title: 'Tom & "Jerry" {{x}} 100%',
    image: [{url: MEDIA_URL + 'wysiwyg/hero.jpg', name: 'hero.jpg', type: 'image/jpeg', size: 123}],
    link: {type: 'product', product: '42', setting: true},
    overlay: '0',
    content: '<p class="intro">{{media url="wysiwyg/a.jpg"}}</p>',
    unrelated: 'not a field'
};

const html = new Converter().toDom(Object.assign({}, formData), config).html;
const expected = fs.readFileSync(path.join(root, 'Test/Unit/_files/stage-directive.txt'), 'utf8').trim();

assert.strictEqual(html, expected, 'directive written by the stage');
assert.ok(!/unrelated/.test(html), 'only the fields are written');

const restored = new Converter().fromDom({html: html}, config);

assert.strictEqual(restored.title, formData.title);
assert.strictEqual(restored.content, formData.content);
assert.strictEqual(restored.overlay, '0');
// Compared as JSON: the object comes from the vm context, with that context's Object prototype.
assert.strictEqual(JSON.stringify(restored.link), '{"type":"product","setting":true,"product":"42"}');
assert.strictEqual(restored.image[0].url, MEDIA_URL + 'wysiwyg/hero.jpg');
assert.strictEqual(restored.image[0].type, 'image/jpeg');

// An emptied field is written, so it stays empty rather than falling back to its default.
assert.ok(/ title=""/.test(new Converter().toDom({title: ''}, config).html), 'empty field written');

// A field missing from stored content is left for the form's default.
assert.ok(!('title' in new Converter().fromDom({html: '{{widget type="X"}}'}, config)), 'missing field');

console.log('OK');
