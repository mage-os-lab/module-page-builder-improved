/**
 * Page Builder's header (template/page-builder.html): "Apply Template" and "Save as Template" in one "Templates"
 * menu, and a primary "Save page" button that saves the form Page Builder is in. Also opens Page Builder in full
 * screen when the page was opened with "Edit with Page Builder", or reloaded by "Save page".
 */
define([
    'jquery',
    'knockout',
    'underscore',
    'mage/translate',
    'Magento_PageBuilder/js/events',
    'MageOS_PageBuilderUi/js/edit-with-page-builder'
], function ($, ko, _, $t, events, editWithPageBuilder) {
    'use strict';

    // The page's own Save: the main part of the split button on CMS pages, blocks and products, or a plain #save.
    var SAVE_BUTTON_SELECTOR = 'button#save-button, button#save',
        // A form in a modal (an insertForm) has its Save in the modal's header, and the modal sits in the body.
        MODAL_SELECTOR = '.modal-inner-wrap',
        MENU_EVENTS = '.mageosTemplateMenu';

    /**
     * The Save of the form an element is in: the one in its nearest ancestor that holds one, so a Page Builder in
     * a modal's form saves that form, and the page's, the page. Not past the modal the element is in, and not in
     * another modal: the page's Save isn't the modal form's, nor the other way round.
     *
     * @param {Element} element
     * @returns {jQuery}
     */
    function findSaveButton(element) {
        var modal = $(element).closest(MODAL_SELECTOR)[0] || null,
            button = $();

        $(element).parents().each(function () {
            button = $(this).find(SAVE_BUTTON_SELECTOR).filter(function () {
                return ($(this).closest(MODAL_SELECTOR)[0] || null) === modal;
            }).first();

            return !button.length && this !== modal;
        });

        return button;
    }

    return function (PageBuilder) {
        var initListeners = PageBuilder.prototype.initListeners,
            destroy = PageBuilder.prototype.destroy;

        /**
         * initListeners runs once, from the constructor: set up this instance's state there.
         */
        PageBuilder.prototype.initListeners = function () {
            var self = this;

            this.template = 'MageOS_PageBuilderUi/page-builder';
            this.isTemplateMenuOpen = ko.observable(false);
            this.templateMenuLabel = $t('Templates');
            this.applyTemplateLabel = $t('Apply Template');
            this.saveTemplateLabel = $t('Save as Template');
            this.savePageLabel = $t('Save page');
            this.pageSaveButton = ko.observable($());
            this.headerButtons = null;
            this.isTemplateMenuOpen.subscribe(this.onTemplateMenuChange, this);

            events.on('stage:' + this.id + ':readyAfter', function () {
                if (editWithPageBuilder.isRequested() && !self.isFullScreen()) {
                    editWithPageBuilder.consume();
                    // After the stage has rendered: going full screen measures it.
                    _.defer(function () {
                        self.toggleFullScreen({animate: true});
                    });
                }
            });

            initListeners.apply(this, arguments);
        };

        /**
         * @returns {String}
         */
        PageBuilder.prototype.getFullScreenLabel = function () {
            return this.isFullScreen() ? $t('Close Full Screen') : $t('Open in Full Screen');
        };

        PageBuilder.prototype.destroy = function () {
            $(document).off(MENU_EVENTS + this.id);

            return typeof destroy === 'function' ? destroy.apply(this, arguments) : undefined;
        };

        /**
         * @returns {Boolean}
         */
        PageBuilder.prototype.hasTemplateActions = function () {
            return !!(this.isAllowedTemplateApply || this.isAllowedTemplateSave);
        };

        /**
         * The header's buttons are rendered: find the Save of the form this Page Builder is in. They are rendered
         * again after a snapshot's preview.
         *
         * @param {Element} element
         */
        PageBuilder.prototype.afterRenderHeaderButtons = function (element) {
            this.headerButtons = element;
            this.pageSaveButton(findSaveButton(element));
        };

        PageBuilder.prototype.toggleTemplateMenu = function () {
            this.isTemplateMenuOpen(!this.isTemplateMenuOpen());
        };

        /**
         * While the menu is open, the next click anywhere closes it (menu items included, not the click that opened
         * it), and so does Escape, which also takes the focus back to the menu's button. One set of handlers per
         * Page Builder, dropped as the menu closes.
         *
         * @param {Boolean} isOpen
         */
        PageBuilder.prototype.onTemplateMenuChange = function (isOpen) {
            var self = this,
                namespace = MENU_EVENTS + this.id;

            $(document).off(namespace);

            if (!isOpen) {
                return;
            }
            _.defer(function () {
                if (!self.isTemplateMenuOpen()) {
                    return;
                }
                $(document).off(namespace)
                    .on('click' + namespace, function () {
                        self.isTemplateMenuOpen(false);
                    })
                    .on('keydown' + namespace, function (event) {
                        if (event.key === 'Escape' || event.key === 'Esc') {
                            self.isTemplateMenuOpen(false);
                            $(self.headerButtons).find('.mageos-header-menu-toggle').trigger('focus');
                        }
                    });
            });
        };

        PageBuilder.prototype.applyTemplate = function () {
            this.isTemplateMenuOpen(false);
            this.toggleTemplateManger();
        };

        PageBuilder.prototype.saveTemplate = function () {
            this.isTemplateMenuOpen(false);
            this.saveAsTemplate();
        };

        /**
         * @returns {jQuery}
         */
        PageBuilder.prototype.getPageSaveButton = function () {
            return this.pageSaveButton();
        };

        /**
         * @returns {Boolean}
         */
        PageBuilder.prototype.canSavePage = function () {
            return this.getPageSaveButton().length > 0;
        };

        /**
         * Click the page's own Save, so its validation and redirect apply. Saving reloads the page: come back to
         * the full screen editor if that is where it was saved from.
         */
        PageBuilder.prototype.savePage = function () {
            var button = this.getPageSaveButton();

            if (!button.length) {
                return;
            }
            if (this.isFullScreen()) {
                editWithPageBuilder.reopenAfterReload();
            }
            button.trigger('click');
        };

        return PageBuilder;
    };
});
