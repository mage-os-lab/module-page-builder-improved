/**
 * Writes a setting as the CSS property it is named after, mageos_justify_content as justify-content, and as
 * --mageos-justify-content, which js/property/custom-property reads back. Reading justify-content itself would
 * also pick up what core sets on the same element: a row's or column's vertical alignment.
 *
 * An empty setting writes nothing, so core's value, or the Desktop one in the Tablet and Mobile views, stays.
 * Nor does a value the setting's options don't have (./values).
 */
define(['MageOS_PageBuilderLayout/js/converter/style/values'], function (values) {
    'use strict';

    /**
     * @constructor
     */
    function Mirrored() {}

    /**
     * @param {String} name - the setting, e.g. mageos_justify_content
     * @returns {String} the CSS property in Knockout's style binding, e.g. justifyContent
     */
    Mirrored.propertyName = function (name) {
        return name.replace(/^mageos_/, '').replace(/_([a-z])/g, function (match, letter) {
            return letter.toUpperCase();
        });
    };

    /**
     * Core doesn't say which setting the value is read for: a value no setting has is read as empty.
     *
     * @param {String} value
     * @returns {String}
     */
    Mirrored.prototype.fromDom = function (value) {
        return values.known(value);
    };

    /**
     * @param {String} name
     * @param {Object} data
     * @returns {Object}
     */
    Mirrored.prototype.toDom = function (name, data) {
        var value = values.allowed(name, data[name]),
            styles = {};

        if (value === '') {
            return {};
        }
        styles[Mirrored.propertyName(name)] = value;
        styles['--' + name.replace(/_/g, '-')] = value;

        return styles;
    };

    return Mirrored;
});
