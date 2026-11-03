/**
 * "Columns per line" of a Columns element: the number (--mageos-columns-per-line, read back by
 * js/property/custom-property), the width it gives each column after the column gaps (--mageos-column-basis),
 * and wrapping (--mageos-column-wrap). view/frontend/web/css/layout.css applies them to the column lines.
 *
 * Empty keeps the widths set on the stage, as does a count the setting's options don't have (./values).
 */
define(['MageOS_PageBuilderLayout/js/converter/style/values'], function (values) {
    'use strict';

    /**
     * @constructor
     */
    function ColumnsPerLine() {}

    /**
     * @param {String} value
     * @returns {String}
     */
    ColumnsPerLine.prototype.fromDom = function (value) {
        return values.known(value);
    };

    /**
     * @param {String} name
     * @param {Object} data
     * @returns {Object}
     */
    ColumnsPerLine.prototype.toDom = function (name, data) {
        var count = parseInt(values.allowed(name, data[name]), 10);

        if (!(count >= 1)) {
            return {};
        }

        return {
            '--mageos-columns-per-line': String(count),
            '--mageos-column-basis': 'calc((100% - ' + (count - 1) + ' * var(--mageos-column-gap, 0px)) / ' +
                count + ')',
            '--mageos-column-wrap': 'wrap'
        };
    };

    return ColumnsPerLine;
});
