/**
 * Reads a CSS custom property (the style's source, e.g. --mageos-column-gap) from an element, for the settings
 * js/converter/style/custom-property and its siblings write. Core's reader reads element.style[name], which has
 * no custom properties.
 */
define([], function () {
    'use strict';

    /**
     * @constructor
     */
    function CustomPropertyReader() {}

    /**
     * @param {HTMLElement} element
     * @param {String} source
     * @returns {String}
     */
    CustomPropertyReader.prototype.read = function (element, source) {
        return element.style.getPropertyValue(source).trim();
    };

    return CustomPropertyReader;
});
