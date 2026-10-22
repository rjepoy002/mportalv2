// Reuse the CDP bootstrap/storage checks, then exercise actual rendered temporary controller views.
const fs=require('node:fs'),path=require('node:path');
let source=fs.readFileSync(path.join(__dirname,'auth-return-browser-test.js'),'utf8');
source='let fixtureStage="bootstrap";\n'+source;
source=source.replace("external.push('blocked')","external.push(new URL(p.request.url).origin+new URL(p.request.url).pathname)");
source=source.replace("p.request.url.startsWith(cfg.base+'/')","(p.request.url.startsWith(cfg.base+'/') || p.request.url===new URL(cfg.base).origin+'/favicon.ico')");
source=source.replace("'No external mail/provider/assets requests'","'No external mail/provider/assets requests: '+JSON.stringify(external)");
source=source.replace("throw Error('Browser evaluation failed');","throw Error('Browser evaluation failed at '+fixtureStage+': '+(r.exceptionDetails.exception?.description?.split('\\n')[0]||r.exceptionDetails.text));");
// A navigation can cancel a paused request before CDP replies; it remains canceled, never allowed externally.
for(const method of ['continueRequest','failRequest'])source=source.replace(new RegExp("send\\('Fetch\\."+method+"',([^;]+)\\);"),"send('Fetch."+method+"',$1).catch(e=>{if(!e.message.includes('Invalid InterceptionId'))exceptions.push('Interception command failure');});");
source=source.replace('auth-return-browser-results.json','apache-navigation-browser-results.json');
source=source.replace('visualAcceptance:false','visualAcceptance:false,visualReview:"Limited landing screenshot review recorded separately in the report"');
source=source.replace("JSON.stringify(cfg.base)+' && document.readyState", "JSON.stringify(new URL(cfg.base).origin)+' && document.readyState");
for(const route of ['session','begin','login','module'])source=source.replaceAll("'/"+route+"'","'/mportalv2/"+route+"'");
const extra=fs.readFileSync(path.join(__dirname,'apache-navigation-browser-journeys.js'),'utf8');
if(!source.includes(" const cookies="))throw Error('Browser insertion hook changed');
source=source.replace(' const cookies=',extra+'\n const cookies=');
new Function('require','process','__dirname',source)(require,process,__dirname);
