const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function element() {
    return { listeners: {}, dataset: {}, disabled: false, value: '', textContent: '',
        addEventListener(name, fn) { this.listeners[name] = fn; },
        setAttribute() {}, removeAttribute() {}, focus() { this.focused = true; } };
}
const form=element(), token=element(), accountNumber=element(), cancel=element(), confirm=element(), trigger=element();
trigger.dataset={accountToken:'opaque-selection-token',accountNumber:'02-0504-9986'};
const secondTrigger=element();
secondTrigger.dataset={accountToken:'second-selection-token',accountNumber:'03-0123-4567'};
const controls={'[data-unlink-form]':form,'[data-unlink-token]':token,'[data-unlink-account-number]':accountNumber,'[data-unlink-cancel]':cancel,'[data-unlink-confirm]':confirm};
const dialog=element();
dialog.querySelector=(selector)=>controls[selector];
dialog.showModal=()=>{dialog.open=true;};
dialog.close=()=>{dialog.open=false;dialog.listeners.close();};
const windowListeners={};
vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/account-unlinking.js'),'utf8'),{
    document:{getElementById:()=>dialog,querySelectorAll:()=>[trigger,secondTrigger]},
    window:{addEventListener:(name,fn)=>{windowListeners[name]=fn;}}
});
trigger.listeners.click();
assert.equal(dialog.open,true);
assert.equal(token.value,'opaque-selection-token');
assert.equal(accountNumber.textContent,'02-0504-9986');
assert.equal(confirm.disabled,false,'Opening the dialog began submission.');
cancel.listeners.click();
assert.equal(dialog.open,false);
assert.equal(token.value,'');
assert.equal(trigger.focused,true);
secondTrigger.listeners.click();
assert.equal(accountNumber.textContent,'03-0123-4567','Second card showed the previous account code.');
assert.equal(token.value,'second-selection-token','Second card changed backend selection incorrectly.');
cancel.listeners.click();
let prevented=false;
const event={preventDefault(){prevented=true;}};
form.listeners.submit(event);
assert.equal(prevented,true,'Cancelled dialog retained a usable submission token.');
trigger.listeners.click();prevented=false;
assert.equal(accountNumber.textContent,'02-0504-9986','First card retained the second card account code.');
form.listeners.submit(event);
assert.equal(prevented,false);
assert.equal(confirm.disabled,true);
assert.equal(cancel.disabled,true);
form.listeners.submit(event);
assert.equal(prevented,true,'Double submission was accepted.');
prevented=false;dialog.listeners.cancel(event);
assert.equal(prevented,true,'Escape interrupted an active submission.');
windowListeners.pageshow();
assert.equal(dialog.open,false);
assert.equal(confirm.disabled,false);
assert.equal(token.value,'');
console.log('Account unlink confirmation tests passed.');
