/**
 * Encodes field values so any string survives a {{widget}} directive parameter, and decodes them.
 *
 * The same codec as MageOS\PageBuilderComponents\Model\DirectiveValueCodec; change both together.
 * Test/Unit/_files/codec-vectors.json holds the cases both are tested against.
 */
define([], function () {
    'use strict';

    var ENCODE = {
            '^': '^0',
            '"': '^1',
            '\'': '^2',
            '{': '^3',
            '}': '^4',
            '\\': '^5',
            '%': '^6',
            '<': '^7',
            '>': '^8',
            '&': '^9'
        },
        DECODE = {};

    Object.keys(ENCODE).forEach(function (character) {
        DECODE[ENCODE[character]] = character;
    });

    return {
        /**
         * @param {*} value
         * @returns {String}
         */
        encode: function (value) {
            return String(value).replace(/[\^"'{}\\%<>&]/g, function (character) {
                return ENCODE[character];
            });
        },

        /**
         * A "^" not followed by a digit is kept as it is.
         *
         * @param {*} value
         * @returns {String}
         */
        decode: function (value) {
            return String(value).replace(/\^[0-9]/g, function (sequence) {
                return DECODE[sequence];
            });
        }
    };
});
