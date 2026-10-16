// Real browser layout checks with synthetic data; no portal HTTP/DB/mail actions.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{pathToFileURL}=require('node:url'),{spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'..'),chrome=process.env.CHROME_BINARY||'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const directory=fs.mkdtempSync(path.join(os.tmpdir(),'paleco-ebills-layout-'));
const script=`
(async()=>{
 const checks=[];const check=(value,message)=>{if(!value)throw Error(message);checks.push(message);};
 const find=name=>document.querySelector('[data-ebills-'+name+']');
 const tick=()=>new Promise(resolve=>setTimeout(resolve,60));
 try{
  for(let i=0;i<80&&getComputedStyle(find('names')).display!=='grid';i++)await tick();
  check(getComputedStyle(find('names')).display==='grid','Tailwind styles loaded');
  let light;
  for(const dark of [false,true]){
   document.documentElement.classList.toggle('dark',dark);await tick();document.querySelector('[data-ebills-open]').click();await tick();
   const modal=document.getElementById('ebills-dialog'),terms=find('terms'),consent=find('consent'),next=find('next');
   check(modal.open,'modal opened');check(find('summary').hidden,'Step 1 account card hidden');
   check(!modal.textContent.includes('Official PALECO terms source'),'external source link removed');
   check(terms.querySelectorAll('li').length===10,'complete terms present');
   check(terms.scrollHeight>terms.clientHeight,'terms scrollable');
   check(terms.getBoundingClientRect().height<=450,'terms maximum height');
   if(innerWidth>=1024)check(terms.getBoundingClientRect().height>=390,'desktop terms enlarged');
   check(!consent.checked&&consent.disabled&&next.disabled,'initial acceptance locked');
   const inside=element=>{const r=element.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight;};
   check(inside(consent)&&inside(next),'consent and Continue visible in viewport');
   terms.scrollTop=terms.scrollHeight;await tick();terms.dispatchEvent(new Event('scroll'));check(!consent.disabled&&next.disabled,'scroll unlocks checkbox only '+JSON.stringify({dark,top:terms.scrollTop,height:terms.clientHeight,total:terms.scrollHeight,disabled:consent.disabled,next:next.disabled}));
   consent.checked=true;consent.dispatchEvent(new Event('change'));next.click();await tick();
   check(!find('summary').hidden,'Step 2 account visible');
   const inputs=[...find('names').querySelectorAll('input')],boxes=inputs.map(input=>input.getBoundingClientRect());
   const columns=getComputedStyle(find('names')).gridTemplateColumns.split(' ').length;
   check(columns===(innerWidth>=640?2:1),'responsive name column count');
   check(innerWidth>=640?Math.abs(boxes[0].top-boxes[1].top)<1:boxes[1].top>boxes[0].bottom,'name row positions');
   check(inputs[0].value==='Portal'&&!inputs[0].readOnly,'editable portal profile prefill');
   check(find('email').readOnly,'registered email read-only');
   next.click();await tick();check(!find('summary').hidden,'Step 3 account visible');
   check(find('summary').textContent.includes('02-0504-9986'),'correct review account');
   check(modal.textContent.split('SYNTHETIC ACCOUNT').length===2,'account name not duplicated');
   const background=getComputedStyle(modal).backgroundColor;if(!dark)light=background;else check(background!==light,'dark theme applied');
   find('cancel').click();check(!modal.open,'Cancel closes without enrollment');
  }
  document.getElementById('layout-result').textContent=JSON.stringify({ok:true,width:innerWidth,height:innerHeight,checks:checks.length});
 }catch(error){document.getElementById('layout-result').textContent=JSON.stringify({ok:false,error:error.message,width:innerWidth,height:innerHeight});}
})();`;
try{
 const rendered=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{cwd:root,encoding:'utf8',input:"<?php require 'includes/ebills-enrollment.php'; function dashboardEscape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');} $csrf='synthetic'; include 'includes/ebills-ui.php';"});
 if(rendered.status!==0)throw Error(rendered.stderr||'Template rendering failed.');
 const fixture=path.join(directory,'fixture.html');
 fs.writeFileSync(fixture,`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><base href="${pathToFileURL(root+path.sep).href}"><script src="https://cdn.tailwindcss.com"></script><script>tailwind.config={darkMode:'class',theme:{extend:{colors:{paleco:{700:'#087449'}}}}};window.fetch=async()=>({ok:true,json:async()=>({ok:true,details:{account:{AcctCode:'02-0504-9986',Name:'SYNTHETIC ACCOUNT',Address:'Synthetic address'},profile:{first_name:'Portal',last_name:'User',email:'portal@example.test',contact_number:'09123456789'},enrolled:false,problem:'',terms_token:'synthetic',terms_version:document.querySelector('[data-ebills-terms]').dataset.version}})});</script></head><body><button data-ebills-open data-account-token="synthetic">Register for eBills</button>${rendered.stdout}<pre id="layout-result"></pre><script>window.addEventListener('load',()=>{${script}});</script></body></html>`);
 for(const [width,height] of [[1440,1000],[768,1024],[390,844]]){
  const browser=spawnSync(chrome,['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--hide-scrollbars','--force-device-scale-factor=1','--user-data-dir='+path.join(directory,'profile-'+width),'--window-size='+width+','+height,'--virtual-time-budget=12000','--dump-dom',pathToFileURL(fixture).href],{encoding:'utf8',timeout:45000,maxBuffer:4*1024*1024,windowsHide:true});
  const match=browser.stdout?.match(/<pre id="layout-result">([^<]+)<\/pre>/);
  if(!match)throw Error('Browser did not produce layout results: '+(browser.error?.message||browser.stderr?.slice(-500)||browser.status));
  const result=JSON.parse(match[1].replaceAll('&quot;','"'));console.log(JSON.stringify(result));if(!result.ok)process.exitCode=1;
 }
}finally{
 // Only this explicitly created temporary fixture/profile directory is removed.
 if(path.dirname(path.resolve(directory))!==path.resolve(os.tmpdir())||!path.basename(directory).startsWith('paleco-ebills-layout-'))throw Error('Unexpected temporary cleanup path.');
 fs.rmSync(directory,{recursive:true,force:true,maxRetries:3,retryDelay:100});
}
