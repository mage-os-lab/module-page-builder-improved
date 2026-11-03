/**
 * Shows the top level fieldsets of an element's edit form ("Content", "Advanced", ...) as tabs, like the panel's
 * "Add element | Current tree".
 *
 * The form is rendered by Magento_Ui, asynchronously, and again when the element's appearance changes, so the
 * tabs follow its DOM: they are worked out again after each change. A fieldset hidden by the form (an appearance
 * that doesn't use it) has no tab. A tab with an invalid field is marked, and opened when the active one has none.
 *
 * The tabs follow the WAI-ARIA tabs pattern: each tab controls its fieldset (aria-controls, aria-labelledby), only
 * the open tab is in the page's tab order, and the arrows, Home and End move between the tabs, opening them.
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    var WRAPPER_SELECTOR = '.fieldset-wrapper',
        TAB_CLASS = 'mageos-form-tab',
        HIDDEN_CLASS = 'mageos-form-tab-hidden',
        // An invalid field, or a fieldset title Magento_Ui marks as invalid. Not just any ._error: every fieldset
        // title holds a hidden "admin__page-nav-item-message _error" tooltip.
        ERROR_SELECTOR = '.admin__field._error, .fieldset-wrapper-title strong._error',
        // Gives each form's tabs and fieldsets their own ids.
        lastUid = 0,
        // Core's general fieldsets have no label.
        FALLBACK_LABELS = {
            general: $t('Content'),
            'appearance_fieldset': $t('Appearance'),
            advanced: $t('Advanced')
        };

    /**
     * @param {Element} wrapper
     * @returns {String}
     */
    function getLabel(wrapper) {
        var title = $(wrapper).children('.fieldset-wrapper-title').find('span')[0],
            label = title ? title.textContent.trim() : '',
            index = wrapper.getAttribute('data-index') || '';

        if (label) {
            return label;
        }

        return FALLBACK_LABELS[index] || index.replace(/_/g, ' ').replace(/^./, function (letter) {
            return letter.toUpperCase();
        });
    }

    /**
     * @param {Element} container The form's container.
     * @param {Element} barParent Where the tab bar goes, as its first child.
     * @constructor
     */
    function FormTabs(container, barParent) {
        this.container = container;
        this.barParent = barParent;
        this.bar = $('<div class="mageos-form-tabs" role="tablist"></div>')[0];
        this.active = null;
        this.errorKeys = '';
        this.signature = '';
        this.scheduled = false;
        this.observer = null;
        this.uid = ++lastUid;

        $(this.bar)
            .on('click', 'button', this.onTabClick.bind(this))
            .on('keydown', 'button', this.onTabKeydown.bind(this));
    }

    FormTabs.prototype = {
        /**
         * Follow the form's changes. Our own changes set off one more update, which then changes nothing.
         */
        observe: function () {
            var self = this;

            if (this.observer || typeof window.MutationObserver !== 'function') {
                return;
            }
            this.observer = new window.MutationObserver(function () {
                self.schedule();
            });
            this.observer.observe(this.container, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
            this.schedule();
        },

        disconnect: function () {
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
        },

        /**
         * A new form: start on its first tab.
         */
        reset: function () {
            this.active = null;
            this.errorKeys = '';
            this.schedule();
        },

        schedule: function () {
            var self = this;

            if (!this.scheduled) {
                this.scheduled = true;
                window.setTimeout(function () {
                    self.scheduled = false;
                    self.update();
                }, 0);
            }
        },

        /**
         * The top level fieldsets: no fieldset of the form around them.
         *
         * @returns {Array}
         */
        getWrappers: function () {
            var container = this.container;

            return $(container).find(WRAPPER_SELECTOR).filter(function () {
                return $(this).parent().closest(WRAPPER_SELECTOR, container).length === 0;
            }).toArray();
        },

        update: function () {
            var self = this,
                wrappers = this.getWrappers(),
                tabs = [],
                errorTabs,
                signature;

            wrappers.forEach(function (wrapper, position) {
                // The form's own visible binding.
                if (wrapper.style.display === 'none') {
                    $(wrapper).removeClass(TAB_CLASS + ' ' + HIDDEN_CLASS);

                    return;
                }
                tabs.push({
                    key: wrapper.getAttribute('data-index') || String(position),
                    label: getLabel(wrapper),
                    wrapper: wrapper,
                    hasError: $(wrapper).find(ERROR_SELECTOR).length > 0
                });
            });

            if (tabs.length < 2) {
                $(wrappers).removeClass(TAB_CLASS + ' ' + HIDDEN_CLASS).filter('[role="tabpanel"]')
                    .removeAttr('role aria-labelledby');
                $(this.container).removeClass('mageos-form-tabbed');
                $(this.bar).detach();
                this.signature = '';

                return;
            }

            errorTabs = tabs.filter(function (tab) {
                return tab.hasError;
            });

            if (!this.findTab(tabs, this.active)) {
                this.active = tabs[0].key;
            }
            // New errors out of sight: show the first of them.
            if (errorTabs.length && errorTabs.map(function (tab) {
                return tab.key;
            }).join() !== this.errorKeys && !this.findTab(tabs, this.active).hasError) {
                this.active = errorTabs[0].key;
            }
            this.errorKeys = errorTabs.map(function (tab) {
                return tab.key;
            }).join();

            tabs.forEach(function (tab) {
                var title = $(tab.wrapper).children('.fieldset-wrapper-title[data-state-collapsible="closed"]');

                tab.id = self.getId('tab', tab.key);
                if (!tab.wrapper.id) {
                    tab.wrapper.id = self.getId('panel', tab.key);
                }
                $(tab.wrapper).addClass(TAB_CLASS).toggleClass(HIDDEN_CLASS, tab.key !== self.active)
                    .attr({role: 'tabpanel', 'aria-labelledby': tab.id});

                // A collapsed fieldset doesn't render its fields: the tab is how it is opened now.
                if (tab.key === self.active && title.length) {
                    title.trigger('click');
                }
            });
            $(this.container).addClass('mageos-form-tabbed');

            signature = JSON.stringify(tabs.map(function (tab) {
                return [tab.key, tab.label, tab.hasError, tab.key === self.active, tab.wrapper.id];
            }));

            if (signature !== this.signature || !this.bar.parentNode) {
                this.signature = signature;
                this.renderBar(tabs);
            }
        },

        /**
         * @param {String} type tab or panel.
         * @param {String} key
         * @returns {String}
         */
        getId: function (type, key) {
            return 'mageos-form-' + type + '-' + this.uid + '-' + key.replace(/[^\w-]/g, '_');
        },

        /**
         * @param {Array} tabs
         * @param {String} key
         * @returns {Object|undefined}
         */
        findTab: function (tabs, key) {
            return tabs.filter(function (tab) {
                return tab.key === key;
            })[0];
        },

        /**
         * @param {Array} tabs
         */
        renderBar: function (tabs) {
            var self = this;

            $(this.bar).empty().append(tabs.map(function (tab) {
                return $('<button type="button" role="tab" class="mageos-form-tab-button"></button>')
                    .text(tab.label)
                    .attr({
                        id: tab.id,
                        'data-tab': tab.key,
                        'aria-controls': tab.wrapper.id,
                        'aria-selected': tab.key === self.active ? 'true' : 'false',
                        tabindex: tab.key === self.active ? '0' : '-1'
                    })
                    .toggleClass('active', tab.key === self.active)
                    .toggleClass('has-error', tab.hasError);
            }));

            if (this.bar.parentNode !== this.barParent || this.barParent.firstChild !== this.bar) {
                $(this.barParent).prepend(this.bar);
            }
        },

        /**
         * @param {jQuery.Event} event
         */
        onTabClick: function (event) {
            var hadFocus = window.document.activeElement === event.currentTarget;

            this.active = event.currentTarget.getAttribute('data-tab');
            this.update();
            // Picked with Enter or Space: the bar is drawn again, keep the focus on the tab.
            if (hadFocus) {
                this.focusActive();
            }
        },

        focusActive: function () {
            var active = this.active;

            $(this.bar).children('button').filter(function () {
                return this.getAttribute('data-tab') === active;
            }).trigger('focus');
        },

        /**
         * @param {jQuery.Event} event
         */
        onTabKeydown: function (event) {
            var buttons = $(this.bar).children('button').toArray(),
                index = buttons.indexOf(event.currentTarget),
                next;

            switch (event.key) {
                case 'ArrowRight':
                    next = (index + 1) % buttons.length;
                    break;
                case 'ArrowLeft':
                    next = (index - 1 + buttons.length) % buttons.length;
                    break;
                case 'Home':
                    next = 0;
                    break;
                case 'End':
                    next = buttons.length - 1;
                    break;
                default:
                    return;
            }
            event.preventDefault();
            this.active = buttons[next].getAttribute('data-tab');
            this.update();
            // The bar is drawn again: focus the new button.
            this.focusActive();
        }
    };

    return {
        FormTabs: FormTabs,

        /**
         * @param {Element} container
         * @param {Element} [barParent] The container, if not given.
         * @returns {FormTabs}
         */
        attach: function (container, barParent) {
            var tabs = new FormTabs(container, barParent || container);

            tabs.observe();

            return tabs;
        }
    };
});
