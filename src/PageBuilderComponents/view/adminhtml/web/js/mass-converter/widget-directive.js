/**
 * Stores a component as a {{widget}} directive: its block class as `type`, and one encoded parameter per field.
 */
define([
    'Magento_PageBuilder/js/mass-converter/widget-directive-abstract',
    'Magento_PageBuilder/js/utils/object',
    'MageOS_PageBuilderComponents/js/utils/directive-value-codec',
    'MageOS_PageBuilderComponents/js/utils/field-values'
], function (WidgetDirectiveAbstract, objectUtils, codec, fieldValues) {
    'use strict';

    /**
     * @constructor
     */
    function WidgetDirective() {
        WidgetDirectiveAbstract.apply(this, arguments);
    }

    WidgetDirective.prototype = Object.create(WidgetDirectiveAbstract.prototype);
    WidgetDirective.prototype.constructor = WidgetDirective;

    /**
     * Stored directive -> data store. A field missing from the directive keeps the form's default.
     *
     * @param {Object} data
     * @param {Object} config
     * @returns {Object}
     */
    WidgetDirective.prototype.fromDom = function (data, config) {
        var attributes = WidgetDirectiveAbstract.prototype.fromDom.call(this, data, config),
            fields = fieldValues.getComponent(config['content_type']).fields;

        Object.keys(fields).forEach(function (name) {
            if (attributes[name] !== undefined) {
                data[name] = fieldValues.fromStored(fields[name], codec.decode(attributes[name]));
            }
        });

        return data;
    };

    /**
     * Data store -> directive. Every field is written, an emptied one too, so it stays empty.
     *
     * @param {Object} data
     * @param {Object} config
     * @returns {Object}
     */
    WidgetDirective.prototype.toDom = function (data, config) {
        var component = fieldValues.getComponent(config['content_type']),
            attributes = {type: component['block_class']};

        Object.keys(component.fields).forEach(function (name) {
            if (data[name] !== undefined) {
                attributes[name] = codec.encode(fieldValues.toStored(component.fields[name], data[name]));
            }
        });

        objectUtils.set(data, config['html_variable'], this.buildDirective(attributes));

        return data;
    };

    return WidgetDirective;
});
