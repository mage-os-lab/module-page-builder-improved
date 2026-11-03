/**
 * The values each setting may have: its options in Model/Config/Source (Test/Js/converters.test.js checks they
 * match). The converters write a setting into CSS only with one of its values, and read back only values a
 * setting has, so content pasted or imported with something else in a style (a ";", "}", url() or
 * expression()) can't carry it into the saved CSS.
 */
define([], function () {
    'use strict';

    var GAPS = ['0px', '8px', '16px', '24px', '32px', '48px', '64px'],
        JUSTIFY = ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'],
        COUNTS = ['1', '2', '3', '4', '5', '6'],
        VALUES = {
            'mageos_column_gap': GAPS,
            'mageos_row_gap': GAPS,
            'mageos_gap': GAPS,
            'mageos_columns_justify': JUSTIFY,
            'mageos_justify_content': JUSTIFY,
            'mageos_align_items': ['stretch', 'flex-start', 'center', 'flex-end', 'baseline'],
            'mageos_flex_wrap': ['nowrap', 'wrap'],
            'mageos_columns_per_line': COUNTS,
            'mageos_grid_columns': COUNTS
        };

    return {
        VALUES: VALUES,

        /**
         * @param {String} name - the setting, e.g. mageos_column_gap
         * @param {*} value
         * @returns {String} the value, or '' when the setting can't have it
         */
        allowed: function (name, value) {
            var values = VALUES.hasOwnProperty(name) ? VALUES[name] : [];

            value = typeof value === 'number' ? String(value) : value;

            return typeof value === 'string' && values.indexOf(value) !== -1 ? value : '';
        },

        /**
         * @param {*} value
         * @returns {String} the value, or '' when no setting can have it
         */
        known: function (value) {
            return Object.keys(VALUES).some(function (name) {
                return VALUES[name].indexOf(value) !== -1;
            }) ? value : '';
        }
    };
});
