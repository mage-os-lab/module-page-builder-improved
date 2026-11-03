/**
 * Form data provider of a component.
 *
 * Core's rule conditions tree (the Products content type's) is not a UI component: it posts one input per
 * condition setting, named `parameters[<field>][<node id>][<setting>]`. Before the form saves, each conditions
 * field the form lists in `conditionsFields` (FormXmlGenerator writes it) gets those inputs collected, removed
 * from the form data, and stored as the field's value: the tree as JSON, as the block reads it.
 */
define([
    'underscore',
    'Magento_PageBuilder/js/form/provider',
    'Magento_Rule/conditions-data-normalizer'
], function (_, Provider, ConditionsDataNormalizer) {
    'use strict';

    var COMBINE = 'Magento\\CatalogWidget\\Model\\Rule\\Condition\\Combine',
        normalizer = new ConditionsDataNormalizer();

    /**
     * Node ids in a tree, deepest first. A node's id is its parent's id, "--", and its position: 1, 1--1, 1--2...
     *
     * @param {Object} nodes Settings by node id.
     * @return {String[]}
     */
    function deepestFirst(nodes) {
        return _.sortBy(_.keys(nodes), function (id) {
            return -id.split('--').length;
        });
    }

    /**
     * Turn the tree's inputs into the field's JSON value, or null when the form has none of them.
     *
     * @param {Object} data Form data, the field's inputs are removed from it.
     * @param {String} field
     * @return {String|null}
     */
    function collectTree(data, field) {
        var prefix = 'parameters[' + field + '][',
            inputs = {},
            nodes = {};

        _.each(_.keys(data), function (name) {
            var match;

            if (name.slice(0, prefix.length) !== prefix) {
                return;
            }
            inputs[name] = data[name];
            delete data[name];

            match = name.slice(prefix.length).match(/^([^\]]+)\]\[([^\]]+)\]$/);
            if (match) {
                nodes[match[1]] = nodes[match[1]] || {};
                nodes[match[1]][match[2]] = inputs[name];
            }
        });

        if (_.isEmpty(inputs)) {
            return null;
        }

        // The rule model cannot load a combination without conditions: clear its type, deepest first, so a
        // combination holding only empty combinations is cleared too.
        _.each(deepestFirst(nodes), function (id) {
            var hasCondition;

            if (nodes[id].type !== COMBINE) {
                return;
            }
            hasCondition = _.some(nodes, function (node, childId) {
                return childId.indexOf(id + '--') === 0 &&
                    childId.slice(id.length + 2).indexOf('--') === -1 &&
                    !!node.type;
            });
            if (!hasCondition) {
                nodes[id].type = '';
                inputs[prefix + id + '][type]'] = '';
            }
        });

        return JSON.stringify(normalizer.normalize(inputs).parameters[field]);
    }

    return Provider.extend({
        defaults: {
            conditionsFields: []
        },

        /** @inheritdoc */
        save: function () {
            var data = this.get('data');

            _.each(this.conditionsFields, function (field) {
                var value = collectTree(data, field);

                if (value !== null) {
                    data[field] = value;
                }
            });

            return this._super();
        }
    });
});
