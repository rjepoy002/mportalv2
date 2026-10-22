// Real headless Chromium via built-in CDP/WebSocket; no packages or live services.
const fs = require('node:fs'), path = require('node:path'), {spawn} = require('node:child_process');
const config = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const localAssets = process.env.MCO_LOCAL_ASSETS_ACCEPTANCE === '1';
const output = path.join(config.root, 'tests/new-connection', localAssets ? 'local-assets-screenshots' : 'screenshots'); fs.mkdirSync(output, {recursive:true});
const chrome = process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--no-sandbox','--disable-gpu','--disable-software-rasterizer','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--user-data-dir='+path.join(config.fixture,'browser-profile'),'about:blank'], {windowsHide:true, stdio:['ignore','ignore','pipe']});
const checks=[], errors=[], blocked=[], networkFailures=[], missingAssets=[]; let ws, seq=0; const pending=new Map(), requests=new Map();
const pause = ms => new Promise(r=>setTimeout(r,ms));
function check(value,label){if(!value)throw Error(label);checks.push(label);}
function send(method,params={}){return new Promise((resolve,reject)=>{const id=++seq;const timer=setTimeout(()=>{pending.delete(id);reject(Error('CDP timeout: '+method));},15000);pending.set(id,{resolve,reject,timer});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);return result.result.value;}
async function wait(expression){for(let i=0;i<120;i++){try{if(await evaluate(expression))return;}catch{}await pause(100);}throw Error('Timeout: '+expression);}
async function go(route){await send('Page.navigate',{url:config.base+route});await wait('document.readyState === "complete"');}
async function submit(form,values,expected){await evaluate(`(()=>{const f=document.querySelector(${JSON.stringify(form)});for(const [name,value] of Object.entries(${JSON.stringify(values)})){const e=f.elements.namedItem(name);if(e.type==='checkbox')e.checked=!!value;else e.value=value;e.dispatchEvent(new Event('input',{bubbles:true}));}setTimeout(()=>f.requestSubmit(),0);})()`);await wait(expected);}
async function clear(){await send('Network.clearBrowserCookies');}
async function login(email='browser@example.invalid',password='correct123'){await go('/index.php');await submit('form',{email,password},'!!document.getElementById("signed-in")');}
async function screenshot(name){const metrics=await send('Page.getLayoutMetrics');const full=name.startsWith('new-connection');const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:full,...(full?{clip:{x:0,y:0,width:metrics.cssContentSize.width,height:Math.min(metrics.cssContentSize.height,10000),scale:1}}:{})});fs.writeFileSync(path.join(output,name),Buffer.from(shot.data,'base64'));}
(async()=>{
 try{
  const endpoint=await new Promise((resolve,reject)=>{let text='';const timer=setTimeout(()=>reject(Error('Browser startup timeout')),15000);browser.once('error',reject);browser.stderr.on('data',d=>{text+=d;const m=text.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(m){clearTimeout(timer);resolve(m[1]);}});});
  const port=new URL(endpoint).port;
  const version=await (await fetch('http://127.0.0.1:'+port+'/json/version')).json();
  const target=await (await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();
  ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
  ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);if(!p)return;clearTimeout(p.timer);pending.delete(m.id);if(m.error)p.reject(Error(m.error.message));else p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);else if(m.method==='Network.requestWillBeSent')requests.set(m.params.requestId,m.params.request.url);else if(m.method==='Network.responseReceived' && m.params.response.status>=400 && m.params.response.url.includes('/assets/'))missingAssets.push(m.params.response.url.split('?')[0]);else if(m.method==='Fetch.requestPaused'){const p=m.params;if(p.request.url.startsWith(config.base+'/'))send('Fetch.continueRequest',{requestId:p.requestId}).catch(()=>{});else send('Fetch.failRequest',{requestId:p.requestId,errorReason:'BlockedByClient'}).catch(()=>{});}else if(m.method==='Network.loadingFailed'){const raw=requests.get(m.params.requestId);if(raw && !raw.startsWith(config.base)){const u=new URL(raw);networkFailures.push({resource:u.origin+u.pathname,error:m.params.errorText});}}};
  await send('Page.enable');await send('Network.enable');await send('Runtime.enable');
  if(localAssets) await send('Fetch.enable',{patterns:[{urlPattern:'*',requestStage:'Request'}]});
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  await go('/index.php');
  check(await evaluate('!!document.querySelector("input[name=csrf_token]").value'),'Login renders CSRF in actual browser');
  await screenshot('login-desktop.png');
  if(localAssets){
   await evaluate('document.fonts.ready');
   check(await evaluate('document.fonts.check("16px bootstrap-icons") && getComputedStyle(document.querySelector(".bi"),"::before").fontFamily.includes("bootstrap-icons")'),'Local Bootstrap Icons font loaded');
  }
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  check(await evaluate('document.documentElement.scrollWidth <= innerWidth'),'Login mobile layout has no horizontal overflow');
  await screenshot('login-mobile.png');
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  let cookie=(await send('Network.getCookies',{urls:[config.base]})).cookies.find(x=>x.name==='PHPSESSID');
  check(cookie.httpOnly && cookie.sameSite==='Lax' && !cookie.secure,'Actual browser local HTTP cookie HttpOnly/Lax and non-Secure');
  const rejected=await evaluate(`fetch('index.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'email=browser%40example.invalid&password=correct123&csrf_token=bad'}).then(r=>r.status)`);
  check(rejected===419,'Browser invalid login CSRF rejected');
  await login(); check(true,'Browser password login with valid form CSRF');
  if(localAssets){
   for(const width of [1440,390,320]){
    await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});
    for(const dark of [false,true]){
     await go('/presentation.php?page=account-settings');
     if(!await evaluate('!!window.mcoTheme'))throw Error('Presentation fixture did not finish: '+await evaluate('document.body.innerText.slice(0,1600)'));
     await evaluate(`window.mcoTheme.applyTheme('${dark?'dark':'light'}')`);
     check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Dashboard/profile no overflow '+width+' '+dark);
     check(await evaluate('typeof Chart === "function"'),'Local Chart.js present '+width+' '+dark);
     check(await evaluate('getComputedStyle(document.getElementById("sidebar")).position === "fixed"'),'Fixed sidebar '+width+' '+dark);
     check(await evaluate('getComputedStyle(document.querySelector("main header")).position === "sticky"'),'Sticky header '+width+' '+dark);
     await evaluate('document.getElementById("profile-settings").scrollIntoView({block:"center"})');
     await screenshot(`profile-${width}-${dark?'dark':'light'}.png`);
     await evaluate('document.querySelector("[data-ebills-open]").click()');await wait('document.getElementById("ebills-dialog").open && document.querySelector("[data-ebills-status]").textContent.startsWith("Read and accept")');
     const geometry=await evaluate('(()=>{const d=document.getElementById("ebills-dialog"),r=d.getBoundingClientRect(),t=d.querySelector("[data-ebills-terms]");return {width:r.width,height:r.height,scroll:t.scrollHeight>t.clientHeight,bg:getComputedStyle(d).backgroundColor,focus:d.contains(document.activeElement)}})()');
     check(geometry.width<=width && geometry.height<=1000 && geometry.scroll && geometry.focus,'eBills modal fit, scrolling and focus '+width+' '+dark);
     await screenshot(`ebills-${width}-${dark?'dark':'light'}.png`);
     await evaluate('(()=>{const t=document.querySelector("[data-ebills-terms]");t.scrollTop=t.scrollHeight;t.dispatchEvent(new Event("scroll"));})()');await pause(100);
     await evaluate('(()=>{const c=document.querySelector("[data-ebills-consent]");c.checked=true;c.dispatchEvent(new Event("change"));document.querySelector("[data-ebills-next]").click()})()');
     await wait('!document.querySelector("[data-ebills-screen="+JSON.stringify("2")+"]").hidden');
     check(await evaluate('getComputedStyle(document.querySelector("[data-ebills-names]")).gridTemplateColumns.split(" ").length')===(width>=640?2:1),'eBills responsive personal fields '+width+' '+dark);
     await evaluate('document.querySelector("[data-ebills-cancel]").click()');
     await wait('document.activeElement.matches("[data-ebills-open]")');
     check(await evaluate('document.activeElement.matches("[data-ebills-open]")'),'eBills cancel restores focus '+width+' '+dark);
    }
   }
   await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
   await go('/presentation.php');
   await wait('!!Chart.getChart(document.getElementById("consumptionChart"))');
   await pause(1200); // Capture after the existing chart animation completes.
   check(true,'Local Chart.js renders synthetic consumption chart');await screenshot('dashboard-desktop.png');
   await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
   check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Dashboard chart mobile has no overflow');await screenshot('dashboard-mobile.png');
   await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  }
  await go('/new-connection.php');await wait('document.querySelectorAll(".nc-number").length === 7');
  check(await evaluate('!document.body.innerText.includes("Signed in (fixture")'),'Actual New Connection controller/view rendered');
  check(await evaluate('document.body.innerText.includes("handwritten signatures")'),'Payment visit and signature information visible');
  // Do not replace unavailable production CDN styles with test-only fake CSS.
  const styled=await evaluate('getComputedStyle(document.body).backgroundColor !== "rgba(0, 0, 0, 0)"');
  if(!styled)blocked.push('Utility styling did not load: complete responsive shell and visible light/dark styling cannot be accepted');
  if(localAssets)check(styled,'Local utility stylesheet styles the shell');
  for(const [width,height] of [[1440,1000],[390,844]]){
   await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<600});
   for(const dark of [false,true]){
    await evaluate(`window.mcoTheme.applyTheme(${JSON.stringify(dark?'dark':'light')})`);await pause(200);
    check(await evaluate('document.documentElement.scrollWidth <= innerWidth'),'New Connection no horizontal overflow '+width+' '+dark);
    const columns=await evaluate('getComputedStyle(document.querySelector(".nc-stages")).gridTemplateColumns.split(" ").length');
    check(columns===(width>=768?2:1),'New Connection responsive columns '+width+' '+dark);
    check(await evaluate('document.documentElement.classList.contains("dark")')===dark,'New Connection theme '+width+' '+dark);
    if(localAssets)check(await evaluate('getComputedStyle(document.body).backgroundColor')===(dark?'rgb(2, 6, 23)':'rgb(248, 250, 252)'),'Visible New Connection theme colors '+width+' '+dark);
    await screenshot(`new-connection-${width}-${dark?'dark':'light'}.png`);
   }
  }
  await evaluate('document.getElementById("openSidebar").click()');
  check(await evaluate('document.getElementById("openSidebar").getAttribute("aria-expanded")==="true"'),'Mobile sidebar opens');
  await evaluate('document.querySelector("[data-session-action=logout]").click()');
  check(await evaluate('document.getElementById("session-action-dialog").open'),'Logout confirmation opens');
  await evaluate('document.querySelector("[data-session-cancel]").click()');check(await evaluate('!document.getElementById("session-action-dialog").open'),'Logout cancel preserves session');
  await evaluate('document.querySelector("[data-session-action=logout]").click();document.querySelector("[data-session-confirm]").click()');
  await wait('location.pathname==="/index.php" && !!document.getElementById("email")');
  await go('/new-connection.php');check(await evaluate('location.pathname==="/index.php"'),'Logout removes protected access');
  for(const state of ['inactive','unverified','expired']){
   await clear();await send('Network.setCookie',{name:'PHPSESSID',value:config[state],url:config.base,httpOnly:true,sameSite:'Lax'});
   await go('/new-connection.php');
   check(await evaluate(state==='expired'?'location.pathname==="/index.php"':'document.body.innerText.includes("active account with a verified email")'), 'Browser denies '+state+' fixture session');
  }
  await clear();await go('/register.php');
  if(localAssets)await screenshot('registration-mobile.png');
  await submit('#registerForm',{first_name:'Browser',last_name:'Applicant',email:'browser-signup@example.invalid',password:'correct123',confirm_password:'correct123',terms:true},'!!document.getElementById("otpSection") && !document.getElementById("otpSection").hidden');
  const otp=fs.readFileSync(path.join(config.fixture,'fixture-otp'),'utf8');
  check(otp.length===6,'Registration delivered OTP only to synthetic transport');
  await evaluate(`(()=>{document.querySelectorAll('.otp-input').forEach((e,i)=>{e.value=${JSON.stringify(otp)}[i];e.dispatchEvent(new Event('input',{bubbles:true}));});setTimeout(()=>document.getElementById('otpForm').requestSubmit(),0);})()`);
  await wait('location.pathname==="/index.php"');check(true,'Actual registration form and OTP browser workflow');
  await login('browser-signup@example.invalid');await go('/new-connection.php');
  check(await evaluate('document.querySelectorAll(".nc-number").length===7'),'Newly registered user without a linked account accesses New Connection');
  await go('/forgot-password.php');
  await submit('#forgotPasswordForm',{email:'recovery@example.invalid'},'document.body.innerText.includes("If an account exists")');
  const reset=fs.readFileSync(path.join(config.fixture,'fixture-reset-token'),'utf8');
  check(reset.length===64,'Recovery sends reset link only to synthetic transport');
  await go('/reset-password.php?token='+reset);
  await submit('#resetPasswordForm',{password:'updated123',confirm_password:'updated123'},'location.pathname==="/index.php"');
  check(true,'Actual forgot/reset forms with valid CSRF and synthetic token');
  await login('browser@example.invalid','updated123');check(true,'Login after synthetic password reset');
  fs.writeFileSync(path.join(config.fixture,'nc-flag'),'0');await go('/new-connection.php');
  check(await evaluate('document.body.innerText.includes("Page unavailable")'),'Feature disabled denies browser access');
  fs.writeFileSync(path.join(config.fixture,'nc-flag'),'1');
  await clear();await go('/index.php');
  const statuses=await evaluate(`(async()=>{const token=document.querySelector('[name=csrf_token]').value;const results=[];for(let i=0;i<11;i++){const body=new URLSearchParams({csrf_token:token,email:'browser-limited@example.invalid',password:'incorrect'});results.push((await fetch('index.php',{method:'POST',body})).status);}return results;})()`);
  check(statuses[9]!==429 && statuses[10]===429,'Actual browser login rate limit after ten attempts, without reset/bypass');
  if(localAssets){check(missingAssets.length===0,'No missing local assets');check(networkFailures.length===0,'No external resource requests');check(errors.length===0,'No JavaScript runtime exceptions');check(blocked.length===0,'No styling blockers');}
  const result={browser:version.Browser,checks:checks.length,results:checks,blocked,networkFailures,missingAssets,runtimeExceptions:errors,screenshots:fs.readdirSync(output).filter(x=>x.endsWith('.png'))};
  fs.writeFileSync(path.join(config.root,'tests/new-connection',localAssets?'local-assets-browser-results.json':'browser-results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
 }finally{if(ws)ws.close();browser.kill();await pause(500);}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
