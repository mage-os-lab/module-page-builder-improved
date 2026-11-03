/**
 * Renders MageOS_PageBuilderUi's stage wrapper and panel (the real mixins, tree and templates) next to a
 * stand-in stage, and its element edit form's tabs on a stand-in form, with the admin's compiled styles, for
 * screenshots outside a live admin.
 *
 * Query: ?tab=add|tree&collapsed=1&fullscreen=1&empty=1&form=1&formerror=1&reopen=1|other&toolbar=<content type id>
 * (reopen: as after "Save page" reloaded the page, or with a flag another page left; formerror: a Content field
 * turns invalid after a second and a half). Also #edit-with-page-builder, as from the grids.
 */
(function () {
    'use strict';

    var params = new URLSearchParams(window.location.search),
        modules = {},
        listeners = {};

    window.localStorage.setItem('mageos-pagebuilder-panel', JSON.stringify({
        tab: params.get('tab') || 'add',
        collapsed: params.get('collapsed') === '1'
    }));
    if (params.get('reopen')) {
        window.sessionStorage.setItem('mageos-pagebuilder-reopen', JSON.stringify({
            path: params.get('reopen') === 'other' ? '/admin/cms/page/edit/page_id/2/' : window.location.pathname,
            time: Date.now()
        }));
    }
    // Magento puts $t in every binding context.
    window.$t = function (text) {
        return text;
    };
    // What the harness's buttons did, for the checks in shot.js.
    window.harnessLog = [];

    /**
     * The templates menu's handlers on the document, for the checks in shot.js.
     *
     * @returns {Number}
     */
    window.harnessMenuHandlers = function () {
        var events = window.jQuery._data(document, 'events') || {};

        return ['click', 'keydown'].reduce(function (count, name) {
            return count + (events[name] || []).filter(function (handler) {
                return handler.namespace.indexOf('mageosTemplateMenu') === 0;
            }).length;
        }, 0);
    };

    // --- AMD stand-ins ---

    modules.jquery = window.jQuery;
    modules.knockout = window.ko;
    modules.underscore = {
        defer: function (callback) {
            window.setTimeout(callback, 0);
        }
    };
    modules.uiRegistry = {
        get: function () {}
    };
    modules['mage/translate'] = function (text) {
        return text;
    };
    modules['Magento_PageBuilder/js/events'] = {
        on: function (name, listener) {
            (listeners[name] = listeners[name] || []).push(listener);
        },
        trigger: function (name, args) {
            (listeners[name] || []).forEach(function (listener) {
                listener(args);
            });
        }
    };
    modules['Magento_PageBuilder/js/drag-drop/move-content-type'] = {
        moveContentType: function (contentType, index) {
            var siblings = contentType.parentContentType.children;

            siblings.remove(contentType);
            siblings.splice(index, 0, contentType);
            renderStage();
        }
    };

    /**
     * @param {String} name
     * @param {String} url
     */
    function load(name, url) {
        var request = new XMLHttpRequest();

        request.open('GET', url, false);
        request.send();
        // eslint-disable-next-line no-new-func
        new Function('define', request.responseText)(function (deps, factory) {
            modules[name] = factory.apply(null, deps.map(function (dep) {
                if (!(dep in modules)) {
                    throw new Error(name + ' needs ' + dep);
                }

                return modules[dep];
            }));
        });
    }

    /**
     * @param {String} url
     * @returns {String}
     */
    function fetchText(url) {
        var request = new XMLHttpRequest();

        request.open('GET', url, false);
        request.send();

        return request.responseText;
    }

    // --- Magento's attribute bindings, turned into data-bind for plain Knockout ---

    var ATTRIBUTE_BINDINGS = {
        visible: 'visible', css: 'css', attr: 'attr', each: 'foreach', click: 'click', event: 'event',
        text: 'text', html: 'html', translate: 'text', afterRender: 'afterRender', 'ko-value': 'value',
        'ko-style': 'style', 'if': 'if', ifnot: 'ifnot', disable: 'disable', enable: 'enable'
    };

    /**
     * @param {String} html
     * @returns {String}
     */
    function convertTemplate(html) {
        var container = document.createElement('div');

        // Magento's <if args="..."> elements, not nested: Knockout's virtual elements.
        container.innerHTML = html.replace(/<if args="([^"]*)">([\s\S]*?)<\/if>/g, '<!-- ko if: $1 -->$2<!-- /ko -->');
        container.querySelectorAll('*').forEach(function (element) {
            var bindings = [];

            Object.keys(ATTRIBUTE_BINDINGS).forEach(function (attribute) {
                var value = element.getAttribute(attribute);

                // Real attributes that share a name: <button disabled>, <label for>, <a href>.
                if (value === null || (attribute === 'disable' && value === '')) {
                    return;
                }
                if (/^(css|attr|event|ko-style)$/.test(attribute) && /^\s*['"\w-]+\s*:/.test(value)) {
                    value = '{' + value + '}';
                }
                bindings.push(ATTRIBUTE_BINDINGS[attribute] + ': ' + value);
                element.removeAttribute(attribute);
            });
            if (bindings.length) {
                element.setAttribute('data-bind', bindings.concat(
                    element.getAttribute('data-bind') ? [element.getAttribute('data-bind')] : []
                ).join(', '));
            }
        });

        return container.innerHTML;
    }

    ko.bindingHandlers.draggable = {init: function () {}};
    ko.bindingHandlers.afterRender = {
        init: function (element, valueAccessor, allBindings, viewModel) {
            var callback = valueAccessor();

            if (typeof callback === 'function') {
                callback.call(viewModel, element, viewModel);
            }
        }
    };

    // --- Content types on a stand-in stage ---

    var lastId = 0;

    /**
     * @param {String} name
     * @param {String} label
     * @param {Object} data
     * @param {Array|null} children
     * @param {Array} [options]
     * @returns {Object}
     */
    function contentType(name, label, data, children, options) {
        var instance = {
            id: name + '-' + (++lastId),
            name: name,
            config: {name: name, label: label, icon: 'icon-pagebuilder-' + ICONS[name]},
            data: data || {},
            dataStore: {
                getState: function () {
                    return instance.data;
                }
            },
            options: {}
        };

        (options || ['move', 'duplicate', 'edit', 'remove']).forEach(function (code) {
            instance.options[code] = {
                isDisabled: ko.observable(false),
                action: function () {
                    window.console.log('option ' + code + ' on ' + instance.id);
                }
            };
        });
        instance.preview = {
            display: ko.observable(true),
            wrapperElement: null,
            getOptions: function () {
                return {
                    getOption: function (code) {
                        return instance.options[code];
                    }
                };
            }
        };
        if (children) {
            instance.children = ko.observableArray(children);
            instance.getChildren = function () {
                return instance.children;
            };
            children.forEach(function (child) {
                child.parentContentType = instance;
            });
        }

        return instance;
    }

    var ICONS = {
        row: 'row', 'column-group': 'column', column: 'column', heading: 'heading', text: 'text',
        buttons: 'button', 'button-item': 'button', image: 'image', slider: 'slider', slide: 'slider',
        banner: 'banner', divider: 'divider', products: 'products', 'root-container': 'row'
    };

    var hero = contentType('heading', 'Heading', {'heading_text': 'Handmade in Belgium'}),
        intro = contentType('text', 'Text', {content: '<p>Solid oak tables, chairs and cabinets, finished by hand in our workshop.</p>'}),
        button = contentType('button-item', 'Button', {'button_text': 'Shop the collection'}, null, ['duplicate', 'edit', 'remove']),
        buttons = contentType('buttons', 'Buttons', {}, [button]),
        imageLeft = contentType('image', 'Image', {}),
        textRight = contentType('text', 'Text', {content: '<p>Every piece is made to order, in the size and finish you pick.</p>'}),
        columnLeft = contentType('column', 'Column', {}, [imageLeft], ['move', 'duplicate', 'edit', 'remove']),
        columnRight = contentType('column', 'Column', {}, [textRight], ['move', 'duplicate', 'edit', 'remove']),
        columns = contentType('column-group', 'Columns', {}, [columnLeft, columnRight]),
        slideA = contentType('slide', 'Slide', {title: 'Autumn collection'}, null, ['duplicate', 'edit', 'remove']),
        slideB = contentType('slide', 'Slide', {title: 'Outlet'}, null, ['duplicate', 'edit', 'remove']),
        slider = contentType('slider', 'Slider', {}, [slideA, slideB]),
        rowA = contentType('row', 'Row', {}, [hero, intro, buttons]),
        rowB = contentType('row', 'Row', {}, [columns]),
        rowC = contentType('row', 'Row', {}, [slider]),
        rootContainer = contentType('root-container', 'Root', {}, params.get('empty') === '1' ? [] : [rowA, rowB, rowC], []);

    /**
     * @param {Object} instance
     * @returns {String}
     */
    function optionsMenu(instance) {
        var links = [];

        if (instance.options.move) {
            links.push('<a class="move-structural" title="Move"><i class="icon-admin-pagebuilder-handle"></i></a>');
        }
        links.push('<div class="option-title">' + instance.config.label + '</div>');
        links.push('<a class="edit-content-type" title="Edit"><i class="icon-admin-pagebuilder-systems"></i></a>');
        links.push('<a class="duplicate-structural" title="Duplicate"><i class="icon-pagebuilder-copy"></i></a>');
        links.push('<a class="remove-structural" title="Remove"><i class="icon-admin-pagebuilder-remove"></i></a>');

        return '<div class="pagebuilder-options pagebuilder-options-middle' +
            (params.get('toolbar') === instance.id ? ' pagebuilder-options-visible' : '') + '">' +
            '<div class="pagebuilder-options-wrapper"><ul class="pagebuilder-options-links">' +
            links.map(function (link) {
                return '<li class="pagebuilder-options-link">' + link + '</li>';
            }).join('') + '</ul></div></div>';
    }

    /**
     * @param {Object} instance
     * @returns {Element}
     */
    function renderContentType(instance) {
        var wrapper = document.createElement('div'),
            data = instance.data,
            active = params.get('toolbar') === instance.id ? ' pagebuilder-content-type-active' : '',
            html;

        wrapper.className = 'pagebuilder-content-type-wrapper';
        wrapper.id = instance.id;

        switch (instance.name) {
            case 'row':
                html = '<div><div class="pagebuilder-affordance-row pagebuilder-content-type pagebuilder-content-type-affordance' + active + '">' +
                    '<div class="pagebuilder-display-label">ROW</div>' +
                    '<div class="pagebuilder-content-type no-system-border type-container pagebuilder-row children-min-height" style="padding: 10px">' +
                    optionsMenu(instance) + '<div class="element-children content-type-container"></div></div></div></div>';
                break;
            case 'column-group':
                html = '<div class="pagebuilder-content-type type-container pagebuilder-column-group' + active + '">' + optionsMenu(instance) +
                    '<div class="element-children content-type-container" style="display: flex"></div></div>';
                break;
            case 'column':
                html = '<div class="pagebuilder-content-type type-container pagebuilder-column' + active + '" style="padding: 10px">' + optionsMenu(instance) +
                    '<div class="element-children content-type-container"></div></div>';
                break;
            case 'buttons':
                html = '<div class="pagebuilder-content-type type-container pagebuilder-buttons' + active + '" style="padding: 10px 0">' + optionsMenu(instance) +
                    '<div class="element-children content-type-container"></div></div>';
                break;
            case 'button-item':
                html = '<div class="pagebuilder-content-type pagebuilder-button-item' + active + '" style="display: inline-block">' + optionsMenu(instance) +
                    '<span class="pagebuilder-button-primary" style="background:#1979c3;color:#fff;display:inline-block;font-size:14px;padding:10px 18px">' + data.button_text + '</span></div>';
                break;
            case 'heading':
                html = '<div class="pagebuilder-content-type pagebuilder-heading' + active + '">' + optionsMenu(instance) +
                    '<h2 style="font-size:28px;font-weight:600;margin:0;padding:6px 0">' + data.heading_text + '</h2></div>';
                break;
            case 'text':
                html = '<div class="pagebuilder-content-type pagebuilder-text' + active + '">' + optionsMenu(instance) +
                    '<div style="font-size:14px;line-height:1.6;padding:4px 0">' + data.content + '</div></div>';
                break;
            case 'image':
                html = '<div class="pagebuilder-content-type pagebuilder-image' + active + '">' + optionsMenu(instance) +
                    '<div style="background:#e3ddd3;height:160px"></div></div>';
                break;
            case 'slider':
                html = '<div class="pagebuilder-content-type type-container pagebuilder-slider' + active + '">' + optionsMenu(instance) +
                    '<div class="element-children content-type-container"></div></div>';
                break;
            case 'slide':
                html = '<div class="pagebuilder-content-type pagebuilder-slide' + active + '" style="display:' + (instance === slideA ? 'block' : 'none') + '">' + optionsMenu(instance) +
                    '<div style="align-items:center;background:#3d3a35;color:#fff;display:flex;font-size:24px;height:180px;justify-content:center">' + data.title + '</div></div>';
                break;
        }
        wrapper.innerHTML = html;
        instance.preview.wrapperElement = wrapper;

        if (instance.children) {
            instance.children().forEach(function (child) {
                wrapper.querySelector('.element-children').appendChild(renderContentType(child));
            });
        }

        return wrapper;
    }

    /**
     * Rebuild the stage from the content types, as Page Builder re-renders after a change.
     */
    function renderStage() {
        var root = document.getElementById('stage-root');

        root.innerHTML = '';
        rootContainer.children().forEach(function (child) {
            root.appendChild(renderContentType(child));
        });
    }

    // --- Core's Panel, as far as the template and the mixin use it ---

    var SECTIONS = [
        ['layout', 'Layout', [['row', 'Row', 'row'], ['column-group', 'Columns', 'column'], ['tabs', 'Tabs', 'tabs']]],
        ['elements', 'Elements', [['text', 'Text', 'text'], ['heading', 'Heading', 'heading'], ['buttons', 'Buttons', 'button'],
            ['divider', 'Divider', 'divider'], ['html', 'HTML Code', 'code']]],
        ['media', 'Media', [['image', 'Image', 'image'], ['video', 'Video', 'video'], ['banner', 'Banner', 'banner'],
            ['slider', 'Slider', 'slider'], ['map', 'Map', 'map']]],
        ['add_content', 'Add Content', [['block', 'Block', 'block'], ['products', 'Products', 'products'],
            ['mageos_hero', 'Hero', 'banner'], ['mageos_cards', 'Cards', 'column'], ['mageos_usp_list', 'USP list', 'text']]]
    ];

    /**
     * @param {Array} item
     * @returns {Object}
     */
    function menuContentType(item) {
        return {
            identifier: ko.observable(item[0]),
            label: ko.observable(item[1]),
            icon: ko.observable('icon-pagebuilder-' + item[2]),
            getDraggableOptions: function () {
                return {};
            }
        };
    }

    /**
     * @param {Object} pageBuilder
     */
    function Panel(pageBuilder) {
        this.menuSections = ko.observableArray([]);
        this.searchResults = ko.observableArray([]);
        this.isVisible = ko.observable(false);
        this.isStickyBottom = ko.observable(false);
        this.isStickyTop = ko.observable(false);
        this.searching = ko.observable(false);
        this.searchValue = ko.observable('');
        this.searchPlaceholder = 'Find items';
        this.searchNoResult = 'Nothing found';
        this.searchTitle = 'Clear Search';
        this.template = 'Magento_PageBuilder/panel';
        this.pageBuilder = pageBuilder;
        this.id = pageBuilder.id;
        this.initListeners();
    }

    Panel.prototype.afterRender = function (element) {
        this.element = element;
    };
    Panel.prototype.initListeners = function () {
        var self = this;

        modules['Magento_PageBuilder/js/events'].on('stage:' + this.id + ':readyAfter', function () {
            self.populateContentTypes();
            self.isVisible(true);
        });
    };
    Panel.prototype.getTemplate = function () {
        return this.template;
    };
    Panel.prototype.search = function (self, event) {
        var value = event.currentTarget.value.toLowerCase(),
            results = [];

        this.searchValue(value);
        this.searching(value !== '');
        SECTIONS.forEach(function (section) {
            section[2].forEach(function (item) {
                if (new RegExp('\\b' + value, 'i').test(item[1])) {
                    results.push(menuContentType(item));
                }
            });
        });
        this.searchResults(results);
    };
    Panel.prototype.clearSearch = function () {
        this.searchValue('');
        this.searching(false);
    };
    Panel.prototype.getDraggableOptions = function () {
        return {};
    };
    Panel.prototype.populateContentTypes = function () {
        this.menuSections(SECTIONS.map(function (section) {
            var menu = {
                id: ko.observable(section[0]),
                label: ko.observable(section[1]),
                active: ko.observable(false),
                hidden: ko.observable(false),
                contentTypes: ko.observableArray(section[2].map(menuContentType))
            };

            menu.toggle = function () {
                menu.active(!menu.active());
            };

            return menu;
        }));
        this.isVisible(true);
        this.menuSections()[0].active(true);
    };

    // --- Core's PageBuilder, as far as its template and the mixin use it ---

    /**
     * @constructor
     */
    function PageBuilder() {
        this.template = 'Magento_PageBuilder/page-builder';
        this.id = 'stage1';
        this.isFullScreen = ko.observable(params.get('fullscreen') === '1');
        this.isSnapshot = ko.observable(false);
        this.isSnapshotTransition = ko.observable(false);
        this.isStageReady = ko.observable(true);
        this.wrapperStyles = ko.observable({});
        this.viewportClasses = {};
        this.isAllowedTemplateApply = true;
        this.isAllowedTemplateSave = true;
        this.stage = {id: 'stage1', rootContainer: rootContainer};
        this.initListeners();
    }

    PageBuilder.prototype.initListeners = function () {};
    PageBuilder.prototype.getTemplate = function () {
        return this.template;
    };
    PageBuilder.prototype.toggleFullScreen = function () {
        this.isFullScreen(!this.isFullScreen());
    };
    PageBuilder.prototype.toggleTemplateManger = function () {
        window.harnessLog.push('apply template');
    };
    PageBuilder.prototype.saveAsTemplate = function () {
        window.harnessLog.push('save as template');
    };

    var VIEWPORTS = '<span class="tooltip"><button type="button" class="page-builder-viewport desktop-switcher" disabled>' +
        '<img src="static/Magento_PageBuilder/css/images/switcher/switcher-desktop.svg" alt=""></button></span>' +
        '<span class="tooltip"><button type="button" class="page-builder-viewport mobile-switcher">' +
        '<img src="static/Magento_PageBuilder/css/images/switcher/switcher-mobile.svg" alt=""></button></span>';

    /**
     * The module's page-builder.html, with the panel's template in it, and the stand-in stage for core's.
     *
     * @param {String} panelTemplate
     * @returns {String}
     */
    function stageWrapperTemplate(panelTemplate) {
        return fetchText('ui/template/page-builder.html')
            .replace(/<render args="viewportTemplate"><\/render>/, VIEWPORTS)
            .replace(/<with args="panel">\s*<render><\/render>\s*<\/with>/,
                '<div id="panel-slot" data-bind="with: panel">' + panelTemplate + '</div>')
            .replace(/<if args="isStageReady">[\s\S]*<\/if>/,
                '<div class="pagebuilder-stage" id="stage" data-bind="css: {\'panel-collapsed\': panel.isCollapsed}">' +
                '<div class="pagebuilder-canvas active"><div class="pagebuilder-content-type type-container pagebuilder-root-container">' +
                '<div class="element-children content-type-container root-container-container" id="stage-root"></div></div></div></div>');
    }

    // --- An element's edit form, as Magento_Ui renders it in Page Builder's modal ---

    /**
     * @param {String} label
     * @param {String} control
     * @returns {String}
     */
    function field(label, control) {
        return '<div class="admin__field"><div class="admin__field-label"><label><span>' + label + '</span></label></div>' +
            '<div class="admin__field-control">' + control + '</div></div>';
    }

    /**
     * @param {String} index
     * @param {String} label
     * @param {String} fields
     * @param {Boolean} opened
     * @returns {String}
     */
    function fieldset(index, label, fields, opened) {
        return '<div class="fieldset-wrapper admin__collapsible-block-wrapper" data-level="0" data-index="' + index + '">' +
            (label ? '<div class="fieldset-wrapper-title" tabindex="0" data-state-collapsible="' + (opened ? 'open' : 'closed') + '">' +
                '<strong class="admin__collapsible-title"><span>' + label + '</span>' +
                '<span class="admin__page-nav-item-messages"><span class="admin__page-nav-item-message _changed"></span>' +
                '<span class="admin__page-nav-item-message _error"></span></span></strong></div>' : '') +
            '<div class="admin__fieldset-wrapper-content admin__collapsible-content ' + (opened ? '_show' : '_hide') + '">' +
            (opened ? '<fieldset class="admin__fieldset">' + fields + '</fieldset>' : '') + '</div></div>';
    }

    var ADVANCED_FIELDS = field('Alignment', '<select class="admin__control-select"><option>Default</option></select>') +
        field('Border', '<select class="admin__control-select"><option>Default</option></select>') +
        field('Border Radius', '<input class="admin__control-text" type="text" value="0">') +
        field('CSS Classes', '<input class="admin__control-text" type="text" value="">') +
        // Core's margins-and-padding.html.
        field('Margins and Padding', '<div class="admin__field-design-options"><input type="text">' +
            '<div class="layout-onion"><div class="layout-onion-layer layout-margin"><span class="label">Margin</span>' +
            ['top', 'right', 'bottom', 'left'].map(function (side) {
                return '<input type="text" class="admin__control-text layout-onion-' + side + '" placeholder="-">';
            }).join('') +
            '<div class="layout-onion-layer layout-border"><span class="label">Padding</span>' +
            ['top', 'right', 'bottom', 'left'].map(function (side) {
                return '<input type="text" class="admin__control-text layout-onion-' + side + '" placeholder="-">';
            }).join('') + '</div></div></div></div>');

    /**
     * Core's edit modal (modal-slide.html) with the form in it, set up as js/modal/modal-mixin.js does.
     */
    function renderEditForm() {
        var modal = document.createElement('div'),
            component;

        modal.innerHTML = '<aside role="dialog" class="modal-slide mageos-pb-edit-modal _show" data-role="modal" data-type="slide">' +
            '<div class="modal-inner-wrap"><header class="modal-header"><h1 class="modal-title">Edit Heading</h1>' +
            '<button class="action-close" type="button"><span>Close</span></button>' +
            '<div class="page-main-actions"><div class="page-actions"><div class="page-actions-buttons">' +
            '<button class="save primary" type="button"><span>Save</span></button></div></div></div></header>' +
            '<div class="modal-content" data-role="content"><div class="modal-component"><div class="entry-edit form-inline">' +
            fieldset('appearance_fieldset', 'Appearance', field('Appearance', '<select class="admin__control-select"><option>Default</option></select>'), true) +
            fieldset('general', 'Content',
                field('Heading Type', '<select class="admin__control-select"><option>H2</option></select>') +
                field('Heading Text', '<input class="admin__control-text" type="text" value="Handmade in Belgium">') +
                field('Link', '<input class="admin__control-text" type="text" placeholder="https://">'), true) +
            fieldset('advanced', 'Advanced', '', false) +
            '</div></div></div></div></aside>';
        document.body.appendChild(modal.firstChild);
        // Only one appearance: the form hides that fieldset.
        document.querySelector('[data-index="appearance_fieldset"]').style.display = 'none';

        // Magento_Ui opens a collapsed fieldset, and renders its fields, when its title is clicked.
        window.jQuery(document).on('click', '.fieldset-wrapper-title[data-state-collapsible="closed"]', function () {
            var wrapper = this.parentNode;

            this.setAttribute('data-state-collapsible', 'open');
            wrapper.querySelector('.admin__fieldset-wrapper-content').className =
                'admin__fieldset-wrapper-content admin__collapsible-content _show';
            wrapper.querySelector('.admin__fieldset-wrapper-content').innerHTML =
                '<fieldset class="admin__fieldset">' + ADVANCED_FIELDS + '</fieldset>';
        });

        component = document.querySelector('.mageos-pb-edit-modal .modal-component');
        window.harness.formTabs = modules['MageOS_PageBuilderUi/js/form/tabs']
            .attach(component, component.parentNode);

        if (params.get('formerror') === '1') {
            window.setTimeout(function () {
                document.querySelector('[data-index="general"] .admin__field:nth-child(2)').classList.add('_error');
            }, 1500);
        }
    }

    // --- Boot ---

    load('MageOS_PageBuilderUi/js/panel/tree', 'ui/js/panel/tree.js');
    load('MageOS_PageBuilderUi/js/panel-mixin', 'ui/js/panel-mixin.js');
    load('MageOS_PageBuilderUi/js/edit-with-page-builder', 'ui/js/edit-with-page-builder.js');
    load('MageOS_PageBuilderUi/js/page-builder-mixin', 'ui/js/page-builder-mixin.js');
    load('MageOS_PageBuilderUi/js/form/tabs', 'ui/js/form/tabs.js');
    modules['MageOS_PageBuilderUi/js/panel-mixin'](Panel);
    modules['MageOS_PageBuilderUi/js/page-builder-mixin'](PageBuilder);

    var pageBuilder = new PageBuilder(),
        panel = new Panel(pageBuilder),
        templateName = function (component) {
            return component.getTemplate().replace(/^[^/]+\//, '');
        };

    pageBuilder.panel = panel;
    window.harness = {panel: panel, pageBuilder: pageBuilder, contentTypes: {rowA: rowA, hero: hero, columns: columns}};
    document.getElementById('pb').innerHTML = convertTemplate(stageWrapperTemplate(
        fetchText('ui/template/' + templateName(panel) + '.html')
    ).replace(/^[\s\S]*?-->/, ''));
    renderStage();
    ko.applyBindings(pageBuilder, document.getElementById('pb'));
    modules['Magento_PageBuilder/js/events'].trigger('stage:stage1:readyAfter');

    if (params.get('form') === '1') {
        renderEditForm();
    }
    document.body.classList.add('harness-ready');
}());
