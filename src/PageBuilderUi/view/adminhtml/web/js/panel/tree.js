/**
 * The "Current tree" of the Page Builder panel: the stage's content types as a flat, indented list, and the
 * actions on them.
 *
 * Duplicate, edit and remove run the content type's own toolbar options, so each type keeps its rules (a column
 * that doesn't fit, the last slide or tab that can't be removed). Moving swaps an element with its neighbour in
 * the same container, through core's moveContentType, which is what dragging it on the stage does; it is offered
 * to the types whose toolbar has a "move" handle.
 */
define([
    'Magento_PageBuilder/js/drag-drop/move-content-type'
], function (moveContentTypeModule) {
    'use strict';

    var SUMMARY_KEYS = ['heading_text', 'title', 'button_text', 'link_text', 'text', 'name', 'content', 'html'],
        SUMMARY_LENGTH = 40;

    /**
     * @param {Object} contentType
     * @returns {Array}
     */
    function getChildren(contentType) {
        return typeof contentType.getChildren === 'function' ? contentType.getChildren()() : [];
    }

    /**
     * @param {Object} contentType
     * @param {String} code
     * @returns {Object|undefined}
     */
    function getOption(contentType, code) {
        var preview = contentType.preview,
            menu = preview && typeof preview.getOptions === 'function' ? preview.getOptions() : null;

        return menu ? menu.getOption(code) : undefined;
    }

    /**
     * @param {Object} contentType
     * @param {String} code
     * @returns {Boolean}
     */
    function hasEnabledOption(contentType, code) {
        var option = getOption(contentType, code);

        return !!option && !option.isDisabled();
    }

    /**
     * @param {String} html
     * @returns {String}
     */
    function toPlainText(html) {
        return html.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
    }

    /**
     * Read from the default viewport's data, as that is what the editor sees first.
     *
     * @param {Object} contentType
     * @returns {String}
     */
    function getSummary(contentType) {
        var state = contentType.dataStore && typeof contentType.dataStore.getState === 'function'
                ? contentType.dataStore.getState() : {},
            index,
            value;

        for (index = 0; index < SUMMARY_KEYS.length; index++) {
            value = typeof state[SUMMARY_KEYS[index]] === 'string' ? toPlainText(state[SUMMARY_KEYS[index]]) : '';

            if (value) {
                return value.length > SUMMARY_LENGTH ? value.slice(0, SUMMARY_LENGTH - 1) + '…' : value;
            }
        }

        return '';
    }

    /**
     * @param {Object} contentType
     * @param {Number} offset -1 for up, 1 for down.
     * @returns {Boolean}
     */
    function canMove(contentType, offset) {
        var siblings, target;

        if (!contentType.parentContentType || !getOption(contentType, 'move')) {
            return false;
        }
        siblings = getChildren(contentType.parentContentType);
        target = siblings.indexOf(contentType) + offset;

        return target >= 0 && target < siblings.length;
    }

    /**
     * Every content type under the root container, depth first, skipping the children of collapsed nodes.
     *
     * Reading each container's children() makes a computed that calls this depend on all of them, so it is
     * recalculated whenever an element is added, moved or removed anywhere on the stage.
     *
     * @param {Object} rootContainer
     * @param {Object} collapsed Content type ids of the collapsed nodes, as keys.
     * @returns {Array}
     */
    function flatten(rootContainer, collapsed) {
        var nodes = [];

        /**
         * @param {Object} parent
         * @param {Number} depth
         */
        function walk(parent, depth) {
            var siblings = getChildren(parent);

            siblings.forEach(function (contentType, index) {
                var children = getChildren(contentType),
                    isCollapsed = !!collapsed[contentType.id];

                nodes.push({
                    id: contentType.id,
                    contentType: contentType,
                    label: contentType.config.label,
                    icon: contentType.config.icon || '',
                    summary: getSummary(contentType),
                    depth: depth,
                    index: index,
                    hasChildren: children.length > 0,
                    isCollapsed: isCollapsed,
                    isHidden: !!contentType.preview && typeof contentType.preview.display === 'function'
                        && !contentType.preview.display(),
                    canMoveUp: canMove(contentType, -1),
                    canMoveDown: canMove(contentType, 1),
                    canDuplicate: hasEnabledOption(contentType, 'duplicate'),
                    canEdit: hasEnabledOption(contentType, 'edit'),
                    canRemove: hasEnabledOption(contentType, 'remove')
                });

                if (!isCollapsed) {
                    walk(contentType, depth + 1);
                }
            });
        }

        walk(rootContainer, 0);

        return nodes;
    }

    /**
     * @param {Object} contentType
     * @param {Number} offset -1 for up, 1 for down.
     * @returns {Boolean} Whether it moved.
     */
    function move(contentType, offset) {
        if (!canMove(contentType, offset)) {
            return false;
        }
        moveContentTypeModule.moveContentType(
            contentType,
            getChildren(contentType.parentContentType).indexOf(contentType) + offset
        );

        return true;
    }

    /**
     * Run one of the content type's toolbar options: duplicate, edit, remove.
     *
     * @param {Object} contentType
     * @param {String} code
     * @returns {Boolean} Whether the option was there and enabled.
     */
    function runOption(contentType, code) {
        var option = getOption(contentType, code);

        if (!option || option.isDisabled()) {
            return false;
        }
        option.action();

        return true;
    }

    return {
        flatten: flatten,
        canMove: canMove,
        move: move,
        runOption: runOption,
        getSummary: getSummary
    };
});
