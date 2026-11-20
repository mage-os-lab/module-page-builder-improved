/**
 * Core's confirmation dialog (Magento_PageBuilder/js/modal/dismissible-confirm), as "Confirm Item Removal" uses
 * it: Escape closes it like Cancel, and it takes the focus as it opens.
 *
 * The dialog is a Magento_Ui prompt, which focuses its prompt field once open; this one has none, so the focus
 * stayed on the button that opened it, outside the dialog, where Magento_Ui's Escape handler doesn't listen.
 * Here the dialog focuses Cancel (OK when it has no Cancel), and Escape is caught on the document while it is
 * open, wherever the focus is. Closing gives the focus back to the button that opened it, as core does.
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return function (dismissibleConfirm) {
        $.widget('mage.dismissibleConfirm', $.mage.dismissibleConfirm, {
            /**
             * A dismissed dialog doesn't open: it runs its actions straight away.
             *
             * @returns {*}
             */
            openModal: function () {
                var result;

                this.options.focus = this.modal.find('.action-dismiss').length ? '.action-dismiss' : '.action-accept';
                result = this._super();

                if (this.options.isOpen) {
                    this._listenForEscape();

                    // Core focuses on the end of the opening transition, which doesn't run without one.
                    if (!this.options.transitionEvent) {
                        this._setFocus('end', 'opened');
                    }
                }

                return result;
            },

            /**
             * @param {Boolean} result
             * @returns {*}
             */
            closeModal: function (result) {
                var returned = this._super(result);

                if (!this.options.isOpen) {
                    this._stopListeningForEscape();
                }

                return returned;
            },

            /**
             * In the capture phase, so the Escape doesn't also reach what is under the dialog (the Templates
             * menu, an edit form).
             *
             * @private
             */
            _listenForEscape: function () {
                var self = this;

                this._stopListeningForEscape();
                this._escapeListener = function (event) {
                    if ((event.key === 'Escape' || event.key === 'Esc') && self.options.isOpen) {
                        event.preventDefault();
                        event.stopPropagation();
                        self.closeModal(false);
                    }
                };
                document.addEventListener('keydown', this._escapeListener, true);
            },

            /**
             * @private
             */
            _stopListeningForEscape: function () {
                if (this._escapeListener) {
                    document.removeEventListener('keydown', this._escapeListener, true);
                    this._escapeListener = null;
                }
            }
        });

        return dismissibleConfirm;
    };
});
