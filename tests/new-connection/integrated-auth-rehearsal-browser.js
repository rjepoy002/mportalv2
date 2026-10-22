// Reuse 06F's Chrome journey against the integrated SQL-backed fixture, separate result artifact.
const fs = require('node:fs');
const source = fs.readFileSync(require('node:path').join(__dirname, 'auth-return-browser-test.js'), 'utf8');
if (!source.includes('auth-return-browser-results.json')) throw Error('Reviewed browser artifact hook changed');
new Function('require', 'process', source.replace('auth-return-browser-results.json', 'integrated-auth-rehearsal-browser-results.json'))(require, process);
