const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const fixtures = require('./profile-mobile-fixtures.json');
const source = fs.readFileSync(require.resolve('../assets/js/profile-settings.js'), 'utf8');
function setup(value) {
    const listeners = {}, attributes = {};
    const field = {value, validity: '', focused: false, setCustomValidity(v) {this.validity=v;}, setAttribute(k,v) {attributes[k]=v;}, focus() {this.focused=true;}, addEventListener(k,v) {listeners[k]=v;}};
    const error = {hidden:true,textContent:''};
    const form = {querySelector:()=>field,addEventListener(k,v) {listeners[k]=v;}};
    vm.runInNewContext(source, {document:{querySelector:()=>form,getElementById:()=>error}});
    return {field,error,listeners,attributes};
}
for (const [raw,expected] of Object.entries(fixtures.valid)) {
    for (const event of ['blur','submit']) {
        const state=setup(raw); let prevented=false;
        assert.equal(state.field.value,raw,'Do not rewrite stored number on load');
        state.listeners.input(); assert.equal(state.field.value,raw,'Do not rewrite while typing/pasting');
        state.listeners[event]({preventDefault(){prevented=true;}});
        assert.equal(state.field.value,expected??''); assert.equal(prevented,false);
        assert.equal(state.error.hidden,true); assert.equal(state.field.validity,'');
    }
}
for (const raw of fixtures.invalid) {
    const state=setup(raw); let prevented=false;
    state.listeners.submit({preventDefault(){prevented=true;}});
    assert.equal(prevented,true); assert.equal(state.field.value,raw);
    assert.equal(state.error.hidden,false); assert.equal(state.attributes['aria-invalid'],'true');
    assert.ok(state.error.textContent.includes('Spaces and hyphens')); assert.equal(state.field.focused,true);
    state.field.value='+63 912-345-6789'; state.listeners.input();
    assert.equal(state.field.value,'+63 912-345-6789'); assert.equal(state.field.validity,'');
    state.listeners.blur(); assert.equal(state.field.value,'09123456789'); assert.equal(state.error.hidden,true);
}
vm.runInNewContext(source,{document:{querySelector:()=>null}});
console.log('Profile mobile JS tests passed (shared format fixtures, blur, submit/Enter, paste/editing, errors, optional blanks).');
