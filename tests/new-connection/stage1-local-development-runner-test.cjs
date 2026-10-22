// Exercises the explicit manual mode, never real portal routes or configuration.
const {spawn}=require('node:child_process');
const path=require('node:path');
const runner=path.join(__dirname,'stage1-local-development-runner.php');
const pause=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const child=spawn('C:/xampp/php/php.exe',[runner,'--start'],{windowsHide:true,stdio:['pipe','pipe','pipe']});
 let output='',error='';child.stdout.on('data',b=>output+=b);child.stderr.on('data',b=>error+=b);
 const done=new Promise(resolve=>child.on('exit',resolve));
 let base;
 try {
  for(let i=0;i<200;i++){const m=output.match(/Preview URL: (http:\/\/127\.0\.0\.1:\d+)\/login/);if(m){base=m[1];break;}await pause(100);}
  if(!base)throw Error('No loopback preview URL');
  for(const [route,status] of [['/login',200],['/private/fixture.json',404],['/stage1-http-router.php',404],['/app',401]]){
   const r=await fetch(base+route,{redirect:'manual'});const text=await r.text();if(r.status!==status)throw Error('Unexpected status '+route);
   if(route==='/login'&&!text.includes('Synthetic test sign-in'))throw Error('Synthetic login missing');
  }
  const status=await new Promise((resolve,reject)=>{require('node:http').get(base+'/login',{headers:{Host:'public.example.invalid'}},r=>{r.resume();r.on('end',()=>resolve(r.statusCode));}).on('error',reject);});if(status!==403)throw Error('Foreign Host denial status unexpected: '+status);
 } finally {child.stdin.end('q\n');}
 const code=await done;if(code!==0||!output.includes('Disposable HTTP schema removed.')||error)throw Error('Runner cleanup failed');
 let closed=false;try{const r=await fetch(base+'/login');await r.arrayBuffer();}catch{closed=true;}if(!closed)throw Error('Server remains running');
 console.log('Manual runner passed: startup, loopback URL, synthetic login, private-file denial, foreign Host denial, anonymous denial, graceful shutdown and schema cleanup.');
})().catch(e=>{console.error(e.message);process.exitCode=1;});
