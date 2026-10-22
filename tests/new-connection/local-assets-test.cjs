// Read-only artifact contract. No database, browser or package installation.
const fs = require('node:fs'), path = require('node:path'), crypto = require('node:crypto');
const root=path.resolve(__dirname,'../..');let checks=0;
function check(ok,label){checks++;if(!ok)throw Error(label);}
const read=file=>fs.readFileSync(path.join(root,file),'utf8');
const walk=dir=>fs.readdirSync(path.join(root,dir),{withFileTypes:true}).flatMap(e=>e.isDirectory()?walk(dir+'/'+e.name):[dir+'/'+e.name]);
for(const file of walk('app/views').filter(f=>f.endsWith('.php'))){
 const source=read(file);
 check(!/cdn\.tailwindcss\.com|tailwind\.config|fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net/.test(source),'External presentation dependency in '+file);
 for(const match of source.matchAll(/(?:src|href)="(assets\/[^"?]+)/g))check(fs.existsSync(path.join(root,match[1])),'Missing referenced asset '+match[1]);
}
const css=read('assets/css/portal-utilities.css');
for(const selector of ['.fixed','.sticky','.grid','.hidden','.bg-paleco-700','.shadow-card','.lg\\:hidden','.sm\\:grid-cols-2','.dark\\:bg-slate-900','max-h-\\[90dvh\\]'])check(css.includes(selector),'Missing generated utility '+selector);
for(const file of ['assets/css/login.css','assets/css/register.css','assets/css/reset-password.css','assets/css/forgot-password.css'])check(read(file).includes('system-ui'),'System font stack '+file);
check(!/@import\s|https?:\/\//.test(css.replace(/\/\*.*?\*\//gs,'')),'Generated CSS must not fetch external resources');
for(const file of ['bootstrap-icons.woff','bootstrap-icons.woff2'])check(fs.statSync(path.join(root,'assets/vendor/bootstrap-icons/fonts',file)).size>1000,'Missing icon font '+file);
const iconCSS=read('assets/vendor/bootstrap-icons/bootstrap-icons.min.css');
const iconNames=new Set();for(const file of [...walk('app/views'),...walk('assets/js')])for(const match of read(file).matchAll(/\bbi-([a-z0-9-]+)/g))iconNames.add(match[1]);
for(const name of iconNames)check(iconCSS.includes('.bi-'+name+'::before'),'Missing Bootstrap icon '+name);
check(read('config/new-connection.php').includes("getenv('MCO_NEW_CONNECTION_ENABLED') === '1'"),'Feature flag default changed');
check(fs.existsSync(path.join(root,'package-lock.json')),'Reproducible lockfile missing');
console.log(JSON.stringify({ok:true,checks,icons:[...iconNames],cssBytes:Buffer.byteLength(css),cssSha256:crypto.createHash('sha256').update(css).digest('hex')}));
