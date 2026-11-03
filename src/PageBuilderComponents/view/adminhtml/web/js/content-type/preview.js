/**
 * Stage preview of a component: the storefront HTML, rendered on the server with the values in the form.
 */
define([
    'jquery',
    'knockout',
    'mage/translate',
    'Magento_PageBuilder/js/config',
    'Magento_PageBuilder/js/content-type/preview',
    'MageOS_PageBuilderComponents/js/utils/field-values',
    'MageOS_PageBuilderComponents/js/utils/preview-frame',
    'MageOS_PageBuilderComponents/js/utils/empty-preview'
], function ($, ko, $t, pbConfig, Preview, fieldValues, previewFrame, emptyPreview) {
    'use strict';

    // Edits within this many milliseconds of each other make one preview request.
    var PREVIEW_DELAY = 250;

    // Previews with Hyvä are iframes; size every one of them to its content, whenever it is inserted.
    previewFrame.watch(document);

    /**
     * @param {ContentTypeInterface} contentType
     * @param {Object} config
     * @param {ObservableUpdater} observableUpdater
     * @constructor
     */
    function ComponentPreview(contentType, config, observableUpdater) {
        Preview.call(this, contentType, config, observableUpdater);

        this.loading = ko.observable(false);
        this.previewUnsanitizedHtml = ko.observable('');
        this.displayPreview = ko.observable(false);
        this.placeholderText = ko.observable(config.label);
        // The rendered HTML shows (next to) nothing, as when a component was just added.
        this.isPreviewEmpty = ko.observable(false);
        this.emptyPreviewText = $t('%1 has no content yet. Click the gear icon to edit it.')
            .replace('%1', config.label);
        this.stopWatchingPreview = null;
        this.request = null;
        this.fetchTimer = null;
        // The values changed while a request was running: its answer is out of date.
        this.fetchPending = false;
        this.destroyed = false;
    }

    ComponentPreview.prototype = Object.create(Preview.prototype);
    ComponentPreview.prototype.constructor = ComponentPreview;

    /**
     * @inheritdoc
     */
    ComponentPreview.prototype.afterObservablesUpdated = function () {
        Preview.prototype.afterObservablesUpdated.call(this);
        this.schedulePreview();
    };

    /**
     * Watch the rendered preview's height, to show a placeholder while it is empty.
     *
     * @param {Element} element The .rendered-content element.
     */
    ComponentPreview.prototype.watchRenderedContent = function (element) {
        var self = this;

        if (this.stopWatchingPreview) {
            this.stopWatchingPreview();
        }
        this.stopWatchingPreview = emptyPreview.watch(element, function (isEmpty) {
            self.isPreviewEmpty(isEmpty);
        });
    };

    /**
     * The placeholder of an empty preview opens the form, like the toolbar's gear icon.
     */
    ComponentPreview.prototype.onEmptyPreviewClick = function () {
        this.openEdit();
    };

    /**
     * @inheritdoc
     */
    ComponentPreview.prototype.destroy = function () {
        var request = this.request;

        this.destroyed = true;
        clearTimeout(this.fetchTimer);
        this.fetchTimer = null;
        this.request = null;
        if (request) {
            request.abort();
        }
        if (this.stopWatchingPreview) {
            this.stopWatchingPreview();
            this.stopWatchingPreview = null;
        }

        return Preview.prototype.destroy.call(this);
    };

    /**
     * The form needs an appearance to open with; a component has one.
     *
     * @inheritdoc
     */
    ComponentPreview.prototype.openEdit = function () {
        if (!this.contentType.dataStore.get('appearance')) {
            this.contentType.dataStore.set('appearance', 'default');
        }

        return Preview.prototype.openEdit.call(this);
    };

    /**
     * Fetch the preview once the values have not changed for PREVIEW_DELAY, so a burst of updates (loading the
     * stage, saving the form) makes one request.
     */
    ComponentPreview.prototype.schedulePreview = function () {
        var self = this;

        if (this.destroyed) {
            return;
        }
        clearTimeout(this.fetchTimer);
        this.fetchTimer = setTimeout(function () {
            self.fetchTimer = null;
            self.fetchPreview();
        }, PREVIEW_DELAY);
    };

    /**
     * Ask the server for the component's HTML, with the values as they are now.
     *
     * One request runs at a time. Asked again while one runs, the preview drops that request's answer and asks
     * again, with the latest values, once it is done.
     */
    ComponentPreview.prototype.fetchPreview = function () {
        var self = this,
            component,
            state,
            values = {},
            request;

        if (this.destroyed) {
            return;
        }
        if (this.request) {
            this.fetchPending = true;

            return;
        }

        component = fieldValues.getComponent(this.config.name);
        state = this.contentType.dataStore.getState();
        Object.keys(component.fields).forEach(function (name) {
            if (state[name] !== undefined) {
                values[name] = fieldValues.toStored(component.fields[name], state[name]);
            }
        });

        this.loading(true);
        request = this.request = $.ajax(pbConfig.getConfig('preview_url'), {
            method: 'POST',
            data: {
                role: 'mageos_component',
                component: component.name,
                values: values
            }
        });

        /**
         * Whether the answer is still wanted: this preview exists, and its values did not change since.
         *
         * @returns {Boolean}
         */
        function isCurrent() {
            return !self.destroyed && self.request === request && !self.fetchPending;
        }

        request.done(function (response) {
            var data = response && response.data || {};

            if (!isCurrent()) {
                return;
            }
            if (data.content) {
                self.previewUnsanitizedHtml(data.content);
                self.displayPreview(true);
            } else {
                self.displayPreview(false);
                self.placeholderText(data.error || self.config.label);
            }
        }).fail(function (xhr, status) {
            if (isCurrent() && status !== 'abort') {
                self.displayPreview(false);
                self.placeholderText($t('The preview could not be loaded. Check your connection and try again.'));
            }
        }).always(function () {
            if (self.destroyed || self.request !== request) {
                return;
            }
            self.request = null;
            if (self.fetchPending) {
                self.fetchPending = false;
                self.fetchPreview();
            } else {
                self.loading(false);
            }
        });
    };

    return ComponentPreview;
});
