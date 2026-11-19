/**
 * Tests the mixin on core's confirmation dialog (js/modal/dismissible-confirm-mixin.js), on a stand-in for the
 * jQuery UI widget: it focuses Cancel (or OK), Escape closes it like Cancel, and the Escape listener goes as it
 * closes, also when a dismissed dialog never opens.
 *
 * Run from the module root: node Test/Js/dismissible-confirm.test.js
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const listeners = [];
const document = {
    addEventListener: function (type, listener, capture) {
        listeners.push({type: type, listener: listener, capture: capture});
    },
    removeEventListener: function (type, listener, capture) {
        const index = listeners.findIndex(function (entry) {
            return entry.type === type && entry.listener === listener && entry.capture === capture;
        });

        if (index !== -1) {
            listeners.splice(index, 1);
        }
    }
};

/**
 * Core's widget, as far as the mixin uses it: a dismissed dialog doesn't open.
 */
const coreWidget = {
    openModal: function () {
        if (this.dismissed) {
            return 'element';
        }
        this.options.isOpen = true;

        return 'element';
    },
    closeModal: function (result) {
        this.closed.push(result);
        this.options.isOpen = false;

        return 'element';
    },
    _setFocus: function (position, type) {
        this.focused.push(this.options.focus + ' ' + position + ' ' + type);
    }
};

/**
 * $.widget(name, base, prototype), with _super.
 */
function widget(name, base, prototype) {
    const extended = Object.create(base);

    Object.keys(prototype).forEach(function (key) {
        extended[key] = function () {
            const previous = this._super;
            let result;

            this._super = function () {
                return base[key].apply(this, arguments);
            };
            result = prototype[key].apply(this, arguments);
            this._super = previous;

            return result;
        };
    });
    $.mage[name.split('.')[1]] = extended;
}

const $ = {widget: widget, mage: {dismissibleConfirm: coreWidget}};
const factory = function () {};
let mixin;

vm.runInNewContext(
    fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/js/modal/dismissible-confirm-mixin.js'), 'utf8'),
    {
        document: document,
        define: function (deps, definition) {
            assert.strictEqual(deps.join(), 'jquery');
            mixin = definition($);
        }
    }
);

assert.strictEqual(mixin(factory), factory, 'the module still returns core\'s factory');

/**
 * @param {Object} config
 * @returns {Object}
 */
function dialog(config) {
    const instance = Object.create($.mage.dismissibleConfirm);
    const buttons = config.buttons;

    instance.options = {transitionEvent: config.transitionEvent, isOpen: false};
    instance.dismissed = !!config.dismissed;
    instance.closed = [];
    instance.focused = [];
    instance.modal = {
        find: function (selector) {
            return {length: buttons.indexOf(selector) === -1 ? 0 : 1};
        }
    };

    return instance;
}

/**
 * @param {String} key
 * @returns {Object}
 */
function keydown(key) {
    const event = {
        key: key,
        prevented: false,
        stopped: false,
        preventDefault: function () {
            this.prevented = true;
        },
        stopPropagation: function () {
            this.stopped = true;
        }
    };

    listeners.slice().forEach(function (entry) {
        if (entry.type === 'keydown') {
            entry.listener(event);
        }
    });

    return event;
}

// Opens with the focus on Cancel; Escape, in the capture phase, closes it as Cancel does.
let confirm = dialog({buttons: ['.action-dismiss', '.action-accept'], transitionEvent: 'transitionend'});

assert.strictEqual(confirm.openModal(), 'element');
assert.strictEqual(confirm.options.focus, '.action-dismiss');
assert.deepStrictEqual(confirm.focused, [], 'core focuses at the end of the transition');
assert.strictEqual(listeners.length, 1);
assert.strictEqual(listeners[0].capture, true);

let event = keydown('Enter');

assert.deepStrictEqual(confirm.closed, [], 'other keys are left alone');
assert.strictEqual(event.prevented, false);

event = keydown('Escape');
assert.deepStrictEqual(confirm.closed, [false]);
assert.strictEqual(event.prevented, true);
assert.strictEqual(event.stopped, true, 'the Escape doesn\'t reach what is under the dialog');
assert.strictEqual(listeners.length, 0, 'closing drops the listener');

keydown('Escape');
assert.deepStrictEqual(confirm.closed, [false], 'a closed dialog no longer listens');

// Closing with a button drops the listener too; reopening doesn't stack listeners.
confirm = dialog({buttons: ['.action-dismiss', '.action-accept'], transitionEvent: 'transitionend'});
confirm.openModal();
confirm.openModal();
assert.strictEqual(listeners.length, 1);
confirm.closeModal(true);
assert.strictEqual(listeners.length, 0);

// No Cancel: OK takes the focus. No transition: focused straight away. "Esc" from older browsers.
confirm = dialog({buttons: ['.action-accept']});
confirm.openModal();
assert.deepStrictEqual(confirm.focused, ['.action-accept end opened']);
keydown('Esc');
assert.deepStrictEqual(confirm.closed, [false]);
assert.strictEqual(listeners.length, 0);

// A dismissed dialog doesn't open, so it doesn't listen.
confirm = dialog({buttons: ['.action-dismiss', '.action-accept'], dismissed: true});
confirm.openModal();
assert.strictEqual(listeners.length, 0);
assert.deepStrictEqual(confirm.focused, []);

console.log('OK');
