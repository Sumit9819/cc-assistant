// Runs the real ProjectStructure against this repo with a minimal vscode shim,
// so the map is inspected as the model would receive it.
const Module = require('node:module');
const path = require('node:path');
const fs = require('node:fs');
const root = process.cwd();

function walk(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (/^(\.git|node_modules|dist|out|build|coverage|\.vscode)$/.test(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, out);
    else out.push(full);
  }
  return out;
}
const files = walk(root);
const uri = (fsPath) => ({
  fsPath,
  path: '/' + fsPath.replace(/\\/g, '/'),
  toString: () => 'file:///' + fsPath.replace(/\\/g, '/')
});

const originalLoad = Module._load;
Module._load = function (request, parent, isMain) {
  if (request === 'vscode') {
    return {
      workspace: {
        getConfiguration: () => ({ get: (_k, f) => f }),
        findFiles: async () => files.map(uri),
        fs: {
          stat: async (u) => ({ type: 1, size: fs.statSync(u.fsPath).size }),
          readFile: async (u) => new Uint8Array(fs.readFileSync(u.fsPath))
        },
        workspaceFolders: [{ uri: uri(root), name: path.basename(root) }],
        getWorkspaceFolder: () => ({ uri: uri(root), name: path.basename(root) })
      },
      FileType: { File: 1, Directory: 2, SymbolicLink: 64 }
    };
  }
  return originalLoad.call(this, request, parent, isMain);
};

const { ProjectStructure } = require(path.join(root, 'dist/agent/projectStructure.js'));
Module._load = originalLoad;

(async () => {
  const structure = new ProjectStructure();
  const overview = await structure.renderOverview(3000);
  console.log(overview);
  console.log('\n=== measurements ===');
  console.log('overview chars :', overview.length, '(~' + Math.round(overview.length / 4) + ' tokens)');
  const map = await structure.build();
  console.log('files parsed   :', map.files.length);
  const hub = map.files[0];
  console.log('most depended  :', hub.path, '<-', hub.importedBy, 'importers');
})();
