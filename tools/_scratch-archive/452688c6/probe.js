const fs = require('fs');
const src = fs.readFileSync(process.argv[2], 'utf8');

const start = src.indexOf('return String.raw`');
const body = src.slice(start + 'return String.raw`'.length, src.indexOf('`;\n  }\n}', start));
const script = String.raw({ raw: [body] });

console.log('delivered mention regex :', (script.match(/const match = \/.*?\/\.exec\(beforeCursor\)/) || [])[0]);
console.log('delivered newline escape:', JSON.stringify((script.match(/'\\n\\nAttached: '/) || [])[0]));
console.log('backslashes preserved   :', (script.match(/\\/g) || []).length);

// The script must parse, and its markdown renderer must behave.
const fn = new Function('window', 'document', 'navigator', 'acquireVsCodeApi', 'requestAnimationFrame', script);
console.log('script parses           : yes');

// Exercise renderMarkdown against a minimal DOM.
const made = [];
function element(tag) {
  const node = {
    tag, className: '', textContent: '', children: [], attrs: {},
    appendChild(child) { this.children.push(child); return child; },
    append(...kids) { this.children.push(...kids); },
    replaceChildren(...kids) { this.children = kids; },
    addEventListener() {},
    set href(v) { this.attrs.href = v; },
    get href() { return this.attrs.href; }
  };
  made.push(node);
  return node;
}
function text(value) { return { tag: '#text', textContent: value, children: [] }; }

function flatten(node) {
  const label = node.tag === '#text' ? 'text:' + node.textContent : node.tag + (node.className ? '.' + node.className : '');
  return node.children.length ? label + '[' + node.children.map(flatten).join(' ') + ']' : label;
}

const doc = {
  createElement: element,
  createTextNode: text,
  getElementById: () => element('div'),
  querySelectorAll: () => [],
  addEventListener: () => {}
};
const frames = [];
const win = { addEventListener: () => {} };
const api = () => ({ postMessage: () => {} });

// Capture renderMarkdown by re-evaluating just the helper section.
const helperStart = script.indexOf('const BACKTICK');
const helperEnd = script.indexOf('function createMessage');
const helpers = script.slice(helperStart, helperEnd) + '\nreturn { renderMarkdown, appendInline, highlightInto };';
const build = new Function('document', 'navigator', 'requestAnimationFrame', 'scrollToBottom', helpers);
const { renderMarkdown } = build(doc, { clipboard: { writeText() {} } }, (cb) => frames.push(cb), () => {});

const target = element('div');
renderMarkdown(target, [
  '# Title',
  '',
  'Some **bold** and `inline` text with a [link](https://example.com).',
  '',
  '- first',
  '- second',
  '',
  '```ts',
  'const x = "hi"; // note',
  '```'
].join('\n'));
console.log('rendered tree           :', flatten(target));
