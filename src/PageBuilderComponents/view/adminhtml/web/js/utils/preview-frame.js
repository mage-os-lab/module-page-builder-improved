/**
 * Keeps each component preview iframe on the stage as tall as its content.
 *
 * The renderer wraps the storefront HTML in `<iframe data-mageos-pbc-preview srcdoc="...">`. Its height is set
 * when its document loads, and again whenever the document's body changes size: images loading, fonts, or the
 * stage getting narrower or wider (which can change the component's responsive layout).
 *
 * One capture-phase load listener on the document finds them: load does not bubble, but it passes the iframe's
 * ancestors in the capture phase, and an iframe loads its document whenever it is inserted or moved. Previews
 * already loaded when watching starts are sized then. Nothing runs on the admin's other DOM changes.
 */
define([], function () {
    'use strict';

    var SELECTOR = 'iframe[data-mageos-pbc-preview]',
        observers = typeof WeakMap === 'function' ? new WeakMap() : null,
        warned = false;

    /**
     * The iframe's body, or null when its document cannot be read.
     *
     * @param {HTMLIFrameElement} frame
     * @returns {HTMLElement|null}
     */
    function bodyOf(frame) {
        var body = null;

        try {
            body = frame.contentDocument && frame.contentDocument.body;
        } catch (e) {
            body = null;
        }
        if (!body && !warned && frame.contentWindow) {
            warned = true;
            // A same-origin srcdoc document is always readable; something made it cross-origin (a sandbox
            // attribute, document.domain). The preview then keeps the browser's default iframe height.
            window.console && console.warn('MageOS_PageBuilderComponents: cannot read a preview iframe', frame);
        }

        return body;
    }

    /**
     * Set the iframe's height to its body's. The body's own height is used, not the root element's, which is
     * at least the iframe's current height and so could never shrink.
     *
     * @param {HTMLIFrameElement} frame
     */
    function fit(frame) {
        var body = bodyOf(frame);

        if (body) {
            frame.style.height = Math.ceil(body.getBoundingClientRect().height) + 'px';
        }
    }

    /**
     * Fit the iframe now, and watch its current document's body.
     *
     * @param {HTMLIFrameElement} frame
     */
    function track(frame) {
        var body = bodyOf(frame),
            // The iframe's own: an observer only sees layout changes in its own window's documents.
            FrameResizeObserver = frame.contentWindow && frame.contentWindow.ResizeObserver,
            previous = observers && observers.get(frame),
            observer;

        fit(frame);
        if (!body || (previous && previous.body === body)) {
            return;
        }
        if (previous) {
            previous.observer.disconnect();
        }

        if (observers && typeof FrameResizeObserver === 'function') {
            observer = new FrameResizeObserver(function () {
                // Next frame: resizing the iframe inside the callback makes browsers report a loop. The stage
                // page's frame, not the iframe's: browsers throttle animation frames in iframes out of view.
                window.requestAnimationFrame(function () {
                    fit(frame);
                });
            });
            observer.observe(body);
            observers.set(frame, {observer: observer, body: body});
        }
    }

    /**
     * Track a preview iframe whose srcdoc document has already loaded.
     *
     * @param {HTMLIFrameElement} frame
     */
    function trackIfLoaded(frame) {
        var doc;

        try {
            doc = frame.contentDocument;
        } catch (e) {
            doc = null;
        }
        if (doc && doc.readyState === 'complete' && doc.body && doc.URL === 'about:srcdoc') {
            track(frame);
        }
    }

    return {
        /**
         * Start sizing previews in a document. Calling it again for the same document does nothing.
         *
         * @param {Document} doc
         */
        watch: function (doc) {
            if (doc.mageosPbcPreviewFrames) {
                return;
            }
            doc.mageosPbcPreviewFrames = true;

            doc.addEventListener('load', function (event) {
                var target = event.target;

                if (target && target.nodeType === 1 && target.matches(SELECTOR)) {
                    track(target);
                }
            }, true);

            // Previews already in the page, loaded before the listener was added.
            Array.prototype.forEach.call(doc.querySelectorAll(SELECTOR), trackIfLoaded);
        },

        fit: fit
    };
});
