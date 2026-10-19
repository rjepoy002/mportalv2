// Real profile markup and JS, synthetic profile only; no portal HTTP writes.
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawnSync} = require('node:child_process');
const {pathToFileURL} = require('node:url');
const root = path.resolve(__dirname, '..');
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'paleco-profile-mobile-'));
try {
    const template = fs.readFileSync(path.join(root, 'app/views/pages/dashboard.php'), 'utf8');
    const start = template.indexOf('<section id="profile-settings"');
    const section = template.slice(start, template.indexOf('</section>', start) + 10);
    const rendered = spawnSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [], {
        cwd: root, encoding: 'utf8', windowsHide: true,
        input: `<?php $_SESSION=[]; $csrf='synthetic'; $portalProfile=['first_name'=>'Ana','last_name'=>'Cruz','contact_number'=>'09123456789']; function dashboardEscape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');} ?>${section}`
    });
    if (rendered.status !== 0) throw Error(rendered.stderr);
    const application = fs.readFileSync(path.join(root, 'assets/js/profile-settings.js'), 'utf8');
    const script = `
    const checks=[];const check=(ok,name)=>{if(!ok)throw Error(name);checks.push(name);};
    try {
        const form=document.querySelector('[data-profile-settings]'),field=form.querySelector('[data-profile-contact]'),error=document.getElementById('profile-contact-error');
        let submitted=false,blocked=false;
        form.addEventListener('submit',event=>{submitted=true;blocked=event.defaultPrevented;event.preventDefault();});
        check(field.type==='tel'&&field.inputMode==='tel'&&field.autocomplete==='tel','Mobile telephone keyboard/autofill');
        check(field.maxLength===-1&&!field.hasAttribute('pattern'),'International input is not truncated or blocked by old pattern');
        check(field.value==='09123456789','Existing stored number unchanged on load');
        field.focus();field.value='+63 912-345-6789';field.dispatchEvent(new Event('input'));
        check(field.value==='+63 912-345-6789','Typing/paste preserves separators');
        field.dispatchEvent(new FocusEvent('blur'));check(field.value==='09123456789','Blur event normalizes');
        field.value='639123456789';field.dispatchEvent(new Event('input'));form.requestSubmit();
        check(submitted&&!blocked&&field.value==='09123456789','Native submit/Enter path normalizes before posting');
        submitted=false;field.value='08123456789';field.dispatchEvent(new Event('input'));form.requestSubmit();
        check(submitted&&blocked&&!error.hidden&&field.getAttribute('aria-invalid')==='true','Invalid submit shows inline error and blocks post');
        check(document.activeElement===field,'Invalid submit focuses contact');
        field.value='9123456789';field.dispatchEvent(new Event('input'));submitted=false;form.requestSubmit();
        check(submitted&&!blocked&&field.value==='09123456789'&&error.hidden,'Correction clears stale validity');
        field.value='';field.dispatchEvent(new Event('input'));submitted=false;form.requestSubmit();
        check(submitted&&!blocked&&field.value==='','Optional empty contact preserved');
        parent.document.getElementById('result').textContent=JSON.stringify({ok:true,width:innerWidth,checks});
    } catch(e) {parent.document.getElementById('result').textContent=JSON.stringify({ok:false,error:e.message});}
    `;
    const fixture = path.join(directory, 'fixture.html');
    const child = `<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">${rendered.stdout}<script>${application}\n${script}</script>`;
    const chrome = process.env.CHROME_BINARY || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
    for (const width of [390, 768]) {
        // An iframe gives exact narrow viewports despite Chrome's minimum window width.
        fs.writeFileSync(fixture, `<!doctype html><pre id="result"></pre><iframe style="width:${width}px;height:844px;border:0" srcdoc="${child.replaceAll('&','&amp;').replaceAll('"','&quot;')}"></iframe>`);
        const browser = spawnSync(chrome, ['--headless=new','--no-sandbox','--disable-gpu','--disable-software-rasterizer','--no-first-run','--no-default-browser-check','--user-data-dir='+path.join(directory,'profile-'+width),'--window-size='+width+',844','--virtual-time-budget=2000','--dump-dom',pathToFileURL(fixture).href], {encoding:'utf8',timeout:30000,maxBuffer:2*1024*1024,windowsHide:true});
        const result = browser.stdout?.match(/<pre id="result">([^<]+)<\/pre>/);
        if (!result) throw Error('Browser unavailable: '+(browser.error?.message || browser.stderr?.slice(-600)));
        const parsed = JSON.parse(result[1].replaceAll('&quot;', '"'));
        console.log(JSON.stringify(parsed));
        if (!parsed.ok) process.exitCode=1;
    }
} finally {
    if (path.dirname(path.resolve(directory)) !== path.resolve(os.tmpdir()) || !path.basename(directory).startsWith('paleco-profile-mobile-')) throw Error('Unexpected cleanup path');
    fs.rmSync(directory, {recursive:true,force:true,maxRetries:3,retryDelay:100});
}
