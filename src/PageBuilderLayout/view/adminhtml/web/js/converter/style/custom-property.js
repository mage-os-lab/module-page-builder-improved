/**
 * Writes a setting as a CSS custom property, for the storefront's CSS to use (view/frontend/web/css/layout.css):
 * the setting mageos_column_gap becomes --mageos-column-gap. Page Builder writes an element's styles once per
 * viewport, inside that viewport's media query, so each viewport can have its own value.
 *
 * An empty setting writes nothing, so a Tablet or Mobile view without one keeps the Desktop value. Nor does a
 * value the setting's options don't have (./values). js/property/custom-property reads the value back.
 */
define(['MageOS_PageBuilderLayout/js/converter/style/values'], function (values) {
    'use strict';

    /**
     * @constructor
     */
    function CustomProperty() {}

    /**
     * @param {String} name - the setting, e.g. mageos_column_gap
     * @returns {String} the custom property, e.g. --mageos-column-gap
     */
    CustomProperty.propertyName = function (name) {
        return '--' + name.replace(/_/g, '-');
    };

    /**
     * Core doesn't say which setting the value is read for: a value no setting has is read as empty.
     *
     * @param {String} value
     * @returns {String}
     */
    CustomProperty.prototype.fromDom = function (value) {
        return values.known(value);
    };

    /**
     * @param {String} name
     * @param {Object} data
     * @returns {Object}
     */
    CustomProperty.prototype.toDom = function (name, data) {
        var value = values.allowed(name, data[name]),
            styles = {};

        if (value === '') {
            return {};
        }
        styles[CustomProperty.propertyName(name)] = value;

        return styles;
    };

    return CustomProperty;
});
