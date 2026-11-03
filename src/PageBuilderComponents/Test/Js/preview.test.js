/**
 * Checks view/adminhtml/web/js/content-type/preview.js on its own: preview requests are debounced, one runs at a
 * time with the latest values, and a destroyed preview aborts its request and ignores late answers.
 *
 * Run from the module root: node Test/Js/preview.test.js
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const requests = [];
let now = 0;
let timers = [];
let ComponentPreview;

/**
 * Run the timers due within the next milliseconds, as the clock moves on.
 *
 * @param {Number} milliseconds
 */
function advance(milliseconds) {
    now += milliseconds;
    timers.filter((timer) => timer.at <= now).sort((a, b) => a.at - b.at).forEach((timer) => {
        timers = timers.filter((other) => other !== timer);
        timer.callback();
    });
}

/**
 * A jqXHR: done, fail and always run in the order they were added, once it settles.
 *
 * @param {Object} data The request's data.
 * @returns {Object}
 */
function fakeRequest(data) {
    const callbacks = [];
    const request = {
        data: data,
        aborted: false,
        settled: false,
        done: (callback) => callbacks.push({on: 'done', callback}) && request,
        fail: (callback) => callbacks.push({on: 'fail', callback}) && request,
        always: (callback) => callbacks.push({on: 'always', callback}) && request,
        settle: (on, args) => {
            assert.ok(!request.settled, 'a request settles once');
            request.settled = true;
            callbacks.filter((entry) => entry.on === on || entry.on === 'always')
                .forEach((entry) => entry.callback.apply(null, args));
        },
        respond: (content) => request.settle('done', [{data: {content: content}}, 'success']),
        abort: () => {
            request.aborted = true;
            request.settle('fail', [request, 'abort']);
        }
    };

    return request;
}

/**
 * @param {*} initial
 * @returns {Function}
 */
function observable(initial) {
    let value = initial;

    return function (newValue) {
        if (arguments.length) {
            value = newValue;
        }

        return value;
    };
}

vm.runInNewContext(fs.readFileSync(path.join(root, 'view/adminhtml/web/js/content-type/preview.js'), 'utf8'), {
    document: {},
    setTimeout: (callback, delay) => {
        const timer = {callback, at: now + delay};

        timers.push(timer);

        return timer;
    },
    clearTimeout: (timer) => {
        timers = timers.filter((other) => other !== timer);
    },
    define: function (deps, factory) {
        /**
         * Core's preview: only what the component's preview uses.
         *
         * @param {Object} contentType
         * @param {Object} config
         * @constructor
         */
        function Preview(contentType, config) {
            this.contentType = contentType;
            this.config = config;
        }
        Preview.prototype.afterObservablesUpdated = function () {};
        Preview.prototype.destroy = function () {};

        ComponentPreview = factory(
            {
                ajax: (url, options) => {
                    const request = fakeRequest(options.data);

                    requests.push(request);

                    return request;
                }
            },
            {observable: observable},
            (text) => text,
            {getConfig: () => '/preview'},
            Preview,
            {
                getComponent: () => ({name: 'test', fields: {title: 'text'}}),
                toStored: (type, value) => String(value)
            },
            {watch: () => {}},
            {watch: () => () => {}}
        );
    }
});

/**
 * @param {Object} state The data store's state, changed by the test.
 * @returns {Object}
 */
function createPreview(state) {
    return new ComponentPreview(
        {dataStore: {getState: () => state, get: () => 'default', set: () => {}}},
        {name: 'test', label: 'Test'}
    );
}

// A burst of updates makes one request, with the values at its end, once nothing changed for 250ms.
const state = {title: 'a'};
const preview = createPreview(state);
preview.afterObservablesUpdated();
advance(100);
state.title = 'b';
preview.afterObservablesUpdated();
advance(249);
assert.strictEqual(requests.length, 0, 'no request before 250ms without changes');
advance(1);
assert.strictEqual(requests.length, 1, 'one request for the burst');
assert.strictEqual(requests[0].data.values.title, 'b');
assert.strictEqual(preview.loading(), true);

// Changes while it runs: no second request in flight, and its answer is not shown, being out of date.
state.title = 'c';
preview.afterObservablesUpdated();
advance(250);
state.title = 'd';
preview.afterObservablesUpdated();
advance(250);
assert.strictEqual(requests.length, 1, 'one request in flight at most');
assert.strictEqual(requests[0].aborted, false);
requests[0].respond('<p>b</p>');
assert.strictEqual(preview.previewUnsanitizedHtml(), '', 'an out-of-date answer is not shown');
assert.strictEqual(requests.length, 2, 'then one request with the latest values');
assert.strictEqual(requests[1].data.values.title, 'd');
assert.strictEqual(preview.loading(), true);
requests[1].respond('<p>d</p>');
assert.strictEqual(preview.previewUnsanitizedHtml(), '<p>d</p>');
assert.strictEqual(preview.displayPreview(), true);
assert.strictEqual(preview.loading(), false);
advance(1000);
assert.strictEqual(requests.length, 2, 'nothing more to ask');

// A failed request shows the error placeholder.
preview.afterObservablesUpdated();
advance(250);
requests[2].settle('fail', [requests[2], 'error']);
assert.strictEqual(preview.displayPreview(), false);
assert.ok(/could not be loaded/.test(preview.placeholderText()));
assert.strictEqual(preview.loading(), false);

// Destroyed with a request running: it is aborted, and nothing touches the preview afterwards.
const destroyed = createPreview({title: 'x'});
destroyed.afterObservablesUpdated();
advance(250);
const running = requests[3];
destroyed.destroy();
assert.strictEqual(running.aborted, true, 'destroy aborts the request');
assert.strictEqual(destroyed.request, null);
assert.strictEqual(destroyed.loading(), true, 'the abort does not update a destroyed preview');
destroyed.afterObservablesUpdated();
advance(1000);
assert.strictEqual(requests.length, 4, 'a destroyed preview makes no request');

// Destroyed before the delay ran out: no request at all.
const early = createPreview({title: 'y'});
early.afterObservablesUpdated();
advance(100);
early.destroy();
advance(1000);
assert.strictEqual(requests.length, 4, 'a pending request is cancelled');

// An answer that arrives after destroy anyway (the browser finished first) is ignored.
const late = createPreview({title: 'z'});
late.afterObservablesUpdated();
advance(250);
const lateRequest = requests[4];
lateRequest.abort = function () {}; // Too late to abort.
late.destroy();
lateRequest.respond('<p>z</p>');
assert.strictEqual(late.previewUnsanitizedHtml(), '', 'a late answer does not update a destroyed preview');
assert.strictEqual(late.displayPreview(), false);

console.log('preview: OK');
