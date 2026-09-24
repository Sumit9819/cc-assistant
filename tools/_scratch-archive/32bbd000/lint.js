// Run the EXACT csslint WordPress ships to its Customizer code editor.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const WP = 'c:/Users/sumit/Local Sites/plugintesting/app/public/wp-includes/js/codemirror/csslint.js';
const sandbox = { window: {}, self: {}, console };
sandbox.window = sandbox;
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(WP, 'utf8'), sandbox);

const CSSLint = sandbox.CSSLint || sandbox.window.CSSLint;
if (!CSSLint) { console.error('CSSLint not found in bundle'); process.exit(1); }

const file = process.argv[2];
const css = fs.readFileSync(file, 'utf8');
const lines = css.split('\n');

// WordPress enables these rules for the Customizer CSS editor.
const res = CSSLint.verify(css);
const errors = res.messages.filter(m => m.type === 'error');
const warnings = res.messages.filter(m => m.type === 'warning');

console.log(`file: ${path.basename(file)}`);
console.log(`ERRORS (these block Publish): ${errors.length}`);
errors.forEach(m => {
  console.log(`  line ${m.line}, col ${m.col}: ${m.message}`);
  console.log(`     >> ${(lines[m.line - 1] || '').trim()}`);
});
console.log(`\nwarnings (do NOT block Publish): ${warnings.length}`);
const byRule = {};
warnings.forEach(m => { byRule[m.rule?.id || m.message] = (byRule[m.rule?.id || m.message] || 0) + 1; });
Object.entries(byRule).sort((a,b)=>b[1]-a[1]).slice(0,8).forEach(([k,v]) => console.log(`  ${v.toString().padStart(3)}x  ${k}`));
