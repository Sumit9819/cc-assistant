const Module = require('node:module');
const path = require('node:path');
const root = process.cwd();
const scratch = path.dirname(process.argv[1]);

const originalLoad = Module._load;
Module._load = function (request, parent, isMain) {
  if (request === 'vscode') {
    return {
      workspace: {
        getConfiguration: () => ({
          get: (key, fallback) => {
            if (key === 'mcpServers') {
              return {
                mock: { command: process.execPath, args: [path.join(scratch, 'mock-mcp-server.cjs')] },
                broken: { command: 'definitely-not-a-real-binary-xyz', args: [] },
                off: { command: process.execPath, args: [], enabled: false }
              };
            }
            return fallback;
          }
        }),
        workspaceFolders: [{ uri: { fsPath: root } }],
        isTrusted: true
      }
    };
  }
  return originalLoad.call(this, request, parent, isMain);
};

const { McpManager } = require(path.join(root, 'dist/services/mcpClient.js'));
const { McpTools, toolName } = require(path.join(root, 'dist/agent/tools/mcpTools.js'));
Module._load = originalLoad;

(async () => {
  const manager = new McpManager();
  const outcome = await manager.reload();
  console.log('started:', outcome.started, '| failed:', outcome.failed.length);
  console.log('failure reported:', outcome.failed[0] ? outcome.failed[0].slice(0, 70) : 'none');

  const tools = new McpTools(manager);
  console.log('\ndiscovered tools:');
  for (const definition of tools.definitions) {
    console.log('  ' + definition.name + '  readOnly=' + definition.readOnly);
    console.log('    ' + definition.description);
  }

  const context = { mode: 'auto', onOutput: () => {} };
  const echoName = toolName('mock', 'echo');
  const ok = await tools.execute(echoName, { message: 'hello from the client' }, context);
  console.log('\ncall echo   ->', JSON.stringify(ok));

  const bad = await tools.execute(toolName('mock', 'write_thing'), {}, context);
  console.log('call failing->', JSON.stringify(bad));

  console.log('unknown tool->', JSON.stringify(await tools.execute('mcp__mock__nope', {}, context)));
  console.log('owns() check:', tools.owns(echoName), tools.owns('read_file'));

  manager.dispose();
  console.log('\ndisposed cleanly');
  process.exit(0);
})();
