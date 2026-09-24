const fs = require('fs');
const src = fs.readFileSync('src/views/sidebarWebview.ts', 'utf8');

// Reproduce getHtml(): the head/body template interpolates ${nonce} and ${this.getScript()}.
const rawStart = src.indexOf('return String.raw`');
const rawBody = src.slice(rawStart + 'return String.raw`'.length, src.indexOf('`;\n  }\n}', rawStart));
const script = String.raw({ raw: [rawBody] });

const htmlStart = src.indexOf('return `<!DOCTYPE html>');
const htmlBody = src.slice(htmlStart + 'return `'.length, src.indexOf('`;\n  }\n\n  /**', htmlStart));
const nonce = 'NONCE';
// A function replacement, so $-patterns inside the script are not substituted.
const html = eval('`' + htmlBody.replace('${this.getScript()}', '__SCRIPT__') + '`').replace('__SCRIPT__', () => script);

const checks = [
  ['doctype', html.startsWith('<!DOCTYPE html>')],
  ['closes html', html.trimEnd().endsWith('</html>')],
  ['one script tag', (html.match(/<script /g) || []).length === 1],
  ['script closed', (html.match(/<\/script>/g) || []).length === 1],
  ['CSP intact', html.includes("default-src 'none'; style-src 'nonce-NONCE'; script-src 'nonce-NONCE'")],
  ['no remote src', !/src=["']https?:/.test(html)],
  ['no innerHTML', !html.includes('.innerHTML')],
  ['mention regex intact', html.includes('/(?:^|\\s)@([^\\s@]*)$/')],
  ['markdown renderer present', html.includes('function renderMarkdown')],
  ['answerDelta handled', html.includes("progress.type === 'answerDelta'")],
  ['answerReset handled', html.includes("progress.type === 'answerReset'")],
  ['copy button present', html.includes("copyText(entry.raw, copyAll, 'Copy')")]
];

let failed = 0;
for (const [name, ok] of checks) {
  if (!ok) failed += 1;
  console.log((ok ? 'PASS' : 'FAIL') + '  ' + name);
}
console.log('\nrendered html size: ' + html.length.toLocaleString() + ' chars');
process.exitCode = failed ? 1 : 0;
