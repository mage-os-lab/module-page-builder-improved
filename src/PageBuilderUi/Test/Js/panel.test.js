/**
 * Tests the reworked Page Builder panel: the "Current tree" (js/panel/tree.js) on a stage built from fake
 * content types, moved with core's real moveContentType, and the panel mixin (js/panel-mixin.js) on a stand-in
 * for core's Panel. Also which page "Save page" reopens Page Builder on (js/edit-with-page-builder.js), and
 * that core's templates the module copies (page-builder.html, panel.html) are still the ones it copied.
 *
 * Needs Knockout: Magento's lib/web/knockoutjs/knockout.js, or the knockout npm package.
 *
 * Run from the module root: node Test/Js/panel.test.js [path/to/magento/module-page-builder] [path/to/knockout.js]
 * Without paths, they are looked up under vendor/ and in the Magento root four levels up.
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const magentoRoot = path.resolve(root, '../../../..');

/**
 * @param {Array} candidates
 * @returns {String|undefined}
 */
function firstExisting(candidates) {
    return candidates.find(function (candidate) {
        return candidate && fs.existsSync(candidate);
    });
}

const pageBuilderRoot = firstExisting([
    process.argv[2],
    path.join(root, 'vendor/magento/module-page-builder'),
    path.join(magentoRoot, 'vendor/magento/module-page-builder'),
    // Mage-OS installs its own package, which replaces magento/module-page-builder.
    path.join(root, 'vendor/mage-os/module-page-builder'),
    path.join(magentoRoot, 'vendor/mage-os/module-page-builder')
]);
const knockoutFile = firstExisting([
    process.argv[3],
    path.join(root, 'vendor/magento/magento2-base/lib/web/knockoutjs/knockout.js'),
    path.join(magentoRoot, 'lib/web/knockoutjs/knockout.js')
]);

// --- Core's templates the module copies: unchanged since they were copied ---

/**
 * A template without what differs between the Page Builder releases the module supports (2.2.6, 2.2.7) but
 * changes nothing on the page: line endings and trailing spaces, the license comment at the top (Magento's in
 * 2.2.6, Adobe's in 2.2.7), and a slash before `>` (2.2.7 dropped the one closing the search input in panel.html;
 * HTML ignores that slash, so both parse the same).
 *
 * @param {String} file
 * @returns {String}
 */
function readNormalized(file) {
    return fs.readFileSync(file, 'utf8')
        .replace(/^\s*<!--[\s\S]*?-->\s*/, '')
        .split(/\r?\n/)
        .map((line) => line.replace(/\s+$/, '').replace(/\s*\/>/g, '>'))
        .join('\n');
}

/**
 * The lines that differ, as - (before) and + (after), with their line numbers; from the longest common subsequence.
 *
 * @param {String} before
 * @param {String} after
 * @returns {String}
 */
function lineDiff(before, after) {
    const a = before.split('\n');
    const b = after.split('\n');
    const common = a.map(() => new Array(b.length + 1).fill(0)).concat([new Array(b.length + 1).fill(0)]);
    const lines = [];
    let i;
    let j;

    for (i = a.length - 1; i >= 0; i--) {
        for (j = b.length - 1; j >= 0; j--) {
            common[i][j] = a[i] === b[j] ? common[i + 1][j + 1] + 1 : Math.max(common[i + 1][j], common[i][j + 1]);
        }
    }
    for (i = 0, j = 0; i < a.length || j < b.length;) {
        if (i < a.length && j < b.length && a[i] === b[j]) {
            i++;
            j++;
        } else if (i < a.length && (j === b.length || common[i + 1][j] >= common[i][j + 1])) {
            lines.push('-' + (i + 1) + ': ' + a[i++]);
        } else {
            lines.push('+' + (j + 1) + ': ' + b[j++]);
        }
    }

    return lines.join('\n');
}

['page-builder.html', 'panel.html'].forEach(function (name) {
    const copied = readNormalized(path.join(__dirname, '_files/core-templates', name + '.txt'));
    const current = readNormalized(path.join(pageBuilderRoot, 'view/adminhtml/web/template', name));

    assert.ok(copied === current, 'Magento_PageBuilder::' + name + ' changed since view/adminhtml/web/template/' +
        name + ' was copied from it: re-sync the copy with the change, then copy the new core template to ' +
        'Test/Js/_files/core-templates/' + name + '.txt. Core changed (- copied from, + installed):\n' +
        lineDiff(copied, current));
});

assert.ok(pageBuilderRoot, 'Magento_PageBuilder not found; pass its path as the first argument');

const moveContentTypeFile = path.join(pageBuilderRoot, 'view/adminhtml/web/js/drag-drop/move-content-type.js');

const ko = knockoutFile ? require(knockoutFile) : require('knockout');

// Rate limited computeds notify asynchronously; the tests read them synchronously.
ko.options.deferUpdates = false;

const triggered = [];
const listeners = {};
const storage = {};
const session = {};
const window = {
    location: {hash: '', pathname: '/admin/cms/page/edit/page_id/5/key/abc/'},
    history: {
        replaceState: function () {}
    },
    sessionStorage: {
        getItem: function (key) {
            return key in session ? session[key] : null;
        },
        setItem: function (key, value) {
            session[key] = String(value);
        },
        removeItem: function (key) {
            delete session[key];
        }
    },
    localStorage: {
        getItem: function (key) {
            return key in storage ? storage[key] : null;
        },
        setItem: function (key, value) {
            storage[key] = String(value);
        }
    },
    setTimeout: function (callback) {
        callback();
    }
};
const modules = {
    knockout: ko,
    'mage/translate': function (text) {
        return text;
    },
    'Magento_PageBuilder/js/events': {
        trigger: function (name, args) {
            triggered.push(name);
            (listeners[name] || []).forEach(function (listener) {
                listener(args);
            });
        },
        on: function (name, listener) {
            (listeners[name] = listeners[name] || []).push(listener);
        }
    },
    jquery: fakeJquery(),
    uiRegistry: {
        get: function () {}
    }
};

/**
 * Just what panel-mixin.js uses, on fake elements: {classes: {}, parent: element}. Delegated handlers are kept
 * on the element, as handlers[event] = [{namespace, selector, handler}].
 *
 * @returns {Function}
 */
function fakeJquery() {
    /**
     * @param {Object} element
     * @returns {Object}
     */
    function $(element) {
        const wrapped = {
            0: element,
            on: function (event, selector, handler) {
                const [name, namespace] = event.split('.');

                if (element) {
                    element.handlers = element.handlers || {};
                    (element.handlers[name] = element.handlers[name] || []).push({
                        namespace: namespace,
                        selector: selector,
                        handler: handler
                    });
                }

                return wrapped;
            },
            off: function (event) {
                const namespace = event.split('.')[1];

                Object.keys((element && element.handlers) || {}).forEach(function (name) {
                    element.handlers[name] = element.handlers[name].filter(function (entry) {
                        return entry.namespace !== namespace;
                    });
                });

                return wrapped;
            },
            closest: function (selector) {
                let current = element;

                while (current && !current.classes[selector.slice(1)]) {
                    current = current.parent;
                }

                return $(current);
            },
            addClass: function (name) {
                return wrapped.toggleClass(name, true);
            },
            removeClass: function (name) {
                return wrapped.toggleClass(name, false);
            },
            width: function () {
                return 0;
            },
            toggleClass: function (name, on) {
                if (element) {
                    element.classes[name] = on;
                }

                return wrapped;
            }
        };

        return wrapped;
    }

    $.extend = Object.assign;

    return $;
}

/**
 * Evaluate an AMD module whose dependencies are registered in `modules`.
 *
 * @param {String} name
 * @param {String} file
 */
function load(name, file) {
    vm.runInNewContext(fs.readFileSync(file, 'utf8'), {
        Object: Object,
        JSON: JSON,
        Date: Date,
        window: window,
        define: function (deps, factory) {
            modules[name] = factory.apply(null, deps.map(function (dep) {
                assert.ok(dep in modules, name + ' needs ' + dep);

                return modules[dep];
            }));
        }
    });
}

const js = path.join(root, 'view/adminhtml/web/js');

load('Magento_PageBuilder/js/drag-drop/move-content-type', moveContentTypeFile);
load('MageOS_PageBuilderUi/js/panel/tree', path.join(js, 'panel/tree.js'));
load('MageOS_PageBuilderUi/js/panel-mixin', path.join(js, 'panel-mixin.js'));

const tree = modules['MageOS_PageBuilderUi/js/panel/tree'];
const panelMixin = modules['MageOS_PageBuilderUi/js/panel-mixin'];

let lastId = 0;

/**
 * A content type, or a container when `children` is given, with a toolbar holding `options`.
 *
 * @param {String} name
 * @param {Object} data
 * @param {Array|null} children
 * @param {Array} options
 * @returns {Object}
 */
function contentType(name, data, children, options) {
    const instance = {
        id: 'ct' + (++lastId),
        stageId: 'stage1',
        config: {name: name, label: name.charAt(0).toUpperCase() + name.slice(1), icon: 'icon-' + name},
        dataStore: {
            getState: function () {
                return data || {};
            }
        },
        actions: [],
        options: {}
    };

    (options || ['move', 'duplicate', 'edit', 'remove']).forEach(function (code) {
        instance.options[code] = {
            isDisabled: ko.observable(false),
            action: function () {
                instance.actions.push(code);
            }
        };
    });
    instance.preview = {
        display: ko.observable(true),
        wrapperElement: {classes: {}, offsetWidth: 0},
        getOptions: function () {
            return {
                getOption: function (code) {
                    return instance.options[code];
                }
            };
        }
    };

    if (children) {
        const observable = ko.observableArray(children);

        instance.children = observable;
        instance.getChildren = function () {
            return observable;
        };
        children.forEach(function (child) {
            child.parentContentType = instance;
        });
    }

    return instance;
}

/**
 * @param {Array} nodes
 * @returns {String}
 */
function outline(nodes) {
    return nodes.map(function (node) {
        return node.depth + ':' + node.contentType.config.name;
    }).join(' ');
}

// --- Tree ---

const heading = contentType('heading', {'heading_text': '<b>Welcome</b>&nbsp; home'});
const text = contentType('text', {content: '<p>' + 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.' + '</p>'});
const slideA = contentType('slide', {}, null, ['duplicate', 'edit', 'remove']);
const slideB = contentType('slide', {}, null, ['duplicate', 'edit', 'remove']);
const slider = contentType('slider', {}, [slideA, slideB]);
const column = contentType('column', {}, [heading, text]);
const row1 = contentType('row', {}, [column]);
const row2 = contentType('row', {}, [slider]);
const rootContainer = contentType('root-container', {}, [row1, row2], []);

let nodes = tree.flatten(rootContainer, {});

assert.strictEqual(outline(nodes), '0:row 1:column 2:heading 2:text 0:row 1:slider 2:slide 2:slide', 'depth first');
assert.strictEqual(nodes[2].summary, 'Welcome home', 'summary: tags and spaces stripped');
assert.strictEqual(nodes[3].summary.length, 40, 'summary: shortened');
assert.ok(nodes[3].summary.endsWith('…'));
assert.strictEqual(nodes[0].summary, '', 'no summary');
assert.strictEqual(nodes[2].label, 'Heading');
assert.strictEqual(nodes[2].icon, 'icon-heading');
assert.ok(nodes[0].hasChildren && !nodes[2].hasChildren);

// Moving: within the container, when the toolbar has a move handle.
assert.deepStrictEqual([nodes[2].canMoveUp, nodes[2].canMoveDown], [false, true], 'first child');
assert.deepStrictEqual([nodes[3].canMoveUp, nodes[3].canMoveDown], [true, false], 'last child');
assert.deepStrictEqual([nodes[1].canMoveUp, nodes[1].canMoveDown], [false, false], 'only child');
assert.deepStrictEqual([nodes[6].canMoveUp, nodes[6].canMoveDown], [false, false], 'no move option (slides)');
assert.ok(nodes[6].canDuplicate && nodes[6].canEdit && nodes[6].canRemove);

// Collapsed nodes keep their children out of the list.
nodes = tree.flatten(rootContainer, {[column.id]: true});
assert.strictEqual(outline(nodes), '0:row 1:column 0:row 1:slider 2:slide 2:slide', 'collapsed');
assert.ok(nodes[1].isCollapsed && nodes[1].hasChildren);

// Moving goes through core's moveContentType.
triggered.length = 0;
assert.strictEqual(tree.move(heading, 1), true);
assert.deepStrictEqual(column.getChildren()(), [text, heading], 'moved down');
assert.ok(triggered.includes('contentType:moveAfter'), 'move events');
assert.strictEqual(tree.move(heading, 1), false, 'already last');
assert.strictEqual(tree.move(slideA, 1), false, 'no move option');
assert.strictEqual(tree.move(rootContainer, 1), false, 'root');
assert.strictEqual(tree.move(heading, -1), true);
assert.deepStrictEqual(column.getChildren()(), [heading, text], 'moved back up');
assert.strictEqual(tree.move(row2, -1), true);
assert.deepStrictEqual(rootContainer.getChildren()(), [row2, row1], 'rows moved');
tree.move(row2, 1);

// Duplicate, edit, remove run the toolbar's options, unless disabled.
assert.strictEqual(tree.runOption(heading, 'duplicate'), true);
assert.deepStrictEqual(heading.actions, ['duplicate']);
slideA.options.remove.isDisabled(true);
assert.strictEqual(tree.runOption(slideA, 'remove'), false, 'disabled');
assert.strictEqual(tree.runOption(slideA, 'move'), false, 'missing');
assert.deepStrictEqual(slideA.actions, []);
assert.strictEqual(tree.flatten(rootContainer, {})[6].canRemove, false, 'disabled option not offered');
slideA.options.remove.isDisabled(false);

// --- Panel mixin ---

/**
 * A stand-in for core's Panel: what the mixin relies on.
 *
 * @param {Object} pageBuilder
 */
function Panel(pageBuilder) {
    this.template = 'Magento_PageBuilder/panel';
    this.pageBuilder = pageBuilder;
    this.id = pageBuilder.id;
    this.initListeners();
}

Panel.prototype.initListeners = function () {
    this.coreListeners = true;
};
Panel.prototype.afterRender = function (element) {
    this.element = element;
};
Panel.prototype.getTemplate = function () {
    return this.template;
};
Panel.prototype.populateContentTypes = function () {
    this.menuSections = ['layout', 'elements', 'media'].map(function (code) {
        return {code: code, active: ko.observable(false)};
    });
    // Core opens the first section only.
    this.menuSections[0].active(true);
    this.menuSections = ko.observableArray(this.menuSections);
};

panelMixin(Panel);

const pageBuilder = {id: 'stage1', stage: {id: 'stage1', rootContainer: rootContainer}};
const panel = new Panel(pageBuilder);
const stageWrapper = {classes: {'pagebuilder-stage-wrapper': true}};

assert.ok(panel.coreListeners, 'core initListeners still runs');
assert.strictEqual(panel.getTemplate(), 'MageOS_PageBuilderUi/panel');
assert.strictEqual(panel.tabs.map((tab) => tab.code).join(), 'add,tree');
assert.strictEqual(panel.activeTab(), 'add', 'opens on "Add element"');
assert.strictEqual(panel.isCollapsed(), false);

panel.afterRender({classes: {}, parent: stageWrapper});
assert.strictEqual(stageWrapper.classes['mageos-panel-host'], true, 'stage wrapper marked');
assert.strictEqual(stageWrapper.classes['mageos-panel-collapsed'], false);

// Every section of "Add element" opens.
panel.populateContentTypes();
assert.deepStrictEqual(panel.menuSections().map((section) => section.active()), [true, true, true], 'all sections open');

// The mouse over an element on the stage marks its node: the innermost element's, as mouseover bubbles.
const outerWrapper = {classes: {'pagebuilder-content-type-wrapper': true}, id: 'ct-outer'};
const innerWrapper = {classes: {'pagebuilder-content-type-wrapper': true}, id: 'ct-inner', parent: outerWrapper};
const overInner = {target: {classes: {}, parent: innerWrapper}};

assert.strictEqual(stageWrapper.handlers.mouseover.length, 1);
assert.strictEqual(stageWrapper.handlers.mouseover[0].selector, '.pagebuilder-content-type-wrapper');
stageWrapper.handlers.mouseover[0].handler.call(innerWrapper, overInner);
assert.strictEqual(panel.hoveredNodeId(), 'ct-inner', 'hovered on the stage');
stageWrapper.handlers.mouseover[0].handler.call(outerWrapper, overInner);
assert.strictEqual(panel.hoveredNodeId(), 'ct-inner', 'not replaced by its container, as the event bubbles');
stageWrapper.handlers.mouseleave[0].handler();
assert.strictEqual(panel.hoveredNodeId(), null, 'mouse left the stage');

// Rendered again, the panel replaces its handlers on the stage wrapper; another panel's stay.
stageWrapper.handlers.mouseover.push({namespace: 'mageosPanelother', selector: '', handler: function () {}});
panel.afterRender({classes: {}, parent: stageWrapper});
assert.deepStrictEqual(stageWrapper.handlers.mouseover.map((entry) => entry.namespace), ['mageosPanelother', 'mageosPanelstage1'], 'mouseover once');
assert.strictEqual(stageWrapper.handlers.mouseleave.length, 1, 'mouseleave once');
stageWrapper.handlers.mouseover.shift();

// The tabs: ids that tie each tab to its pane, and the arrows, Home and End.
const focused = [];
const keyPanel = new Panel(pageBuilder);

keyPanel.focusTab = (code) => focused.push(code);
keyPanel.activeTab('add');
assert.strictEqual(keyPanel.getTabId('tree'), 'mageos-panel-tab-tree-stage1');
assert.strictEqual(keyPanel.getPaneId('tree'), 'mageos-panel-pane-tree-stage1');
assert.strictEqual(keyPanel.onTabKeydown(keyPanel.tabs[0], {key: 'ArrowRight'}), false, 'handled');
assert.strictEqual(keyPanel.activeTab(), 'tree');
assert.strictEqual(keyPanel.onTabKeydown(keyPanel.tabs[1], {key: 'ArrowRight'}), false);
assert.strictEqual(keyPanel.activeTab(), 'add', 'round to the first');
keyPanel.onTabKeydown(keyPanel.tabs[0], {key: 'ArrowLeft'});
assert.strictEqual(keyPanel.activeTab(), 'tree', 'round to the last');
keyPanel.onTabKeydown(keyPanel.tabs[1], {key: 'Home'});
assert.strictEqual(keyPanel.activeTab(), 'add', 'Home');
keyPanel.onTabKeydown(keyPanel.tabs[0], {key: 'End'});
assert.strictEqual(keyPanel.activeTab(), 'tree', 'End');
assert.deepStrictEqual(focused, ['tree', 'add', 'tree', 'add', 'tree'], 'the focus follows');
assert.strictEqual(keyPanel.onTabKeydown(keyPanel.tabs[1], {key: 'Tab'}), true, 'other keys left to the browser');
assert.strictEqual(keyPanel.onTabKeydown(keyPanel.tabs[1], {key: 'Enter'}), true);
keyPanel.isCollapsed(true);
keyPanel.onTabKeydown(keyPanel.tabs[1], {key: 'ArrowLeft'});
assert.strictEqual(keyPanel.isCollapsed(), true, 'the arrows leave a collapsed panel folded');
keyPanel.isCollapsed(false);
keyPanel.activeTab('add');

// The tree is only worked out while its tab is open.
const subscription = panel.treeNodes.subscribe(function () {});

assert.strictEqual(panel.treeNodes().length, 0, 'not on the add tab');
panel.selectTab('tree');
assert.strictEqual(outline(panel.treeNodes()), '0:row 1:column 2:heading 2:text 0:row 1:slider 2:slide 2:slide');
assert.strictEqual(JSON.parse(storage['mageos-pagebuilder-panel']).tab, 'tree', 'tab remembered');

// It follows the stage.
panel.moveNode(panel.treeNodes()[2], 1);
assert.strictEqual(outline(panel.treeNodes()), '0:row 1:column 2:text 2:heading 0:row 1:slider 2:slide 2:slide', 'after a move');
assert.strictEqual(heading.preview.wrapperElement.classes['mageos-tree-highlight-flash'], false, 'flashed');
column.getChildren().remove(text);
assert.strictEqual(outline(panel.treeNodes()), '0:row 1:column 2:heading 0:row 1:slider 2:slide 2:slide', 'after a remove');
column.getChildren().push(text);

panel.toggleNode(panel.treeNodes()[5]);
assert.strictEqual(outline(panel.treeNodes()), '0:row 1:column 2:heading 2:text 0:row 1:slider', 'node collapsed');
panel.toggleNode(panel.treeNodes()[5]);
assert.strictEqual(panel.treeNodes().length, 8, 'node expanded');

// Collapse all folds every container, down to the rows; expand all opens them again.
panel.collapseAll();
assert.strictEqual(outline(panel.treeNodes()), '0:row 0:row', 'all collapsed');
panel.expandAll();
assert.strictEqual(panel.treeNodes().length, 8, 'all expanded');

// Selecting a node marks it and shows its element on the stage.
assert.strictEqual(panel.selectedNodeId(), null, 'nothing selected');
heading.preview.wrapperElement.classes = {};
panel.selectNode(panel.treeNodes()[2]);
assert.strictEqual(panel.selectedNodeId(), heading.id, 'selected');
assert.strictEqual(heading.preview.wrapperElement.classes['mageos-tree-highlight-flash'], false, 'flashed on select');

// Element data isn't observable: summaries refresh on the stage's update event.
const headingData = {'heading_text': 'Old'};

heading.dataStore.getState = () => headingData;
modules['Magento_PageBuilder/js/events'].trigger('stage:updateAfter', {stageId: 'stage1'});
assert.strictEqual(panel.treeNodes()[2].summary, 'Old');
headingData['heading_text'] = 'New';
modules['Magento_PageBuilder/js/events'].trigger('stage:updateAfter', {stageId: 'other'});
assert.strictEqual(panel.treeNodes()[2].summary, 'Old', 'another stage');
modules['Magento_PageBuilder/js/events'].trigger('stage:updateAfter', {stageId: 'stage1'});
assert.strictEqual(panel.treeNodes()[2].summary, 'New', 'this stage');

// Hiding an element on the stage shows in the tree.
heading.preview.display(false);
assert.strictEqual(panel.treeNodes()[2].isHidden, true);
heading.preview.display(true);

panel.runNodeOption(panel.treeNodes()[3], 'edit');
assert.deepStrictEqual(text.actions, ['edit']);

panel.highlightNode(panel.treeNodes()[3], true);
assert.strictEqual(text.preview.wrapperElement.classes['mageos-tree-highlight'], true, 'hover highlights');
panel.highlightNode(panel.treeNodes()[3], false);
assert.strictEqual(text.preview.wrapperElement.classes['mageos-tree-highlight'], false);

// One outline at a time, and none left behind by an action: the redrawn tree sends no mouseleave.
panel.highlightNode(panel.treeNodes()[2], true);
panel.highlightNode(panel.treeNodes()[3], true);
assert.strictEqual(heading.preview.wrapperElement.classes['mageos-tree-highlight'], false, 'previous cleared');
panel.moveNode(panel.treeNodes()[3], -1);
assert.strictEqual(text.preview.wrapperElement.classes['mageos-tree-highlight'], false, 'cleared by a move');
panel.moveNode(panel.treeNodes()[2], 1);
assert.strictEqual(outline(panel.treeNodes()), '0:row 1:column 2:heading 2:text 0:row 1:slider 2:slide 2:slide');

// Collapsing: remembered, and passed on to the stage wrapper.
panel.toggleCollapsed();
assert.strictEqual(panel.isCollapsed(), true);
assert.strictEqual(stageWrapper.classes['mageos-panel-collapsed'], true);
assert.strictEqual(panel.treeNodes().length, 0, 'not worked out while collapsed');
assert.deepStrictEqual(JSON.parse(storage['mageos-pagebuilder-panel']), {tab: 'tree', collapsed: true});

const restored = new Panel(pageBuilder);

assert.strictEqual(restored.isCollapsed(), true, 'collapsed state restored');
assert.strictEqual(restored.activeTab(), 'tree', 'tab restored');

// Picking a tab opens the panel.
restored.selectTab('add');
assert.strictEqual(restored.isCollapsed(), false);

storage['mageos-pagebuilder-panel'] = '{not json';
assert.strictEqual(new Panel(pageBuilder).activeTab(), 'add', 'unreadable state ignored');

subscription.dispose();

// --- "Save page" reopens Page Builder in full screen: on the page it was saved from only ---

/**
 * A fresh copy of the module, as on a new page load, on the given page.
 *
 * @param {String} pathname
 * @param {String} [hash]
 * @returns {Function}
 */
function loadEditWithPageBuilder(pathname, hash) {
    window.location.pathname = pathname;
    window.location.hash = hash || '';
    load('MageOS_PageBuilderUi/js/edit-with-page-builder', path.join(js, 'edit-with-page-builder.js'));

    return modules['MageOS_PageBuilderUi/js/edit-with-page-builder'];
}

const cmsPage = '/admin/cms/page/edit/page_id/5/key/abc/';
let editWithPageBuilder = loadEditWithPageBuilder(cmsPage);

assert.strictEqual(editWithPageBuilder.isRequested(), false, 'nothing asked');
editWithPageBuilder.reopenAfterReload();
assert.strictEqual(JSON.parse(session['mageos-pagebuilder-reopen']).path, cmsPage, 'the flag holds the page');

editWithPageBuilder = loadEditWithPageBuilder(cmsPage);
assert.strictEqual(editWithPageBuilder.isRequested(), true, 'reloaded after the save');
editWithPageBuilder.consume();
assert.strictEqual(editWithPageBuilder.isRequested(), false, 'consumed');
assert.ok(!('mageos-pagebuilder-reopen' in session), 'flag dropped');

// A save that failed its validation doesn't reload: the next page opened is another one.
loadEditWithPageBuilder(cmsPage).reopenAfterReload();
['/admin/cms/page/edit/page_id/6/key/abc/', '/admin/cms/block/edit/block_id/5/key/def/',
    '/admin/catalog/product/edit/id/5/key/ghi/', '/admin/cms/page/new/key/jkl/'].forEach(function (other) {
    assert.strictEqual(loadEditWithPageBuilder(other).isRequested(), false, 'not on ' + other);
});
assert.strictEqual(loadEditWithPageBuilder(cmsPage).isRequested(), true, 'still on the page saved from');

// A minute old: a save that didn't reload.
session['mageos-pagebuilder-reopen'] = JSON.stringify({path: cmsPage, time: Date.now() - 61000});
assert.strictEqual(loadEditWithPageBuilder(cmsPage).isRequested(), false, 'expired');
// The flag before it held a page: just a time.
session['mageos-pagebuilder-reopen'] = String(Date.now());
assert.strictEqual(loadEditWithPageBuilder(cmsPage).isRequested(), false, 'flag without a page');
session['mageos-pagebuilder-reopen'] = '{not json';
assert.strictEqual(loadEditWithPageBuilder(cmsPage).isRequested(), false, 'unreadable flag');
delete session['mageos-pagebuilder-reopen'];

// The grids' action asks on any page.
assert.strictEqual(loadEditWithPageBuilder('/admin/cms/block/edit/block_id/5/', '#edit-with-page-builder').isRequested(), true);

// Which page reloads after a save: the same edit page, with a product's extra parameters, or a new entity's.
[
    [cmsPage, '/admin/cms/page/edit/page_id/5/key/abc/', true],
    [cmsPage, '/admin/cms/page/edit/page_id/5/', true],
    [cmsPage, '/admin/cms/page/edit/page_id/50/key/abc/', false],
    ['/admin/catalog/product/edit/id/12/key/a/', '/admin/catalog/product/edit/id/12/set/4/key/a/', true],
    ['/admin/catalog/product/edit/id/12/set/4/key/a/', '/admin/catalog/product/edit/set/4/id/12/key/a/', true],
    ['/admin/catalog/product/edit/id/12/key/a/', '/admin/catalog/product/edit/id/13/set/4/key/a/', false],
    ['/admin/catalog/product/new/set/4/type/simple/key/a/', '/admin/catalog/product/edit/id/13/set/4/key/b/', true],
    ['/admin/cms/page/new/key/a/', '/admin/cms/page/edit/page_id/9/key/b/', true],
    ['/admin/cms/page/new/key/a/', '/admin/cms/block/edit/block_id/9/key/b/', false],
    ['/admin/cms/page/new/key/a/', '/admin/cms/page/new/key/a/', false],
    ['/admin/cms/page/edit/page_id/9/key/a/', '/admin/cms/page/index/key/b/', false],
    ['/admin/catalog/category/save/key/a/', '/admin/catalog/category/save/key/b/', true],
    ['/admin/catalog/category/save/key/a/', '/admin/catalog/category/index/key/a/', false]
].forEach(function (pair) {
    assert.strictEqual(editWithPageBuilder.isSamePage(pair[0], pair[1]), pair[2], pair[0] + ' then ' + pair[1]);
});

console.log('OK');
