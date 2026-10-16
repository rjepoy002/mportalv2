const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

(async () => {
    const focused=[], scrolled=[], navigated=[], requests=[];
    let forms, stage='step1';
    const makeForm=(action)=>{
        const button={textContent:action,disabled:false,dataset:{}};
        return {action,button,dataset:{loadingText:'Working…'},listeners:{},
            addEventListener(name,fn){this.listeners[name]=fn;},setAttribute(){},removeAttribute(){},
            querySelector(){return button;},
            querySelectorAll(selector){return selector==='button:not(:disabled)' ? (button.disabled?[]:[button]) : (button.dataset.linkPreviousText!==undefined?[button]:[]);}};
    };
    const panel={
        querySelector(selector){
            if(selector==='[data-link-error]')return stage.startsWith('error')?{focus:()=>focused.push('error')}:null;
            if(selector==='[data-link-step-heading]')return {focus:()=>focused.push('heading')};
            return null;
        },scrollIntoView(options){scrolled.push(options.block);},
        set outerHTML(value){stage=value;forms=stage.endsWith('step2')?[makeForm('verify_link'),makeForm('restart_link')]:[makeForm('add')];}
    };
    forms=[makeForm('add')];
    const results=[
        {ok:true,linked:false,html:'step2'},
        {ok:false,linked:false,html:'error-step2'},
        {ok:true,linked:false,html:'step1'},
        {ok:true,linked:false,html:'step2'},
        {ok:false,linked:false,html:'error-step1'},
        {ok:true,linked:false,html:'step2'},
        {ok:true,linked:true,html:'step1'}
    ];
    vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/account-linking.js'),'utf8'),{
        document:{querySelectorAll:()=>forms,getElementById:()=>panel},
        FormData:class {constructor(form){this.action=form.action;}},
        window:{addEventListener(){},location:{assign:url=>navigated.push(url)},
            fetch:async (url,options)=>{requests.push({url,options});return {ok:true,json:async()=>results.shift()};}}
    });
    const submit=async(index=0)=>{let prevented=false;await forms[index].listeners.submit({preventDefault(){prevented=true;}});assert.equal(prevented,true);};
    await submit();
    assert.equal(stage,'step2');assert.equal(navigated.length,0,'Step 1 caused a page reload.');
    assert.equal(focused.at(-1),'heading');assert.equal(scrolled.at(-1),'start');
    assert.equal(requests[0].options.headers.Accept,'application/json');
    assert.equal(requests[0].options.credentials,'same-origin');
    await submit();assert.equal(stage,'error-step2');assert.equal(focused.at(-1),'error');
    await submit(1);assert.equal(stage,'step1','Back did not return to details.');
    await submit();await submit();assert.equal(stage,'error-step1','Expired challenge did not return to details.');
    assert.equal(navigated.length,0,'Intermediate or error response triggered final navigation.');
    await submit();await submit();
    assert.deepEqual(navigated,['dashboard.php?page=account-settings']);
    assert.equal(results.length,0);
    console.log('Account linking navigation tests passed (Step 1, errors, Back, expiry and final success).');
})().catch(error=>{console.error(error);process.exitCode=1;});
