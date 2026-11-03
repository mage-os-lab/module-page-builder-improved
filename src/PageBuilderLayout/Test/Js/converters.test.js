/**
 * Checks the style converters and reader, and that they know every value the PHP option sources offer.
 *
 * Run from the module root: node Test/Js/converters.test.js
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');

/**
 * @param {String} file - under view/adminhtml/web/js
 * @returns {*} what the AMD module defines, with the module's own dependencies
 */
function load(file) {
    let defined;

    vm.runInNewContext(fs.readFileSync(path.join(root, 'view/adminhtml/web/js', file), 'utf8'), {
        define: function (deps, factory) {
            defined = factory.apply(null, deps.map(function (dep) {
                return load(dep.replace(/^MageOS_PageBuilderLayout\/js\//, '') + '.js');
            }));
        }
    });

    return defined;
}

/**
 * @param {String} source - class name under Model/Config/Source
 * @returns {String[]} the option values in its PHP file
 */
function optionValues(source) {
    const php = fs.readFileSync(path.join(root, 'Model/Config/Source', source + '.php'), 'utf8');

    return Array.from(php.matchAll(/'value' => '([^']*)'/g), (match) => match[1]);
}

/**
 * @param {String} source - class name under Model/Config/Source
 * @returns {String[]} its option values, also those it makes from a constant (foreach (self::GAPS as $gap) with
 *     'value' => $gap . 'px'), and its parent's (class RowGap extends Gap)
 */
function sourceValues(source) {
    const php = fs.readFileSync(path.join(root, 'Model/Config/Source', source + '.php'), 'utf8'),
        parent = /class \w+ extends (\w+)/.exec(php),
        loop = /foreach \(self::(\w+) as \$(\w+)\)[\s\S]*?'value' => (?:\(string\) )?\$\2(?: \. '(\w*)')?/.exec(php);

    if (parent) {
        return sourceValues(parent[1]);
    }
    if (!loop) {
        return optionValues(source);
    }
    const constant = new RegExp('const ' + loop[1] + ' = \\[([^\\]]*)\\]').exec(php)[1];

    return optionValues(source).concat(constant.split(',').map((item) => item.trim() + (loop[3] || '')));
}

/**
 * @returns {Object<String, String>} each setting of the module's forms, and its option source's class name
 */
function settingSources() {
    const sources = {};

    fs.readdirSync(path.join(root, 'view/adminhtml/ui_component')).forEach(function (file) {
        const xml = fs.readFileSync(path.join(root, 'view/adminhtml/ui_component', file), 'utf8');

        for (const field of xml.matchAll(/<field name="(mageos_\w+)"[\s\S]*?<\/field>/g)) {
            const options = /<options class="[^"]*\\(\w+)"/.exec(field[0]);

            sources[field[1]] = options ? options[1] : null;
        }
    });

    return sources;
}

/**
 * @param {*} value - from the module's context, with that context's Object prototype
 * @returns {*} the same value, with this context's
 */
function plain(value) {
    return JSON.parse(JSON.stringify(value));
}

/**
 * @param {Object} converter
 * @param {String} name
 * @param {Object} data
 * @returns {*} converter.toDom(name, data), with this context's Object prototype
 */
function plainDom(converter, name, data) {
    return plain(converter.toDom(name, data));
}

const CustomProperty = load('converter/style/custom-property.js');
const ColumnsPerLine = load('converter/style/columns-per-line.js');
const Layout = load('converter/style/layout.js');
const GridColumns = load('converter/style/grid-columns.js');
const Mirrored = load('converter/style/mirrored.js');
const Reader = load('property/custom-property.js');
const values = load('converter/style/values.js');

// What pasted or imported content could have in a style: none of it may reach the CSS.
const HOSTILE = [
    '16px; background: url(https://example.com/x.png)',
    '16px}body{display:none',
    'url(javascript:alert(1))',
    'expression(alert(1))',
    'center;color:red',
    ' 16px',
    '16PX',
    '3;',
    '7',
    '0',
    '-1',
    '2.5',
    'constructor',
    'hasOwnProperty',
    {toString: () => '16px'},
    ['16px'],
    true
];

const tests = {
    'custom property: named after the setting, nothing when empty': function () {
        const converter = new CustomProperty();

        assert.deepStrictEqual(plainDom(converter, 'mageos_column_gap', {'mageos_column_gap': '16px'}),
            {'--mageos-column-gap': '16px'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_column_gap', {'mageos_column_gap': ''}), {});
        assert.deepStrictEqual(plainDom(converter, 'mageos_column_gap', {}), {});
        assert.deepStrictEqual(plainDom(converter, 'mageos_columns_justify', {'mageos_columns_justify': 'center'}),
            {'--mageos-columns-justify': 'center'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_column_gap', {'mageos_column_gap': 'center'}), {});
        assert.strictEqual(converter.fromDom('16px'), '16px');
    },

    'columns per line: count, width after the gaps, wrapping': function () {
        const converter = new ColumnsPerLine();

        assert.deepStrictEqual(plainDom(converter, 'mageos_columns_per_line', {'mageos_columns_per_line': '3'}), {
            '--mageos-columns-per-line': '3',
            '--mageos-column-basis': 'calc((100% - 2 * var(--mageos-column-gap, 0px)) / 3)',
            '--mageos-column-wrap': 'wrap'
        });
        assert.strictEqual(
            converter.toDom('mageos_columns_per_line', {'mageos_columns_per_line': '1'})['--mageos-column-basis'],
            'calc((100% - 0 * var(--mageos-column-gap, 0px)) / 1)'
        );
        ['', '0', 'x', undefined].forEach(function (value) {
            assert.deepStrictEqual(
                plainDom(converter, 'mageos_columns_per_line', {'mageos_columns_per_line': value}),
                {}
            );
        });
    },

    'layout: CSS for each layout, only the setting when hidden': function () {
        const converter = new Layout();

        assert.deepStrictEqual(plainDom(converter, 'mageos_layout', {'mageos_layout': 'row', display: true}),
            {display: 'flex', flexDirection: 'row', '--mageos-layout': 'row'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_layout', {'mageos_layout': 'stack'}),
            {display: 'flex', flexDirection: 'column', '--mageos-layout': 'stack'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_layout', {'mageos_layout': 'grid'}),
            {display: 'grid', '--mageos-layout': 'grid'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_layout', {'mageos_layout': 'grid', display: false}),
            {'--mageos-layout': 'grid'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_layout', {'mageos_layout': ''}), {});
        assert.deepStrictEqual(plainDom(converter, 'mageos_layout', {'mageos_layout': 'toString'}), {});
        assert.strictEqual(converter.fromDom('row-reverse'), 'row-reverse');
        assert.strictEqual(converter.fromDom('nonsense'), '');
    },

    'layout: every option has CSS': function () {
        assert.deepStrictEqual(optionValues('ContentLayout'), [''].concat(Object.keys(Layout.LAYOUTS)));
    },

    'grid columns: equal columns content cannot widen': function () {
        const converter = new GridColumns();

        assert.deepStrictEqual(plainDom(converter, 'mageos_grid_columns', {'mageos_grid_columns': '4'}),
            {gridTemplateColumns: 'repeat(4, minmax(0, 1fr))', '--mageos-grid-columns': '4'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_grid_columns', {'mageos_grid_columns': ''}), {});
        assert.deepStrictEqual(plainDom(converter, 'mageos_grid_columns', {'mageos_grid_columns': '100000'}), {});
        assert.strictEqual(converter.fromDom('4'), '4');
    },

    'mirrored: the CSS property and its custom property': function () {
        const converter = new Mirrored();

        assert.deepStrictEqual(plainDom(converter, 'mageos_justify_content', {'mageos_justify_content': 'center'}),
            {justifyContent: 'center', '--mageos-justify-content': 'center'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_align_items', {'mageos_align_items': 'baseline'}),
            {alignItems: 'baseline', '--mageos-align-items': 'baseline'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_flex_wrap', {'mageos_flex_wrap': 'wrap'}),
            {flexWrap: 'wrap', '--mageos-flex-wrap': 'wrap'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_gap', {'mageos_gap': '0px'}),
            {gap: '0px', '--mageos-gap': '0px'});
        assert.deepStrictEqual(plainDom(converter, 'mageos_gap', {'mageos_gap': ''}), {});
    },

    'values: the options of each setting, without Default': function () {
        const sources = settingSources();

        assert.deepStrictEqual(Object.keys(plain(values.VALUES)).sort(),
            Object.keys(sources).filter((name) => name !== 'mageos_layout').sort());
        Object.keys(sources).filter((name) => name !== 'mageos_layout').forEach(function (name) {
            assert.deepStrictEqual(plain(values.VALUES[name]), sourceValues(sources[name]).filter((value) => value),
                name + ' has the values of ' + sources[name]);
        });
        assert.strictEqual(sources['mageos_layout'], 'ContentLayout');
    },

    'values: only a setting\'s own values are allowed, any setting\'s are known': function () {
        assert.strictEqual(values.allowed('mageos_gap', '16px'), '16px');
        assert.strictEqual(values.allowed('mageos_grid_columns', 4), '4');
        assert.strictEqual(values.allowed('mageos_gap', 'center'), '');
        assert.strictEqual(values.allowed('mageos_unknown', '16px'), '');
        assert.strictEqual(values.allowed('constructor', '16px'), '');
        assert.strictEqual(values.known('center'), 'center');
        assert.strictEqual(values.known('6'), '6');
        HOSTILE.forEach(function (value) {
            assert.strictEqual(values.allowed('mageos_gap', value), '', JSON.stringify(value));
            assert.strictEqual(values.known(value), '', JSON.stringify(value));
        });
    },

    'every converter: hostile values write and read back nothing': function () {
        const settings = {
            'custom-property': [CustomProperty, ['mageos_column_gap', 'mageos_row_gap', 'mageos_columns_justify']],
            'columns-per-line': [ColumnsPerLine, ['mageos_columns_per_line']],
            'grid-columns': [GridColumns, ['mageos_grid_columns']],
            'layout': [Layout, ['mageos_layout']],
            'mirrored': [Mirrored, ['mageos_justify_content', 'mageos_align_items', 'mageos_flex_wrap', 'mageos_gap']]
        };

        Object.keys(settings).forEach(function (converterName) {
            const converter = new settings[converterName][0]();

            settings[converterName][1].forEach(function (name) {
                HOSTILE.forEach(function (value) {
                    const data = {display: true};

                    data[name] = value;
                    assert.deepStrictEqual(plainDom(converter, name, data), {},
                        converterName + ' writes ' + name + ' = ' + JSON.stringify(value));
                    assert.strictEqual(converter.fromDom(value), '',
                        converterName + ' reads ' + JSON.stringify(value));
                });
            });
        });
    },

    'reader: the trimmed custom property': function () {
        const element = {
            style: {
                getPropertyValue: function (name) {
                    return name === '--mageos-gap' ? ' 16px' : '';
                }
            }
        };

        assert.strictEqual(new Reader().read(element, '--mageos-gap'), '16px');
        assert.strictEqual(new Reader().read(element, '--mageos-layout'), '');
    }
};

let failures = 0;

Object.keys(tests).forEach(function (name) {
    try {
        tests[name]();
        console.log('ok - ' + name);
    } catch (e) {
        failures++;
        console.error('FAIL - ' + name + ': ' + e.message);
    }
});

if (failures) {
    process.exit(1);
}
console.log('OK (' + Object.keys(tests).length + ' tests)');
