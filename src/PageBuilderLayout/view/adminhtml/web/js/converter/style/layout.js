/**
 * Content layout of a row or column: lays the elements in it out stacked or side by side (flex), or in a grid,
 * with the setting itself as --mageos-layout to read it back (js/property/custom-property).
 *
 * Empty writes nothing: Page Builder's stacking, or in the Tablet and Mobile views the Desktop layout. A hidden
 * element keeps core's display: none: only the setting is written.
 */
define([], function () {
    'use strict';

    var LAYOUTS = {
        'stack': {display: 'flex', flexDirection: 'column'},
        'row': {display: 'flex', flexDirection: 'row'},
        'row-reverse': {display: 'flex', flexDirection: 'row-reverse'},
        'grid': {display: 'grid'}
    };

    /**
     * @constructor
     */
    function Layout() {}

    Layout.LAYOUTS = LAYOUTS;

    /**
     * @param {String} value
     * @returns {String}
     */
    Layout.prototype.fromDom = function (value) {
        return LAYOUTS.hasOwnProperty(value) ? value : '';
    };

    /**
     * @param {String} name
     * @param {Object} data
     * @returns {Object}
     */
    Layout.prototype.toDom = function (name, data) {
        var layout = data[name],
            styles = {};

        if (!LAYOUTS.hasOwnProperty(layout)) {
            return {};
        }
        if (data.display !== false) {
            Object.keys(LAYOUTS[layout]).forEach(function (property) {
                styles[property] = LAYOUTS[layout][property];
            });
        }
        styles['--mageos-layout'] = layout;

        return styles;
    };

    return Layout;
});
