const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
function element(){return {listeners:{},dataset:{},value:'',textContent:'',disabled:false,classList:{add(){},remove(){}},addEventListener(n,f){this.listeners[n]=f;},setAttribute(){},removeAttribute(){},focus(){this.focused=true;}};}
async function scenario(action,fail=false){
    const form=element(),intent=element(),cancel=element(),confirm=element(),error=element(),title=element(),description=element(),trigger=element(),dialog=element();
    const selectors={'[data-session-form]':form,'[data-session-intent]':intent,'[data-session-cancel]':cancel,'[data-session-confirm]':confirm,'[data-session-error]':error,'#session-action-title':title,'#session-action-description':description};
    dialog.querySelector=s=>selectors[s];dialog.showModal=()=>{dialog.open=true;};dialog.close=()=>{dialog.open=false;dialog.listeners.close();};trigger.dataset.sessionAction=action;
    let calls=0,resolve;const navigated=[];
    vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/session-actions.js'),'utf8'),{
        document:{getElementById:()=>dialog,querySelectorAll:()=>[trigger]},FormData:class{},
        window:{addEventListener(){},location:{assign:u=>navigated.push(u)},fetch:()=>{calls++;return new Promise(r=>resolve=r);}}
    });
    trigger.listeners.click();assert.equal(dialog.open,true);assert.equal(calls,0);assert.equal(cancel.focused,true);
    assert.equal(title.textContent,action==='reset'?'Reset your password?':'Sign out of PALECO MCO Portal?');
    assert.equal(confirm.textContent,action==='reset'?'Continue to Reset':'Sign Out');
    cancel.listeners.click();assert.equal(dialog.open,false);assert.equal(calls,0);assert.equal(trigger.focused,true);
    trigger.listeners.click();let prevented=false;const event={preventDefault(){prevented=true;}};
    // Escape is not prevented while idle; the browser closes the native dialog.
    dialog.listeners.cancel(event);assert.equal(prevented,false);dialog.close();assert.equal(calls,0);
    trigger.listeners.click();const pending=form.listeners.submit(event);await form.listeners.submit(event);
    assert.equal(calls,1,'Confirmation was submitted twice.');assert.equal(confirm.disabled,true);
    prevented=false;dialog.listeners.cancel(event);assert.equal(prevented,true);
    resolve({ok:!fail,json:async()=>fail?{ok:false,message:'Session check failed.'}:{ok:true,redirect:action==='reset'?'forgot-password.php':'index.php'}});
    await pending;
    if(fail){assert.equal(error.textContent,'Session check failed.');assert.equal(error.focused,true);assert.equal(confirm.disabled,false);assert.equal(navigated.length,0);}
    else{assert.deepEqual(navigated,[action==='reset'?'forgot-password.php':'index.php']);await form.listeners.submit(event);assert.equal(calls,1);}
}
(async()=>{await scenario('reset');await scenario('logout');await scenario('logout',true);console.log('Session confirmation UI tests passed (Cancel, Escape, focus, once-only POST, errors and both redirects).');})().catch(e=>{console.error(e);process.exitCode=1;});
