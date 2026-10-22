// Same 06F browser journey; separate current-policy SQL fixture and evidence filename.
const fs=require('node:fs'), path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'auth-return-browser-test.js'),'utf8');
if(!source.includes('auth-return-browser-results.json'))throw Error('Browser evidence hook changed');
new Function('require','process',source.replace('auth-return-browser-results.json','localhost-auth-navigation-browser-results.json'))(require,process);
