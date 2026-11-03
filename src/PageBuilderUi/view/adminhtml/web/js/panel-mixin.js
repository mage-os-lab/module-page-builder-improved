/**
 * Reworks Page Builder's left panel with a fresh new UI: wider, collapsible, and with two tabs, "Add element" (core's elements, as a searchable grid of tiles) and
 * "Current tree" (the elements on the stage, with actions to move, duplicate, edit and remove them).
 *
 * The collapsed state is core's own hook: the stage template already puts "panel-collapsed" on the stage while
 * panel.isCollapsed is true. The collapsed state and the open tab are remembered per browser.
 */
define([
    'jquery',
    'knockout',
    'mage/translate',
    'Magento_PageBuilder/js/events',
    'MageOS_PageBuilderUi/js/panel/tree'
], function ($, ko, $t, events, tree) {
    'use strict';

    var STORAGE_KEY = 'mageos-pagebuilder-panel',
        TAB_ADD = 'add',
        TAB_TREE = 'tree',
        HIGHLIGHT_CLASS = 'mageos-tree-highlight',
        WRAPPER_SELECTOR = '.pagebuilder-content-type-wrapper',
        STAGE_EVENTS = '.mageosPanel';

    /**
     * @returns {Object}
     */
    function readState() {
        try {
            return JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || {};
        } catch (e) {
            return {};
        }
    }

    /**
     * @param {Object} state
     */
    function writeState(state) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            // Private mode or storage full: the panel just won't remember.
        }
    }

    return function (Panel) {
        var initListeners = Panel.prototype.initListeners,
            afterRender = Panel.prototype.afterRender,
            populateContentTypes = Panel.prototype.populateContentTypes;

        /**
         * initListeners runs once, from the constructor: set up this instance's state there.
         */
        Panel.prototype.initListeners = function () {
            var self = this,
                state = readState();

            this.template = 'MageOS_PageBuilderUi/panel';
            this.tabs = [
                {code: TAB_ADD, label: $t('Add element')},
                {code: TAB_TREE, label: $t('Current tree')}
            ];
            this.activeTab = ko.observable(state.tab === TAB_TREE ? TAB_TREE : TAB_ADD);
            this.isCollapsed = ko.observable(!!state.collapsed);
            this.collapsedNodes = ko.observable({});
            this.treeRevision = ko.observable(0);
            // The node clicked last, and the one whose element is under the mouse on the stage.
            this.selectedNodeId = ko.observable(null);
            this.hoveredNodeId = ko.observable(null);
            this.treeEmptyTitle = $t('Nothing on the stage yet');
            this.treeEmptyText = $t('Drag an element in from "Add element" to start building the page.');
            this.addElementLabel = $t('Add element');
            this.expandAllLabel = $t('Expand all');
            this.collapseAllLabel = $t('Collapse all');
            this.collapseTitle = $t('Collapse panel');
            this.expandTitle = $t('Expand panel');
            this.actionTitles = {
                moveUp: $t('Move up'),
                moveDown: $t('Move down'),
                duplicate: $t('Duplicate'),
                edit: $t('Edit'),
                remove: $t('Remove'),
                toggle: $t('Show or hide children')
            };

            /**
             * Only worked out while the tree tab is open; rate limited, as one change on the stage can update
             * several containers at once.
             */
            this.treeNodes = ko.pureComputed(function () {
                var stage = self.pageBuilder.stage;

                self.treeRevision();

                if (self.activeTab() !== TAB_TREE || self.isCollapsed() || !stage || !stage.rootContainer) {
                    return [];
                }

                return tree.flatten(stage.rootContainer, self.collapsedNodes());
            }).extend({rateLimit: 50});

            this.activeTab.subscribe(this.saveState, this);
            this.isCollapsed.subscribe(this.saveState, this);
            this.isCollapsed.subscribe(this.updateWrapperClass, this);

            // Element data isn't observable: refresh the summaries after each edit.
            events.on('stage:updateAfter', function (args) {
                if (args && args.stageId === self.id) {
                    self.treeRevision(self.treeRevision() + 1);
                }
            });

            initListeners.apply(this, arguments);
        };

        /**
         * Core's afterRender keeps the panel's element; then mark the stage wrapper around it, and follow the
         * mouse on the stage, so the tree shows which element it is over. Rendered again, the panel replaces its
         * handlers rather than add to them.
         */
        Panel.prototype.afterRender = function () {
            var self = this,
                namespace = STAGE_EVENTS + this.id;

            afterRender.apply(this, arguments);
            this.updateWrapperClass();

            if (this.element) {
                $(this.element).closest('.pagebuilder-stage-wrapper')
                    .off(namespace)
                    .on('mouseover' + namespace, WRAPPER_SELECTOR, function (event) {
                        // The innermost element: mouseover bubbles up through its containers' wrappers.
                        if ($(event.target).closest(WRAPPER_SELECTOR)[0] === this) {
                            self.hoveredNodeId(this.id || null);
                        }
                    })
                    .on('mouseleave' + namespace, '.pagebuilder-stage', function () {
                        self.hoveredNodeId(null);
                    });
            }
        };

        /**
         * Show every section's elements: with tiles, they fit.
         */
        Panel.prototype.populateContentTypes = function () {
            populateContentTypes.apply(this, arguments);
            this.menuSections().forEach(function (section) {
                section.active(true);
            });
        };

        Panel.prototype.saveState = function () {
            writeState({tab: this.activeTab(), collapsed: this.isCollapsed()});
        };

        /**
         * The stage and the full screen header sit next to the panel, in its .pagebuilder-stage-wrapper: mark
         * that, so their offsets follow the panel's width.
         */
        Panel.prototype.updateWrapperClass = function () {
            if (this.element) {
                $(this.element).closest('.pagebuilder-stage-wrapper')
                    .addClass('mageos-panel-host')
                    .toggleClass('mageos-panel-collapsed', this.isCollapsed());
            }
        };

        /**
         * @param {String} code
         * @returns {String}
         */
        Panel.prototype.getTabId = function (code) {
            return 'mageos-panel-tab-' + code + '-' + this.id;
        };

        /**
         * @param {String} code
         * @returns {String}
         */
        Panel.prototype.getPaneId = function (code) {
            return 'mageos-panel-pane-' + code + '-' + this.id;
        };

        /**
         * The tabs' keys (WAI-ARIA tabs, activated as they are focused): the arrows go to the previous or next
         * tab, round, Home and End to the first and last. Only the open tab is in the page's tab order. In the
         * collapsed strip, the panel stays folded until a tab is picked.
         *
         * @param {Object} tab
         * @param {jQuery.Event} event
         * @returns {Boolean} Whether the browser should handle the key, as Knockout prevents it otherwise.
         */
        Panel.prototype.onTabKeydown = function (tab, event) {
            var codes = this.tabs.map(function (item) {
                    return item.code;
                }),
                index = codes.indexOf(tab.code),
                next;

            switch (event.key) {
                case 'ArrowRight':
                    next = (index + 1) % codes.length;
                    break;
                case 'ArrowLeft':
                    next = (index - 1 + codes.length) % codes.length;
                    break;
                case 'Home':
                    next = 0;
                    break;
                case 'End':
                    next = codes.length - 1;
                    break;
                default:
                    return true;
            }

            this.clearHighlight();
            this.activeTab(codes[next]);
            this.focusTab(codes[next]);

            return false;
        };

        /**
         * @param {String} code
         */
        Panel.prototype.focusTab = function (code) {
            $(this.element).find('.mageos-panel-tab[data-tab="' + code + '"]').trigger('focus');
        };

        /**
         * @param {String} code
         */
        Panel.prototype.selectTab = function (code) {
            this.clearHighlight();
            this.activeTab(code);
            this.isCollapsed(false);
        };

        Panel.prototype.toggleCollapsed = function () {
            this.clearHighlight();
            this.isCollapsed(!this.isCollapsed());
        };

        /**
         * @param {Object} node
         */
        Panel.prototype.toggleNode = function (node) {
            var collapsed = $.extend({}, this.collapsedNodes());

            if (collapsed[node.id]) {
                delete collapsed[node.id];
            } else {
                collapsed[node.id] = true;
            }
            this.collapsedNodes(collapsed);
        };

        Panel.prototype.expandAll = function () {
            this.collapsedNodes({});
        };

        /**
         * Fold every container, so only the top level (the rows) shows.
         */
        Panel.prototype.collapseAll = function () {
            var collapsed = {};

            tree.flatten(this.pageBuilder.stage.rootContainer, {}).forEach(function (node) {
                if (node.hasChildren) {
                    collapsed[node.id] = true;
                }
            });
            this.collapsedNodes(collapsed);
        };

        /**
         * @param {Object} node
         * @param {Number} offset
         */
        Panel.prototype.moveNode = function (node, offset) {
            var self = this;

            this.clearHighlight();

            if (tree.move(node.contentType, offset)) {
                // After the stage has re-rendered the element in its new place.
                window.setTimeout(function () {
                    self.focusNode(node);
                }, 100);
            }
        };

        /**
         * @param {Object} node
         * @param {String} code duplicate, edit or remove.
         */
        Panel.prototype.runNodeOption = function (node, code) {
            this.clearHighlight();
            tree.runOption(node.contentType, code);
        };

        /**
         * Select the node, and show its element on the stage.
         *
         * @param {Object} node
         */
        Panel.prototype.selectNode = function (node) {
            this.selectedNodeId(node.id);
            this.focusNode(node);
        };

        /**
         * Scroll the element into view on the stage, and flash it.
         *
         * @param {Object} node
         */
        Panel.prototype.focusNode = function (node) {
            var element = node.contentType.preview && node.contentType.preview.wrapperElement;

            if (!element) {
                return;
            }
            if (typeof element.scrollIntoView === 'function') {
                element.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
            // Reading the width in between restarts the animation when the same element is focused again.
            $(element).removeClass(HIGHLIGHT_CLASS + '-flash').width();
            $(element).addClass(HIGHLIGHT_CLASS + '-flash');
            window.setTimeout(function () {
                $(element).removeClass(HIGHLIGHT_CLASS + '-flash');
            }, 1200);
        };

        /**
         * Outline the element on the stage while its node is hovered.
         *
         * @param {Object} node
         * @param {Boolean} on
         */
        Panel.prototype.highlightNode = function (node, on) {
            var element = node.contentType.preview && node.contentType.preview.wrapperElement;

            this.clearHighlight();

            if (element && on) {
                $(element).addClass(HIGHLIGHT_CLASS);
                this.highlightedElement = element;
            }
        };

        /**
         * The tree is redrawn when the stage changes, without a mouseleave for the node under the mouse: drop the
         * outline before each action, rather than wait for one.
         */
        Panel.prototype.clearHighlight = function () {
            if (this.highlightedElement) {
                $(this.highlightedElement).removeClass(HIGHLIGHT_CLASS);
                this.highlightedElement = null;
            }
        };

        return Panel;
    };
});
