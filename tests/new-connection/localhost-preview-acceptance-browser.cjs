// Actual copied preview route + SELECT-only synthetic users; only actual localhost static assets accessed.
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net'),{spawn,spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'../..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'mco-preview-render-')),checks=[],errors=[],blocked=[];
let php,chrome,ws,id=0;const pending=new Map(),pause=ms=>new Promise(r=>setTimeout(r,ms));
function check(ok,label){if(!ok)throw Error(label);checks.push(label);}
function send(method,params={}){return new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error('Synthetic preview evaluation failed');return r.result.value;}
(async()=>{try{
 const render=spawnSync('C:/xampp/php/php.exe',[path.join(__dirname,'localhost-preview-apache-test.php')],{env:{...process.env,MCO_PREVIEW_TEST_RENDER:path.join(temp,'index.html')},encoding:'utf8',windowsHide:true});check(render.status===0,'Copied-route authorization/security suite passed');console.log(render.stdout.trim());
 let html=fs.readFileSync(path.join(temp,'index.html'),'utf8');html=html.replace('<head>','<head><base href="http://localhost/mportalv2/">');fs.writeFileSync(path.join(temp,'index.html'),html);
 const port=await new Promise(r=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const n=s.address().port;s.close(()=>r(n));});});const base='http://127.0.0.1:'+port;
 php=spawn('C:/xampp/php/php.exe',['-S','127.0.0.1:'+port,'-t',temp],{windowsHide:true,stdio:'ignore'});for(let n=0;n<50;n++){try{const ready=await fetch(base+'/index.html');await ready.arrayBuffer();break;}catch{}await pause(50);}
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+path.join(temp,'chrome'),'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
 const endpoint=await new Promise((resolve,reject)=>{let all='';const t=setTimeout(()=>reject(Error('Chrome unavailable')),15000);chrome.stderr.on('data',b=>{all+=b;const m=all.match(/DevTools listening on (ws:\/\/\S+)/);if(m){clearTimeout(t);resolve(m[1]);}});});
 const origin='http://127.0.0.1:'+new URL(endpoint).port,page=await(await fetch(origin+'/json/new?about:blank',{method:'PUT'})).json();ws=new WebSocket(page.webSocketDebuggerUrl);await new Promise((r,j)=>{ws.onopen=r;ws.onerror=j;});
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}return;}if(m.method==='Runtime.exceptionThrown')errors.push('runtime exception');if(m.method==='Fetch.requestPaused'){const p=m.params,allowed=p.request.url===base+'/index.html'||p.request.url===base+'/favicon.ico'||p.request.url.startsWith('http://localhost/mportalv2/assets/');if(!allowed)blocked.push(new URL(p.request.url).pathname);send(allowed?'Fetch.continueRequest':'Fetch.failRequest',allowed?{requestId:p.requestId}:{requestId:p.requestId,errorReason:'BlockedByClient'}).catch(()=>{});}};
 await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});await send('Page.navigate',{url:base+'/index.html'});for(let n=0;n<100;n++){if(await evaluate('document.readyState==="complete" && !!document.getElementById("themeToggle")'))break;await pause(50);}
 check(await evaluate('document.querySelectorAll(".nc-stages > li").length===7 && document.body.innerText.includes("Development Preview")'),'Seven stages and development notice rendered');
 check(await evaluate(`(()=>{const a=document.querySelector('nav a[href="new-connection-preview.php"]');return !!a&&a.getAttribute('aria-current')==='page'&&a.innerText.includes('Preview');})()`),'Fixed preview link and active state');
 check(await evaluate(`!document.querySelector('input[type=file],input[name=first_name],form[action="new-connection-preview.php"]')`),'No applicant forms or uploads');
 for(const [width,mode] of [[1440,'light'],[1440,'dark'],[390,'light'],[390,'dark'],[320,'light']]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<1024});await pause(200);await evaluate(`if(document.documentElement.classList.contains('dark')!==${mode==='dark'})document.getElementById('themeToggle').click()`);await pause(100);
  check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No horizontal overflow at '+width+'px '+mode);
  if(width!==320){const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(__dirname,'localhost-preview-acceptance-'+(width===1440?'desktop':'mobile')+'-'+mode+'.png'),Buffer.from(shot.data,'base64'));}
 }
 await evaluate('document.getElementById("openSidebar").click()');await pause(200);check(await evaluate('document.querySelector("nav a[aria-current=page]").getBoundingClientRect().left>=0'),'Mobile navigation opens with active preview');
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await evaluate('document.activeElement.blur()');
 await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
 await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
 check(await evaluate('document.activeElement!==document.body && document.activeElement.matches(":focus-visible")'),'Keyboard Tab reaches a focus-visible control');
 check(await evaluate(`JSON.stringify([...document.querySelectorAll('.nc-stages h2,.nc-stages h3')].map(e=>e.innerText)).includes('Installation Scheduling')`),'Stage headings readable in document order');
 check(errors.length===0&&blocked.length===0,'No runtime exceptions or application/customer requests');
 fs.writeFileSync(path.join(__dirname,'localhost-preview-acceptance-browser-results.json'),JSON.stringify({checks:checks.length,results:checks,scope:'Copied actual preview route with SELECT-only synthetic identity; actual localhost assets, no live authenticated customer access',errors,blocked},null,2));console.log('Preview browser passed ('+checks.length+' checks).');
 }finally{if(ws)await Promise.race([new Promise(r=>{ws.onclose=r;ws.close();}),pause(300)]);if(chrome)chrome.kill();if(php)php.kill();await pause(800);const resolved=fs.realpathSync(temp);if(path.dirname(resolved)!==fs.realpathSync(os.tmpdir())||!/^mco-preview-render-[a-zA-Z0-9_-]+$/.test(path.basename(resolved)))throw Error('Unsafe fixture cleanup');fs.rmSync(resolved,{recursive:true,force:true});}})().catch(e=>{console.error(e.message);process.exitCode=1;});

