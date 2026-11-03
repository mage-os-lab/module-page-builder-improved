/**
 * Tells whether a component's rendered preview takes (next to) no space on the stage: a component just added,
 * with its fields still empty, often renders nothing visible, and an iframe preview is then sized to 0px. The
 * stage shows a placeholder instead, so there is something to see, hover and edit.
 *
 * The element is watched with a ResizeObserver, so the answer follows the iframe being sized to its content,
 * images loading, and the editor filling in fields.
 */
define([], function () {
    'use strict';

    // Below this, a preview is too small to find and hover on the stage.
    var MIN_HEIGHT = 8;

    /**
     * @param {Element} element
     * @returns {Boolean}
     */
    function isEmpty(element) {
        return element.getBoundingClientRect().height < MIN_HEIGHT;
    }

    return {
        MIN_HEIGHT: MIN_HEIGHT,
        isEmpty: isEmpty,

        /**
         * Call back with isEmpty now, and whenever the element changes size.
         *
         * @param {Element} element
         * @param {Function} callback Gets a Boolean.
         * @returns {Function} Stops watching.
         */
        watch: function (element, callback) {
            var observer;

            callback(isEmpty(element));

            if (typeof window.ResizeObserver !== 'function') {
                return function () {};
            }
            observer = new window.ResizeObserver(function () {
                // A detached element measures 0: it is on its way out, not empty.
                if (element.isConnected !== false) {
                    callback(isEmpty(element));
                }
            });
            observer.observe(element);

            return function () {
                observer.disconnect();
            };
        }
    };
});
