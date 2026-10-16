const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
let checks=0;const check=(actual,expected,message)=>{checks++;assert.equal(actual,expected,message);};
function node(){return {listeners:{},dataset:{},value:'',checked:false,disabled:false,hidden:false,children:[],textContent:'',scrollTop:0,clientHeight:200,scrollHeight:1000,addEventListener(n,f){this.listeners[n]=f;},focus(){this.focused=true;},append(...nodes){this.children.push(...nodes);},replaceChildren(){this.children=[];},replaceWith(other){this.replacement=other;},checkValidity(){return !this.required||!!this.value.trim();},reportValidity(){return this.checkValidity();}};}
async function setup(kind='new'){
    const names=['form','status','summary','review','token','terms-token','version','terms','consent','confirm','cancel','next','back','progress','email'];
    const nodes=Object.fromEntries(names.map(name=>[name,node()])),modal=node(),heading=node();
    const fields=['first_name','last_name','middle_name','extension_name','contact_number'].map(name=>Object.assign(node(),{name,required:['first_name','last_name','contact_number'].includes(name)}));
    fields[4].checkValidity=fields[4].reportValidity=function(){return /^09[0-9]{9}$/.test(this.value);};
    const screens=[1,2,3].map(number=>Object.assign(node(),{dataset:{ebillsScreen:String(number)}}));
    nodes.terms.dataset.version='sha256:current';
    modal.querySelector=selector=>nodes[selector.slice(13,-1)];
    modal.querySelectorAll=selector=>selector==='[data-ebills-personal]'?fields:screens;
    modal.showModal=()=>{modal.open=true;};modal.close=()=>{modal.open=false;modal.listeners.close();};
    nodes.form.reset=()=>{fields.forEach(field=>field.value='');nodes.consent.checked=false;};
    const card=Object.assign(node(),{dataset:{accountToken:'card-token',ebillsCode:'02-0504-9986'}}),optional=Object.assign(node(),{dataset:{accountToken:'optional-token',ebillsCode:'02-0504-9986'}});
    const listeners={},calls=[],requests=[];let complete;
    const profile=kind==='missing'?{email:'portal@example.test'}:{first_name:'Portal',last_name:'User',middle_name:'',extension_name:'Jr.',email:'portal@example.test',contact_number:'09123456789'};
    const details={account:{AcctCode:'02-0504-9986',Name:'DIFFERENT ACCOUNT HOLDER',Address:'Service address'},profile,enrolled:kind==='existing',problem:'',terms_token:'server-consent-token',terms_version:kind==='version'?'sha256:changed':'sha256:current'};
    vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/ebills-enrollment.js'),'utf8'),{
        document:{getElementById:id=>id==='ebills-dialog'?modal:heading,addEventListener:(n,f)=>listeners[n]=f,createElement:node,querySelectorAll:()=>[card,optional],querySelector:()=>null},
        FormData:class{constructor(){this.action='ebills_enroll';for(const field of fields)this[field.name]=field.value;this.terms_token=nodes['terms-token'].value;this.terms_version=nodes.version.value;this.account_token=nodes.token.value;}set(key,value){this[key]=value;}},
        fetch:async(url,options)=>{
            const data=options.body;calls.push(data.action);requests.push(data);
            const answer=(ok,result)=>({ok,json:async()=>({ok,...result})});
            if(data.action==='ebills_details')return answer(kind!=='lookup-failure',kind==='lookup-failure'?{message:'Lookup unavailable.'}:{details});
            if(data.action==='ebills_accept_terms')return answer(kind!=='accept-failure',{message:'Terms acceptance failed.'});
            return new Promise(resolve=>{complete=()=>resolve(answer(kind!=='enroll-failure',{created:true,message:kind==='enroll-failure'?'Enrollment failed.':'Successfully Enrolled in eBills'}));});
        }
    });
    const open=button=>listeners.click({target:{closest:()=>button||card}}),event={preventDefault(){}};
    await open();check(nodes.summary.hidden,true,'Step 1 exposes account details.');return {nodes,fields,screens,modal,card,optional,calls,requests,open,event,finish:()=>complete()};
}
async function accept(s){
    const n=s.nodes;check(n.next.disabled,true);check(n.consent.disabled,true);check(n.consent.checked,false);
    await n.form.listeners.submit(s.event);check(s.calls.includes('ebills_enroll'),false,'Direct submit bypassed terms.');
    n.consent.checked=true;await n.next.listeners.click();check(s.calls.length,1,'Could accept without scrolling.');n.consent.checked=false;
    n.terms.scrollTop=800;n.terms.listeners.scroll();check(n.consent.disabled,false);check(n.next.disabled,true);
    n.consent.checked=true;n.consent.listeners.change();check(n.next.disabled,false);
    await n.next.listeners.click();
}
async function run(){
    for(const kind of ['existing','lookup-failure','version']){
        const s=await setup(kind);check(s.screens[0].hidden,false);check(s.screens[1].hidden,true);s.nodes.terms.scrollTop=800;s.nodes.terms.listeners.scroll();check(s.nodes.consent.disabled,true);check(s.nodes.next.disabled,true);s.nodes.cancel.listeners.click();check(s.calls.length,1);
    }
    const failure=await setup('accept-failure');await accept(failure);check(failure.screens[0].hidden,false);check(failure.nodes.status.textContent,'Terms acceptance failed.');failure.nodes.cancel.listeners.click();check(failure.calls.includes('ebills_enroll'),false);
    const cancel=await setup();await accept(cancel);check(cancel.screens[1].hidden,false);cancel.nodes.cancel.listeners.click();check(cancel.calls.includes('ebills_enroll'),false,'Cancel enrolled an account.');check(cancel.card.focused,true);
    for(const kind of ['new','missing','enroll-failure']){
        const s=await setup(kind),n=s.nodes;await accept(s);check(s.screens[1].hidden,false);check(s.screens[2].hidden,true);check(n.summary.hidden,false,'Step 2 lost account identification.');
        check(n.summary.children.length,3,'Account holder repeated in personal fields.');check(n.summary.children[0].children[1].textContent,'02-0504-9986');check(n.summary.children[2].children[1].textContent,'Service address');check(n.email.value,'portal@example.test');
        check(s.fields[0].value,kind==='missing'?'':'Portal','Name came from account holder.');
        n.back.listeners.click();check(s.screens[0].hidden,false);check(n.summary.hidden,true,'Back exposed account details on Step 1.');check(s.calls.includes('ebills_enroll'),false);await n.next.listeners.click();
        s.fields[0].value='';await n.next.listeners.click();check(s.screens[1].hidden,false,'Empty first name advanced.');
        s.fields[0].value='Reviewed';s.fields[1].value='User';s.fields[4].value='bad';await n.next.listeners.click();check(s.screens[1].hidden,false);
        s.fields[4].value='09123456789';await n.next.listeners.click();check(s.screens[2].hidden,false);check(n.summary.hidden,false,'Review lost account identification.');check(n.review.children[0].children[1].textContent,'Reviewed');check(n.confirm.disabled,false);
        n.back.listeners.click();s.fields[0].value='Changed';await n.next.listeners.click();check(n.review.children[0].children[1].textContent,'Changed');
        check(s.calls.includes('ebills_enroll'),false,'Review or Back submitted enrollment.');
        const pending=n.form.listeners.submit(s.event);await n.form.listeners.submit(s.event);check(s.calls.filter(x=>x==='ebills_enroll').length,1,'Duplicate submission.');
        let prevented=false;s.modal.listeners.cancel({preventDefault(){prevented=true;}});check(prevented,true);check(n.cancel.disabled,true);check(n.back.disabled,true);
        const sent=s.requests.at(-1);check(sent.first_name,'Changed');check(sent.terms_token,'server-consent-token');check(sent.consent,'1');
        s.finish();await pending;
        if(kind==='enroll-failure'){check(n.status.textContent,'Enrollment failed.');check(n.confirm.disabled,false);check(n.review.children[0].children[1].textContent,'Changed');check(s.card.replacement,undefined);}
        else{check(n.confirm.hidden,true);check(s.card.replacement.textContent,'eBills Enrolled');check(s.optional.replacement.textContent,'eBills Enrolled');check(n.cancel.textContent,'Close');}
        n.cancel.listeners.click();await s.open(s.optional);check(n.token.value,'optional-token');check(n.consent.checked,false,'Reopening preserved checked consent.');check(n.consent.disabled,true);check(s.screens[0].hidden,false);check(s.screens[1].hidden,true);
    }
    console.log(`eBills three-screen modal tests passed (${checks} assertions).`);
}
run().catch(error=>{console.error(error);process.exitCode=1;});
