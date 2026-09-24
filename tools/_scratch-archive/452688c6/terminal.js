const Module = require('node:module');
const path = require('node:path');
const fs = require('node:fs');
const root = process.cwd();

const uri = (p) => ({ fsPath: p, path: '/' + p.replace(/\\/g, '/'), scheme: 'file', toString: () => 'file:///' + p });
const joinPath = (base, ...parts) => uri(path.join(base.fsPath, ...parts));

const originalLoad = Module._load;
Module._load = function (request, parent, isMain) {
  if (request === 'vscode') {
    return {
      env: { appRoot: 'C:/Users/sumit/AppData/Local/Programs/Microsoft VS Code/08d4889f9e/resources/app' },
      Uri: { joinPath },
      workspace: {
        getConfiguration: () => ({ get: (_k, f) => f }),
        workspaceFolders: [{ uri: uri(root), name: 'cf-ai-assistant' }],
        fs: {
          stat: async (u) => { fs.statSync(u.fsPath); return { type: 1, size: 1 }; },
          readFile: async (u) => new Uint8Array(fs.readFileSync(u.fsPath))
        }
      }
    };
  }
  return originalLoad.call(this, request, parent, isMain);
};

const { findRipgrep, runRipgrep, parseRipgrepOutput } = require(path.join(root, 'dist/agent/tools/ripgrep.js'));
const { ProjectCommands } = require(path.join(root, 'dist/agent/projectCommands.js'));
Module._load = originalLoad;

(async () => {
  console.log('=== ripgrep ===');
  console.log('binary:', findRipgrep() || 'NOT FOUND');

  // Colons inside the matched line must survive parsing.
  const parsed = parseRipgrepOutput('src/a.ts:12:5:const url = "http://x:8080";\n', 10);
  console.log('parse check:', JSON.stringify(parsed[0]));

  const hits = await runRipgrep({
    query: 'renderCorePrinciples',
    isRegex: false, caseSensitive: false, maxResults: 10, cwd: root
  });
  console.log('live search hits:', hits ? hits.length : 'fallback (undefined)');
  for (const hit of (hits || []).slice(0, 4)) {
    console.log('  ' + hit.path + ':' + hit.line + ':' + hit.column + ': ' + hit.preview.slice(0, 60));
  }

  console.log('\n=== project commands ===');
  const commands = new ProjectCommands();
  console.log(await commands.detect());
  console.log('\nprompt line:', await commands.describe());
})();
