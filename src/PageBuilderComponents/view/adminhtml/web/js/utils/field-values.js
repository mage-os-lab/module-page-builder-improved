/**
 * Converts a component's field values between the form's data store and the strings stored in the directive.
 *
 * Value types (FieldInterface::VALUE_*):
 *  - text:  the string as it is;
 *  - image: the image uploader's [{url, ...}] <-> the path under the media directory;
 *  - link:  the URL input's {type, <type>: value, setting} <-> its JSON.
 */
define([
    'Magento_PageBuilder/js/config',
    'Magento_PageBuilder/js/utils/url'
], function (pbConfig, urlUtils) {
    'use strict';

    var MIME_TYPES = {
        jpg: 'image/jpeg',
        jpeg: 'image/jpeg',
        png: 'image/png',
        gif: 'image/gif'
    };

    /**
     * @param {String} url
     * @returns {Boolean}
     */
    function isAbsolute(url) {
        return url.indexOf('://') !== -1 || url.charAt(0) === '/';
    }

    /**
     * @param {*} images
     * @returns {String}
     */
    function imageToStored(images) {
        var url = Array.isArray(images) && images.length && images[0].url ? String(images[0].url) : '',
            mediaUrl;

        if (!url) {
            return '';
        }
        mediaUrl = urlUtils.convertUrlToPathIfOtherUrlIsOnlyAPath(pbConfig.getConfig('media_url'), url);

        return mediaUrl && url.indexOf(mediaUrl) === 0 ? url.slice(mediaUrl.length) : url;
    }

    /**
     * @param {String} stored
     * @returns {Array}
     */
    function imageFromStored(stored) {
        var url, extension;

        if (!stored) {
            return [];
        }
        url = isAbsolute(stored) ? stored : pbConfig.getConfig('media_url') + stored;
        extension = stored.split('?')[0].split('.').pop().toLowerCase();

        return [{
            url: url,
            name: stored.split('?')[0].split('/').pop(),
            type: MIME_TYPES[extension] || 'image/jpeg',
            size: 0
        }];
    }

    /**
     * @param {*} link
     * @returns {String}
     */
    function linkToStored(link) {
        var type, result;

        if (typeof link === 'string') {
            return link;
        }
        if (!link || typeof link !== 'object') {
            return '';
        }
        type = link.type || 'default';
        if (link[type] === undefined || link[type] === null || String(link[type]) === '') {
            return '';
        }
        result = {type: type, setting: link.setting === true || link.setting === 'true' || link.setting === '1'};
        result[type] = String(link[type]);

        return JSON.stringify(result);
    }

    /**
     * @param {String} stored
     * @returns {Object}
     */
    function linkFromStored(stored) {
        var link;

        try {
            link = JSON.parse(stored);
        } catch (e) {
            link = null;
        }

        // Only an object with a string `type` is the URL input's value. Anything else (a number, `null`, an array,
        // an object without a type) is taken for a plain URL typed into the widget dialog, so the input never
        // gets a value it cannot show.
        return link && typeof link === 'object' && !Array.isArray(link) && typeof link.type === 'string'
            ? link
            : {type: 'default', 'default': stored, setting: false};
    }

    return {
        /**
         * Data store value -> stored string.
         *
         * @param {String} type
         * @param {*} value
         * @returns {String}
         */
        toStored: function (type, value) {
            if (type === 'image') {
                return imageToStored(value);
            }
            if (type === 'link') {
                return linkToStored(value);
            }

            return value === undefined || value === null ? '' : String(value);
        },

        /**
         * Stored string -> data store value.
         *
         * @param {String} type
         * @param {String} stored
         * @returns {*}
         */
        fromStored: function (type, stored) {
            if (type === 'image') {
                return imageFromStored(stored);
            }
            if (type === 'link') {
                return linkFromStored(stored);
            }

            return stored;
        },

        /**
         * A component's {name, block_class, fields: {field name: value type}}, from its content type config.
         *
         * @param {String} contentType
         * @returns {Object}
         */
        getComponent: function (contentType) {
            var config = pbConfig.getContentTypeConfig(contentType) || {},
                component = (config['additional_data'] || {})['mageos_component'] || {};

            return {
                name: component.name || contentType,
                'block_class': component['block_class'] || '',
                fields: component.fields || {}
            };
        }
    };
});
