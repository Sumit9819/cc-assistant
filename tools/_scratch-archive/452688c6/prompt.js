const Module = require('node:module');
const path = require('node:path');
const root = process.cwd();

const originalLoad = Module._load;
Module._load = function (request, parent, isMain) {
  if (request === 'vscode') {
    return {
      workspace: {
        getConfiguration: () => ({ get: (_k, f) => f }),
        registerTextDocumentContentProvider: () => ({ dispose() {} })
      },
      languages: { getDiagnostics: () => [], onDidChangeDiagnostics: () => ({ dispose() {} }) },
      DiagnosticSeverity: { Error: 0, Warning: 1, Information: 2, Hint: 3 }
    };
  }
  return originalLoad.call(this, request, parent, isMain);
};

const { AgentEngine } = require(path.join(root, 'dist/agent/agentEngine.js'));
const { FileSystemTools } = require(path.join(root, 'dist/agent/tools/fileSystemTools.js'));
const tools = new FileSystemTools();
Module._load = originalLoad;

const engine = new AgentEngine({}, {}, { getDefinitions: () => tools.definitions }, {}, {}, {
  load: async () => ({ text: '- Prefer Vitest over Jest.\n- No default exports.', source: 'AGENTS.md' })
});

engine.createSystemPrompt(
  {
    id: 'refactor', name: 'Architect', description: '', builtIn: true, model: '@cf/x/y',
    systemPrompt: 'You are an Autonomous Lead Software Architect.'
  },
  'auto',
  true
).then((prompt) => {
  console.log(prompt);
  console.log('\n---');
  console.log('prompt characters:', prompt.length);
  tools.dispose();
});
