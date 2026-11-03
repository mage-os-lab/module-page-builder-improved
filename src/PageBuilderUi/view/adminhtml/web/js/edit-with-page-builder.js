/**
 * "Edit with Page Builder": opens Page Builder in full screen as the edit page loads, when asked to.
 *
 * Asked by the grids' "Edit with Page Builder" action (the URL's #edit-with-page-builder), or by the header's
 * "Save page" (a flag in session storage, as saving reloads the page). The flag holds the page it was set on, and
 * only that page acts on it: a save that failed its validation doesn't reload, and the next page opened may be
 * another one. The page builder mixin opens the first stage that is ready, and consumes the request.
 *
 * As a component (x-magento-init, layout cms_page_edit.xml), it opens the fieldset holding the content first:
 * Page Builder doesn't start inside a closed fieldset.
 */
define([
    'uiRegistry'
], function (registry) {
    'use strict';

    var FRAGMENT = 'edit-with-page-builder',
        STORAGE_KEY = 'mageos-pagebuilder-reopen',
        // A save that reloads the page takes a few seconds; an older flag is from a save that didn't.
        MAX_AGE = 60000,
        // The admin URL's actions for an entity's form: saving a new one reloads its edit page.
        FORM_ACTIONS = ['new', 'edit'],
        consumed = false;

    /**
     * An admin path as the entity it is for: what comes before its form action (route and controller), the action,
     * and its parameters, without the secret key.
     *
     * @param {String} path E.g. /admin/catalog/product/edit/id/12/key/abc/.
     * @returns {{base: String, action: String, params: Object}}
     */
    function describePage(path) {
        var segments = String(path).split('/').filter(Boolean),
            index = segments.lastIndexOf('key'),
            params = {},
            rest,
            i;

        // The secret key is the URL's last parameter, and differs between actions.
        if (index !== -1) {
            segments.splice(index, 2);
        }
        index = segments.length - 1;
        while (index >= 0 && FORM_ACTIONS.indexOf(segments[index]) === -1) {
            index--;
        }
        if (index < 0) {
            return {base: segments.join('/'), action: '', params: params};
        }
        rest = segments.slice(index + 1);
        for (i = 0; i + 1 < rest.length; i += 2) {
            params[rest[i]] = rest[i + 1];
        }

        return {base: segments.slice(0, index).join('/'), action: segments[index], params: params};
    }

    /**
     * Whether the page saved from (a flag's path) is the one now open: the same entity's edit page, which may carry
     * more parameters (a product's attribute set), or the edit page of the entity a "new" page just created.
     *
     * @param {String} savedPath
     * @param {String} currentPath
     * @returns {Boolean}
     */
    function isSamePage(savedPath, currentPath) {
        var saved = describePage(savedPath),
            current = describePage(currentPath);

        if (saved.base !== current.base) {
            return false;
        }
        if (!saved.action) {
            return !current.action;
        }
        if (saved.action === 'new') {
            return current.action === 'edit';
        }

        return current.action === saved.action && Object.keys(saved.params).every(function (name) {
            return current.params[name] === saved.params[name];
        });
    }

    /**
     * @returns {{path: String, time: Number}|null}
     */
    function readFlag() {
        var flag;

        try {
            flag = JSON.parse(window.sessionStorage.getItem(STORAGE_KEY));
        } catch (e) {
            return null;
        }

        return flag && typeof flag.path === 'string' && typeof flag.time === 'number' ? flag : null;
    }

    /**
     * @returns {Boolean}
     */
    function isRequested() {
        var flag;

        if (consumed) {
            return false;
        }
        if (window.location.hash === '#' + FRAGMENT) {
            return true;
        }
        flag = readFlag();

        return !!flag && Date.now() - flag.time < MAX_AGE && isSamePage(flag.path, window.location.pathname);
    }

    /**
     * The request is for one stage, once: drop the flag, and the fragment, so a reload doesn't open it again.
     */
    function consume() {
        consumed = true;

        try {
            window.sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // Storage blocked: there is no flag to drop.
        }

        if (window.location.hash === '#' + FRAGMENT && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
    }

    /**
     * Ask the next load of this page to open Page Builder again.
     */
    function reopenAfterReload() {
        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                path: window.location.pathname,
                time: Date.now()
            }));
        } catch (e) {
            // Storage blocked: the editor just won't reopen after saving.
        }
    }

    /**
     * @param {Object} config
     * @param {String} config.fieldset Full name of the fieldset holding Page Builder, e.g.
     *     cms_page_form.cms_page_form.content.
     */
    function openFieldset(config) {
        if (!isRequested() || !config || !config.fieldset) {
            return;
        }

        registry.get(config.fieldset, function (fieldset) {
            if (typeof fieldset.opened === 'function') {
                fieldset.opened(true);
            }
        });
    }

    openFieldset.FRAGMENT = FRAGMENT;
    openFieldset.isRequested = isRequested;
    openFieldset.consume = consume;
    openFieldset.reopenAfterReload = reopenAfterReload;
    openFieldset.isSamePage = isSamePage;

    return openFieldset;
});
