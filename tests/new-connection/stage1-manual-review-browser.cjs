// Actual copied preview route + SELECT-only synthetic users; only actual localhost static assets accessed.
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net'),{spawn,spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'../..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'mco-preview-render-')),checks=[],errors=[],blocked=[];
let php,chrome,ws,id=0,output="";const pending=new Map(),pause=ms=>new Promise(r=>setTimeout(r,ms));
function check(ok,label){if(!ok)throw Error(label);checks.push(label);}
function send(method,params={}){return new Promise((resolve,reject)=>{const n=++id;pending.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error('Synthetic preview evaluation failed');return r.result.value;}
(async()=>{try{
 php=spawn('C:/xampp/php/php.exe',[path.join(__dirname,'stage1-manual-review-launcher.php'),'--start'],{windowsHide:true,stdio:['pipe','pipe','pipe']});output='';php.stdout.on('data',b=>output+=b);php.stderr.on('data',b=>errors.push(b.toString()));
 for(let n=0;n<200&&!output.includes('Type q');n++)await pause(100);
 const urls={};for(const m of output.matchAll(/^(active|inactive|unverified|expired|anonymous): (http:\/\/\S+)/gm))urls[m[1]]=m[2];
 check(!!urls.active,'Manual Apache launcher ready');const activeUrl=urls.active,base=new URL(activeUrl).origin;
 for(const [kind,status] of [['active',200],['inactive',403],['unverified',403],['expired',302],['anonymous',302]]){const r=await fetch(urls[kind],{redirect:'manual'});await r.arrayBuffer();check(r.status===status,'Apache session '+kind);}
 for(const method of ['POST','PUT','DELETE']){const r=await fetch(activeUrl,{method});await r.arrayBuffer();check(r.status===405,'Apache rejects '+method);}
 const secret=await fetch(base+'/private/context.php');await secret.arrayBuffer();check(secret.status===404,'Private configuration outside document root');
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+path.join(temp,'chrome'),'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
 const endpoint=await new Promise((resolve,reject)=>{let all='';const t=setTimeout(()=>reject(Error('Chrome unavailable')),15000);chrome.stderr.on('data',b=>{all+=b;const m=all.match(/DevTools listening on (ws:\/\/\S+)/);if(m){clearTimeout(t);resolve(m[1]);}});});
 const origin='http://127.0.0.1:'+new URL(endpoint).port,page=await(await fetch(origin+'/json/new?about:blank',{method:'PUT'})).json();ws=new WebSocket(page.webSocketDebuggerUrl);await new Promise((r,j)=>{ws.onopen=r;ws.onerror=j;});
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}return;}if(m.method==='Runtime.exceptionThrown')errors.push('runtime exception');if(m.method==='Fetch.requestPaused'){const p=m.params,allowed=p.request.url===activeUrl||p.request.url===base+'/favicon.ico'||p.request.url.startsWith(base+'/assets/');if(!allowed)blocked.push(new URL(p.request.url).pathname);send(allowed?'Fetch.continueRequest':'Fetch.failRequest',allowed?{requestId:p.requestId}:{requestId:p.requestId,errorReason:'BlockedByClient'}).catch(()=>{});}};
 await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});await send('Page.navigate',{url:activeUrl});for(let n=0;n<100;n++){if(await evaluate('document.readyState==="complete" && !!document.getElementById("themeToggle")'))break;await pause(50);}
 check(await evaluate('document.querySelectorAll("[data-step]" ).length===5 && document.body.innerText.includes("Read-Only Development Display" )'),'Five display-only sections');
 check(await evaluate('!document.querySelector("form,input,select,textarea" )'),'No editable fields or forms');
 check(await evaluate(`!document.querySelector('input[type=file],input[name=first_name],form[action="new-connection-preview.php"]')`),'No applicant forms or uploads');
 for(const [width,mode] of [[1440,'light'],[1440,'dark'],[390,'light'],[390,'dark'],[320,'light']]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<1024});await pause(200);await evaluate(`if(document.documentElement.classList.contains('dark')!==${mode==='dark'})document.getElementById('themeToggle').click()`);await pause(100);
  check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No horizontal overflow at '+width+'px '+mode);
  if(width!==320){const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(__dirname,'stage1-manual-review-'+(width===1440?'desktop':'mobile')+'-'+mode+'.png'),Buffer.from(shot.data,'base64'));}
 }
 await evaluate('document.getElementById("openSidebar" ).click()');check(await evaluate('document.getElementById("sidebar" ).getBoundingClientRect().left>=0'),'Mobile sidebar opens');
 check(errors.length===0&&blocked.length===0,'No runtime exceptions or application/customer requests');
 fs.writeFileSync(path.join(__dirname,'stage1-manual-review-browser-results.json'),JSON.stringify({checks:checks.length,results:checks,scope:'Live temporary Apache navigation; attested disposable SQL identities; no actual port-80 customer access',errors,blocked},null,2));console.log('Preview browser passed ('+checks.length+' checks).');
 }finally{if(ws)await Promise.race([new Promise(r=>{ws.onclose=r;ws.close();}),pause(300)]);if(chrome)chrome.kill();if(php){php.stdin.end('q\n');await Promise.race([new Promise(r=>php.on('exit',r)),pause(5000)]);check(output.includes('Disposable review schema removed.'),'Manual Apache cleanup');}await pause(800);const resolved=fs.realpathSync(temp);if(path.dirname(resolved)!==fs.realpathSync(os.tmpdir())||!/^mco-preview-render-[a-zA-Z0-9_-]+$/.test(path.basename(resolved)))throw Error('Unsafe fixture cleanup');fs.rmSync(resolved,{recursive:true,force:true});}})().catch(e=>{console.error(e.message);process.exitCode=1;});
