const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const config=JSON.parse(fs.readFileSync(process.argv[2],'utf8')),out=path.join(config.root,'tests/new-connection/applicant-prototype-screenshots');fs.mkdirSync(out,{recursive:true});
const child=spawn(process.env.CHROME_BINARY||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-sandbox','--disable-gpu','--disable-software-rasterizer','--no-first-run','--remote-debugging-port=0','--user-data-dir='+path.join(config.temp,'chrome'),'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let ws,id=0,unloadDialogs=0;const pending=new Map(),checks=[],exceptions=[],requests=[],missingAssets=[];
const pause=ms=>new Promise(r=>setTimeout(r,ms));
function check(v,label){if(!v)throw Error(label);checks.push(label);}
function send(method,params={}){return new Promise((resolve,reject)=>{const key=++id,timer=setTimeout(()=>{pending.delete(key);reject(Error('CDP timeout '+method));},15000);pending.set(key,{resolve,reject,timer});ws.send(JSON.stringify({id:key,method,params}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;}
async function wait(expression){for(let n=0;n<100;n++){try{if(await evaluate(expression))return;}catch{}await pause(100);}throw Error('Timeout '+expression);}
async function go(suffix=''){await send('Page.navigate',{url:config.base+'/prototype'+suffix});await wait('document.readyState==="complete" && !!document.getElementById("prototype-form")');}
async function field(p,value){await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(p)});if(e.type==='checkbox')e.checked=${JSON.stringify(value)};else e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('input',{bubbles:true}));})()`);}
async function section(n){await evaluate(`document.querySelector('[data-section="${n}"]').click()`);}
async function next(n){await evaluate('document.getElementById("next-section").click()');await wait(`!document.querySelector('[data-step="${n}"]').hidden`);}
async function shot(name){const position=await evaluate('scrollY');await evaluate('scrollTo(0,0)');const m=await send('Page.getLayoutMetrics');const r=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width:m.cssContentSize.width,height:m.cssContentSize.height,scale:1}});fs.writeFileSync(path.join(out,name),Buffer.from(r.data,'base64'));await evaluate('scrollTo(0,'+position+')');}
(async()=>{try{
 const endpoint=await new Promise((resolve,reject)=>{let s='';const timer=setTimeout(()=>reject(Error('Chrome startup')),15000);child.once('error',reject);child.stderr.on('data',d=>{s+=d;const m=s.match(/DevTools listening on (ws:\/\/\S+)/);if(m){clearTimeout(timer);resolve(m[1]);}});});
 const port=new URL(endpoint).port,version=await(await fetch('http://127.0.0.1:'+port+'/json/version')).json();
 const target=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((r,j)=>{ws.onopen=r;ws.onerror=j;});
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);if(!p)return;clearTimeout(p.timer);pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}else if(m.method==='Fetch.requestPaused'){const p=m.params;if(p.request.url.startsWith(config.base+'/'))send('Fetch.continueRequest',{requestId:p.requestId});else{requests.push(p.request.url.split('?')[0]);send('Fetch.failRequest',{requestId:p.requestId,errorReason:'BlockedByClient'});}}else if(m.method==='Runtime.exceptionThrown')exceptions.push(m.params.exceptionDetails.text);else if(m.method==='Network.responseReceived'&&m.params.response.url.includes('/assets/')&&m.params.response.status>=400)missingAssets.push(m.params.response.url);else if(m.method==='Page.javascriptDialogOpening'){if(m.params.type==='beforeunload')unloadDialogs++;send('Page.handleJavaScriptDialog',{accept:true});}};
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*',requestStage:'Request'}]});
 for(const width of [1440,390,320]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});await go();
  for(const dark of [false,true]){
   await evaluate(`window.mcoTheme.applyTheme('${dark?'dark':'light'}')`);
   for(let s=0;s<5;s++){await section(s);check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No overflow '+width+' '+dark+' section '+s);}
   await section(0);await shot(`identity-${width}-${dark?'dark':'light'}.png`);
  }
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});check(await evaluate('document.activeElement!==document.body'),'Keyboard Tab focus '+width);
  check(await evaluate('document.getElementById("prototype-email").readOnly'),'Email readonly '+width);
  check(await evaluate('document.getElementById("applicant.first_name").value==="Ana María"'),'Structured Unicode prefill '+width);
  check(await evaluate(`(()=>{const e=document.getElementById('education-attainment');return e.options.length===13&&!e.required&&!e.hasAttribute('data-path')&&!document.getElementById('education-other').required;})()`),'Education choices non-required and outside v1 '+width);
  await evaluate(`(()=>{const e=document.getElementById('education-attainment');e.value='Other';e.dispatchEvent(new Event('change',{bubbles:true}));})()`);
  check(await evaluate(`!document.getElementById('education-other-group').hidden&&!document.getElementById('education-other').disabled`),'Education Other visible '+width);
  await evaluate(`(()=>{document.getElementById('education-other').value='Synthetic specification';const e=document.getElementById('education-attainment');e.value='College Graduate';e.dispatchEvent(new Event('change',{bubbles:true}));})()`);
  check(await evaluate(`document.getElementById('education-other-group').hidden&&document.getElementById('education-other').value===''`),'Education Other clears on selection change '+width);
  check(await evaluate(`!Array.from(document.querySelectorAll('label,input[placeholder]')).some(e=>/optional/i.test(e.textContent||e.placeholder||'')) && document.querySelector('label[for="applicant.first_name"] .proto-required') && !document.querySelector('label[for="applicant.middle_name"] .proto-required')`),'Required markers without optional labels '+width);
  await evaluate('document.getElementById("next-section").click()');await wait('!document.getElementById("error-summary").hidden');
  check(await evaluate('document.activeElement.id==="error-summary"'),'Error-summary focus '+width);
  await evaluate('document.querySelector("#error-summary a").click()');check(await evaluate('document.activeElement.id==="applicant.contact_number"'),'Error link field focus '+width);
  await field('applicant.contact_number','+63 900-000-0000');await evaluate('document.getElementById("applicant.contact_number").dispatchEvent(new FocusEvent("blur"))');await wait('document.getElementById("applicant.contact_number").value==="09000000000"');check(true,'Mobile normalization '+width);
  await field('applicant.profile_confirmed',true);await next(1);
  await evaluate('document.getElementById("back-section").click()');check(await evaluate('!document.querySelectorAll("[data-step]")[0].hidden'),'Back navigation '+width);await next(1);
  await field('residential_address.city_municipality_code','TEST-C1');await field('residential_address.barangay_code','TEST-B1');await field('residential_address.street_purok_sitio','Synthetic Sitio');await field('residential_address.postal_code','0123');await next(2);
  await section(1);await shot(`residential-${width}.png`);await section(2);
  await field('service_location.city_municipality_code','TEST-C2');
  check(await evaluate(`document.querySelector('[id="service_location.barangay_code"] option[value="TEST-B1"]').disabled`),'Synthetic barangay dependency '+width);
  await evaluate(`document.getElementById('service-map-placeholder').dataset.confirmed='true'`);
  await field('service_location.city_municipality_code','TEST-C1');
  check(await evaluate(`document.getElementById('service-map-placeholder').dataset.confirmed==='false'&&document.getElementById('confirm-service-pin').disabled`),'Changed locality invalidates pin and unavailable map cannot confirm '+width);
  await field('service_location.same_as_residential',true);check(await evaluate('document.getElementById("service_location.street_purok_sitio").value==="Synthetic Sitio"'),'Address copy '+width);await shot(`service-location-${width}.png`);await next(3);
  await field('service_request.premises_classification_code','shop');check(await evaluate('!document.getElementById("connection-category").hidden'),'Conditional connection category '+width);check(await evaluate("document.getElementById('service_request.connection_type_code').getAttribute('aria-required')==='true'"),'Conditional category required marker '+width);
  await field('service_request.connection_type_code','ordinary');await field('service_request.occupancy_code','other');check(await evaluate('!document.getElementById("occupancy-other").hidden'),'Conditional occupancy '+width);check(await evaluate("document.getElementById('service_request.occupancy_other').getAttribute('aria-required')==='true'"),'Conditional occupancy required marker '+width);await field('service_request.occupancy_other','Synthetic occupant');await shot(`request-${width}.png`);await next(4);
  check(await evaluate('document.getElementById("review-summary").innerText.includes("Synthetic Sitio") && document.getElementById("review-summary").innerText.includes("prototype@example.invalid")'),'Read-only complete summary '+width);
  check(await evaluate('[...document.querySelectorAll("[data-path^=acknowledgments]")].every(e=>!e.checked)'),'Acknowledgments initially unchecked '+width);
  await evaluate('document.getElementById("check-readiness").click()');await wait('!document.getElementById("error-summary").hidden');check(true,'Readiness rejects missing acknowledgments '+width);
  await field('review.information_confirmed',true);for(const key of ['privacy_notice','information_accuracy','application_verification','physical_signing'])await field('acknowledgments.'+key,true);
  await evaluate('document.getElementById("check-readiness").click()');await wait('document.getElementById("save-status").textContent.includes("readiness check passed")');check(true,'Synthetic readiness only '+width);await shot(`review-${width}.png`);
  await evaluate('document.querySelector("#review-summary button").click()');check(await evaluate('!document.querySelectorAll("[data-step]")[0].hidden'),'Review Edit navigation '+width);
  await field('applicant.middle_name','Synthetic');check(await evaluate('!document.getElementById("review.information_confirmed").checked && !document.getElementById("acknowledgments.privacy_notice").checked'),'Edit resets review and acknowledgments '+width);
  await evaluate('document.getElementById("save-demo").click()');await wait('document.getElementById("save-status").textContent.includes("Disposable fixture draft saved")');check(true,'Explicit fixture save '+width);
  await field('applicant.middle_name','Unsaved example');await send('Input.dispatchMouseEvent',{type:'mousePressed',x:280,y:150,button:'left',clickCount:1});await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:280,y:150,button:'left',clickCount:1});const dialogsBefore=unloadDialogs;
  await go();check(unloadDialogs>dialogsBefore,'Unsaved changes warning '+width);check(await evaluate('document.getElementById("applicant.middle_name").value==="Synthetic"'),'Disposable draft recovery '+width);
  await section(4);check(await evaluate('[...document.querySelectorAll("[data-path^=acknowledgments]")].every(e=>!e.checked)'),'Acknowledgments not restored '+width);
  // A fresh browser session starts from synthetic profile data next iteration.
  await send('Network.clearBrowserCookies');
 }
 await go('?fast_expiry=1');await wait('document.getElementById("expiry-warning").innerText.includes("expired")');check(await evaluate('document.getElementById("save-demo").disabled'),'Expiry stops saves');
 check(await evaluate('sessionStorage.length===0 && Object.keys(localStorage).every(k=>["mco-theme","mco.sidebar.collapsed"].includes(k))'),'No PII in browser storage');
 check(await evaluate('fetch("/fixture-api",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"save",input:{}})}).then(r=>r.status)')===401,'Server rejects expired fixture session');
 await go();check(await evaluate('fetch("/fixture-api",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"save",input:{},csrf:"wrong"})}).then(r=>r.status)')===419,'CSRF rejection');
 check(missingAssets.length===0,'No missing CSS, JS or font resources');check(exceptions.length===0,'No JS runtime exceptions');check(requests.length===0,'No external requests');
 const result={browser:version.Browser,checks:checks.length,results:checks,exceptions,missingAssets,externalRequests:requests,screenshots:fs.readdirSync(out)};fs.writeFileSync(path.join(config.root,'tests/new-connection/applicant-prototype-browser-results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
}finally{if(ws)ws.close();child.kill();await pause(500);}})().catch(e=>{console.error(e.message);process.exitCode=1;});
