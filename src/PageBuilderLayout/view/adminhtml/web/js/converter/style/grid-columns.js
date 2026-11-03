/**
 * Columns of a row's or column's Grid layout: 3 as grid-template-columns: repeat(3, minmax(0, 1fr)), equal
 * columns that content can't widen, and as --mageos-grid-columns to read it back (js/property/custom-property).
 * Empty writes nothing, as does a count the setting's options don't have (./values).
 */
define(['MageOS_PageBuilderLayout/js/converter/style/values'], function (values) {
    'use strict';

    /**
     * @constructor
     */
    function GridColumns() {}

    /**
     * @param {String} value
     * @returns {String}
     */
    GridColumns.prototype.fromDom = function (value) {
        return values.known(value);
    };

    /**
     * @param {String} name
     * @param {Object} data
     * @returns {Object}
     */
    GridColumns.prototype.toDom = function (name, data) {
        var count = parseInt(values.allowed(name, data[name]), 10);

        if (!(count >= 1)) {
            return {};
        }

        return {
            gridTemplateColumns: 'repeat(' + count + ', minmax(0, 1fr))',
            '--mageos-grid-columns': String(count)
        };
    };

    return GridColumns;
});
