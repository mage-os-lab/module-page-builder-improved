/**
 * Checks the stage's directive value codec against the cases the PHP codec is tested against.
 *
 * Run from the module root: node Test/Js/codec.test.js
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
let codec;
const source = fs.readFileSync(path.join(root, 'view/adminhtml/web/js/utils/directive-value-codec.js'), 'utf8');

vm.runInNewContext(source, {
    define: function (deps, factory) {
        codec = factory();
    }
});

const vectors = JSON.parse(fs.readFileSync(path.join(root, 'Test/Unit/_files/codec-vectors.json'), 'utf8'));
let failures = 0;

vectors.forEach(function (vector, index) {
    try {
        assert.strictEqual(codec.encode(vector.decoded), vector.encoded, 'encode');
        assert.strictEqual(codec.decode(vector.encoded), vector.decoded, 'decode');
        assert.ok(!/["'{}\\%<>&]/.test(codec.encode(vector.decoded)), 'no unsafe character');
    } catch (e) {
        failures++;
        console.error('vector ' + index + ': ' + e.message);
    }
});

assert.strictEqual(codec.decode('a^b^'), 'a^b^');

if (failures) {
    console.error(failures + ' of ' + vectors.length + ' vectors failed');
    process.exit(1);
}
console.log('OK (' + vectors.length + ' vectors)');
