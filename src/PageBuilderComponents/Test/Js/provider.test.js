/**
 * Runs the form provider's save() on the inputs core's rule conditions tree posts, with core's
 * conditions-data-normalizer, and checks the JSON value each conditions field gets.
 *
 * Run from the module root: node Test/Js/provider.test.js [path/to/magento/module-rule]
 * Without a path, ../../vendor/magento/module-rule and vendor/magento/module-rule are tried.
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const NORMALIZER = 'view/adminhtml/web/conditions-data-normalizer.js';
const ruleModule = [
    process.argv[2],
    path.join(root, '../../vendor/magento/module-rule'),
    path.resolve(root, '../../../../vendor/magento/module-rule')
].find(function (dir) {
    return dir && fs.existsSync(path.join(dir, NORMALIZER));
});

assert.ok(ruleModule, 'Magento_Rule not found; pass its path as the first argument');

const COMBINE = 'Magento\\CatalogWidget\\Model\\Rule\\Condition\\Combine';
const PRODUCT = 'Magento\\CatalogWidget\\Model\\Rule\\Condition\\Product';

const modules = {
    underscore: require('underscore'),
    jquery: {
        /**
         * jQuery.extend(true, target, source), the only form the normalizer uses: a deep merge.
         */
        extend: function extend(deep, target, source) {
            Object.keys(source).forEach(function (key) {
                const value = source[key];

                target[key] = value && typeof value === 'object' && !Array.isArray(value) ?
                    extend(true, target[key] && typeof target[key] === 'object' ? target[key] : {}, value) :
                    value;
            });

            return target;
        }
    },
    'Magento_PageBuilder/js/form/provider': {
        /**
         * Stand-in for core's provider: extend() returns a constructor with the defaults and methods, and
         * save() records that the parent ran.
         */
        extend: function (definition) {
            function Provider(config) {
                Object.assign(this, JSON.parse(JSON.stringify(definition.defaults || {})), config);
            }

            Provider.prototype.get = function (key) {
                return this[key];
            };
            Provider.prototype.save = function () {
                const parent = function () {
                    this.parentSaved = true;

                    return this;
                }.bind(this);

                return definition.save.call(Object.assign(Object.create(this), {_super: parent}));
            };

            return Provider;
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
        JSON: JSON,
        Object: Object,
        define: function (deps, factory) {
            modules[name] = factory.apply(null, deps.map(function (dep) {
                assert.ok(dep in modules, name + ' needs ' + dep);

                return modules[dep];
            }));
        }
    });
}

load('Magento_Rule/conditions-data-normalizer', path.join(ruleModule, NORMALIZER));
load('provider', path.join(root, 'view/adminhtml/web/js/form/provider.js'));

const Provider = modules.provider;
let failures = 0;

/**
 * @param {String} name
 * @param {Function} test
 */
function test(name, test) {
    try {
        test();
        console.log('ok - ' + name);
    } catch (e) {
        failures++;
        console.log('not ok - ' + name + '\n' + e.stack);
    }
}

/**
 * Inputs of one tree node.
 *
 * @param {String} field
 * @param {String} id
 * @param {Object} settings
 * @return {Object}
 */
function node(field, id, settings) {
    const inputs = {};

    Object.keys(settings).forEach(function (key) {
        inputs['parameters[' + field + '][' + id + '][' + key + ']'] = settings[key];
    });

    return inputs;
}

/**
 * @param {String[]} conditionsFields
 * @param {Object} data
 * @return {Object} The data after save().
 */
function save(conditionsFields, data) {
    const provider = new Provider({conditionsFields: conditionsFields, data: data});

    assert.strictEqual(provider.save().parentSaved, true, 'the parent save() runs');

    return data;
}

test('a tree becomes the field\'s JSON value, and its inputs leave the form data', function () {
    const data = save(['products'], Object.assign(
        {title: 'Best sellers'},
        node('products', '1', {type: COMBINE, aggregator: 'all', value: '1', 'new_child': ''}),
        node('products', '1--1', {type: PRODUCT, attribute: 'sku', operator: '()', value: 'A, B'})
    ));

    assert.deepStrictEqual(Object.keys(data).sort(), ['products', 'title']);
    assert.deepStrictEqual(JSON.parse(data.products), {
        '1': {type: COMBINE, aggregator: 'all', value: '1', 'new_child': ''},
        '1--1': {type: PRODUCT, attribute: 'sku', operator: '()', value: 'A, B'}
    });
});

test('several conditions fields, each with its own tree', function () {
    const data = save(['first', 'second'], Object.assign(
        node('first', '1', {type: COMBINE, aggregator: 'all', value: '1'}),
        node('first', '1--1', {type: PRODUCT, attribute: 'sku', operator: '==', value: 'A'}),
        node('second', '1', {type: COMBINE, aggregator: 'any', value: '1'}),
        node('second', '1--1', {type: PRODUCT, attribute: 'sku', operator: '==', value: 'B'})
    ));

    assert.strictEqual(JSON.parse(data.first)['1--1'].value, 'A');
    assert.strictEqual(JSON.parse(data.second)['1'].aggregator, 'any');
    assert.strictEqual(JSON.parse(data.second)['1--1'].value, 'B');
});

test('a combination without conditions has its type cleared', function () {
    const data = save(['products'], node('products', '1', {type: COMBINE, aggregator: 'all', value: '1'}));

    assert.strictEqual(JSON.parse(data.products)['1'].type, '');
});

test('a combination holding only empty combinations is cleared too, deepest first', function () {
    const data = save(['products'], Object.assign(
        node('products', '1', {type: COMBINE, aggregator: 'all', value: '1'}),
        node('products', '1--1', {type: COMBINE, aggregator: 'any', value: '1'}),
        node('products', '1--1--1', {type: COMBINE, aggregator: 'all', value: '1'})
    ));
    const tree = JSON.parse(data.products);

    assert.strictEqual(tree['1--1--1'].type, '');
    assert.strictEqual(tree['1--1'].type, '');
    assert.strictEqual(tree['1'].type, '');
});

test('a combination with a condition further down keeps its type', function () {
    const data = save(['products'], Object.assign(
        node('products', '1', {type: COMBINE, aggregator: 'all', value: '1'}),
        node('products', '1--1', {type: COMBINE, aggregator: 'any', value: '1'}),
        node('products', '1--1--1', {type: PRODUCT, attribute: 'sku', operator: '==', value: 'A'}),
        node('products', '1--2', {type: COMBINE, aggregator: 'all', value: '1'})
    ));
    const tree = JSON.parse(data.products);

    assert.strictEqual(tree['1'].type, COMBINE);
    assert.strictEqual(tree['1--1'].type, COMBINE);
    assert.strictEqual(tree['1--2'].type, '');
});

test('a field whose tree is not in the form keeps its value', function () {
    const data = save(['products'], {products: '{"1":{"type":"x"}}', title: 'Kept'});

    assert.deepStrictEqual(data, {products: '{"1":{"type":"x"}}', title: 'Kept'});
});

test('inputs of a field that is not a conditions field are left alone', function () {
    const data = save([], node('products', '1', {type: COMBINE}));

    assert.deepStrictEqual(Object.keys(data), ['parameters[products][1][type]']);
});

test('a field name that starts like another one does not take its inputs', function () {
    const data = save(['product'], node('products', '1', {type: COMBINE, aggregator: 'all', value: '1'}));

    assert.strictEqual(data.product, undefined);
    assert.strictEqual(data['parameters[products][1][type]'], COMBINE);
});

if (failures) {
    console.log(failures + ' failed');
    process.exit(1);
}
console.log('OK');
