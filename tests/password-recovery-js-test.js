/* Run with: node tests/password-recovery-js-test.js. No npm dependencies. */
const fs = require('fs');
const resetSource = fs.readFileSync('assets/js/reset-password.js', 'utf8');
const forgotSource = fs.readFileSync('assets/js/forgot-password.js', 'utf8');
let checks = 0;
function assert(value, label) {
    if (!value) throw new Error(label);
    checks++;
}
class Element {
    constructor() {
        this.value = '';
        this.type = 'password';
        this.disabled = false;
        this.innerHTML = 'Reset Password <i class="bi bi-arrow-right"></i>';
        this.textContent = '';
        this.style = {};
        this.events = {};
        this.attributes = {};
        this.classes = new Set();
        this.classList = { toggle: (name, enabled) => enabled ? this.classes.add(name) : this.classes.delete(name) };
    }
    addEventListener(name, handler) { (this.events[name] ||= []).push(handler); }
    setAttribute(name, value) { this.attributes[name] = value; }
    querySelector() { return this.icon ||= new Element(); }
    closest() { return this.wrapper ||= new Element(); }
    checkValidity() { return this.value.includes('@'); }
    focus() {}
    fire(name, prevented = false) {
        const event = { defaultPrevented: prevented, preventDefault() { this.defaultPrevented = true; } };
        for (const handler of this.events[name] || []) handler(event);
        return event;
    }
}
function page(source, absent = false) {
    const elements = {};
    const document = new Element();
    const window = new Element();
    const bars = Array.from({ length: 4 }, () => new Element());
    document.getElementById = (id) => absent ? null : (elements[id] ||= new Element());
    document.querySelectorAll = () => bars;
    new Function('document', 'window', source)(document, window);
    assert(document.events.DOMContentLoaded.length === 1, 'Single initialization');
    document.fire('DOMContentLoaded');
    return { elements, bars, window };
}
page(resetSource, true); // Invalid-token page has no form.
const { elements: e, bars, window: win } = page(resetSource);
assert(e.resetPasswordForm.events.submit.length === 1, 'One submit handler');
assert(e.passwordToggle.events.click.length === 1 && e.confirmPasswordToggle.events.click.length === 1, 'One toggle handler per field');
assert(e.submitButton.disabled, 'Initially disabled');
function fill(password, confirmation) {
    e.password.value = password;
    e.confirm_password.value = confirmation;
    e.password.fire('input');
    e.confirm_password.fire('input');
}
for (const [password, confirmation, missing] of [
    ['abcdefgh', 'abcdefgh', 'numberRequirement'],
    ['12345678', '12345678', 'letterRequirement'],
    ['Abc1', 'Abc1', 'lengthRequirement'],
    ['éééA1', 'éééA1', 'lengthRequirement'],
    ['Password123', '', null],
    ['Password123', 'other', null],
]) {
    fill(password, confirmation);
    assert(e.submitButton.disabled, 'Invalid input disables button');
    assert(e.resetPasswordForm.fire('submit').defaultPrevented, 'Invalid submit blocked');
    assert(e.submitButton.textContent !== 'Resetting...', 'No invalid loading state');
    if (missing) {
        assert(!e[missing].classes.has('valid'), 'Missing requirement has no green check');
        assert(e.strengthText.textContent === 'Requirements missing', 'Strength cannot imply validity');
        assert(bars[0].style.background !== '#168454', 'Invalid meter is not green');
    }
}
fill('Password123', 'Password123');
assert(!e.submitButton.disabled, 'Valid input enables button');
assert(!e.passwordError.textContent && !e.confirmPasswordError.textContent, 'Stale errors cleared');
for (const id of ['lengthRequirement', 'letterRequirement', 'numberRequirement']) assert(e[id].classes.has('valid'), 'Satisfied requirement is green');
assert(e.strengthText.textContent === 'Good', 'Valid basic strength');
fill('LongPassword123!', 'LongPassword123!');
assert(e.strengthText.textContent === 'Strong' && bars.every((bar) => bar.style.background === '#168454'), 'Strong meter');
for (const [toggle, input] of [['passwordToggle', 'password'], ['confirmPasswordToggle', 'confirm_password']]) {
    e[toggle].fire('click');
    assert(e[input].type === 'text' && e[toggle].innerHTML.includes('bi-eye-slash'), 'Eye reveals password');
    assert(e[toggle].attributes['aria-label'] === 'Hide password', 'Accessible toggle label');
    e[toggle].fire('click');
    assert(e[input].type === 'password' && e[toggle].innerHTML.includes('bi-eye"'), 'Eye hides password');
}
assert(e.resetPasswordForm.fire('submit', true).defaultPrevented && e.submitButton.textContent !== 'Resetting...', 'Already canceled submit has no loading');
assert(!e.resetPasswordForm.fire('submit').defaultPrevented, 'Valid submit proceeds');
assert(e.submitButton.disabled && e.submitButton.textContent === 'Resetting...', 'Loading after valid submit');
assert(e.resetPasswordForm.fire('submit').defaultPrevented, 'Double submit blocked');
win.fire('pageshow');
assert(!e.submitButton.disabled && e.submitButton.innerHTML.includes('Reset Password'), 'Back navigation restores button');
fill('LongPassword123!', 'different');
assert(e.confirmPasswordError.textContent === 'Passwords do not match.', 'Mismatch displayed');
e.password.value = 'different';
e.password.fire('input');
assert(!e.confirmPasswordError.textContent && e.submitButton.disabled, 'Either field updates confirmation and validity');
const rerender = page(resetSource).elements;
assert(rerender.submitButton.disabled && rerender.submitButton.textContent !== 'Resetting...', 'Server rerender starts without loading');

const forgot = page(forgotSource);
assert(forgot.elements.forgotPasswordForm.events.submit.length === 1, 'Forgot single submit handler');
assert(forgot.elements.forgotPasswordForm.fire('submit').defaultPrevented, 'Empty email blocked');
forgot.elements.email.value = 'test@example.com';
assert(!forgot.elements.forgotPasswordForm.fire('submit').defaultPrevented, 'Email submit allowed');
assert(forgot.elements.forgotPasswordForm.fire('submit').defaultPrevented, 'Forgot double submit blocked');
forgot.window.fire('pageshow');
assert(!forgot.elements.submitButton.disabled, 'Forgot button restored');
console.log(`Password recovery JavaScript tests passed (${checks} assertions).`);
