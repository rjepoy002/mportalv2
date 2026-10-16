const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const windowListeners = {};
function button(text, disabled = false) {
    return { textContent: text, disabled, dataset: {} };
}
function form(buttons, loadingText) {
    const attributes = new Map();
    return {
        buttons, attributes, dataset: { loadingText }, listeners: {},
        addEventListener(event, callback) { this.listeners[event] = callback; },
        setAttribute(name, value) { attributes.set(name, value); },
        removeAttribute(name) { attributes.delete(name); },
        querySelector() { return buttons[0]; },
        querySelectorAll(selector) {
            if (selector === 'button:not(:disabled)') return buttons.filter((b) => !b.disabled);
            if (selector === '[data-link-previous-text]') return buttons.filter((b) => b.dataset.linkPreviousText !== undefined);
            throw new Error(`Unexpected selector ${selector}`);
        },
    };
}
const verify = button('Verify and Link Account');
const back = button('Back');
const policyDisabled = button('Continue', true);
const forms = [form([verify], 'Verifying account…'), form([back], 'Restarting verification…'), form([policyDisabled], 'Verifying account…')];
vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/account-linking.js'), 'utf8'), {
    document: { querySelectorAll: () => forms },
    window: { addEventListener: (name, callback) => { windowListeners[name] = callback; } },
});
let prevented = false;
const event = { preventDefault() { prevented = true; } };
forms[0].listeners.submit(event);
assert.equal(prevented, false);
assert.equal(verify.disabled, true);
assert.equal(back.disabled, true);
assert.equal(verify.textContent, 'Verifying account…');
assert.equal(forms[0].attributes.get('aria-busy'), 'true');
forms[1].listeners.submit(event);
assert.equal(prevented, true, 'A second form bypassed submission protection.');
windowListeners.pageshow();
assert.equal(verify.disabled, false);
assert.equal(back.disabled, false);
assert.equal(verify.textContent, 'Verify and Link Account');
assert.equal(policyDisabled.disabled, true, 'Browser navigation enabled a policy-disabled control.');
assert.equal(forms[0].attributes.has('aria-busy'), false);
prevented = false;
forms[1].listeners.submit(event);
assert.equal(prevented, false);
assert.equal(back.textContent, 'Restarting verification…');
console.log('Account linking submission-control tests passed.');
