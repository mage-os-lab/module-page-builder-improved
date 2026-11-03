/**
 * The element edit form's modal: a light sidebar like the panel (.mageos-pb-edit-modal, styled in _module.less),
 * with the form's fieldsets as tabs.
 */
define([
    'jquery',
    'Magento_PageBuilder/js/events',
    'MageOS_PageBuilderUi/js/form/tabs'
], function ($, events, formTabs) {
    'use strict';

    return function (Modal) {
        return Modal.extend({
            defaults: {
                options: {
                    modalClass: 'mageos-pb-edit-modal'
                }
            },

            /**
             * Runs once the modal's element is rendered; the form is inserted in it later, on each edit.
             *
             * @param {Element} element
             */
            initModal: function (element) {
                var result = this._super(element);

                if (!this.formTabs) {
                    this.formTabs = formTabs.attach(element, $(element).closest('.modal-content')[0] || element);
                    events.on('form:renderAfter', function () {
                        this.formTabs.reset();
                    }.bind(this));
                }

                return result;
            }
        });
    };
});
