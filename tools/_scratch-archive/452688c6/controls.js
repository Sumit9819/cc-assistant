// Positive controls: each check asserts the NEW behaviour and states what the
// OLD behaviour was, so a passing run proves the fix is live in dist/.
const Module = require('node:module');
const path = require('node:path');
const root = process.cwd();

const originalLoad = Module._load;
Module._load = function (request, parent, isMain) {
  if (request === 'vscode') {
    return {
      workspace: {
        getConfiguration: () => ({ get: (_k, fallback) => fallback }),
        registerTextDocumentContentProvider: () => ({ dispose() {} })
      }
    };
  }
  return originalLoad.call(this, request, parent, isMain);
};

const results = [];
const check = (name, was, fn) => {
  try {
    const ok = fn();
    results.push([ok, name, was]);
  } catch (error) {
    results.push([false, name, was + ' :: threw ' + error.message]);
  }
};

const { CloudflareApiClient } = require(path.join(root, 'dist/services/cloudflareApiClient.js'));
const { AgentEngine } = require(path.join(root, 'dist/agent/agentEngine.js'));
const { TerminalTools } = require(path.join(root, 'dist/agent/tools/terminalTools.js'));
const { FileSystemTools } = require(path.join(root, 'dist/agent/tools/fileSystemTools.js'));
const { ContextManager } = require(path.join(root, 'dist/agent/contextManager.js'));

const client = new CloudflareApiClient();
const engine = new AgentEngine({}, {}, { getDefinitions: () => [] }, {}, {});
const terminal = Object.create(TerminalTools.prototype);

check('#1 edit_file tool is registered', 'only read_file/write_file/list_directory existed', () => {
  const instance = new FileSystemTools();
  const names = instance.definitions.map((definition) => definition.name);
  instance.dispose();
  return names.includes('edit_file') && names.includes('write_file');
});

check('#2 all tool calls survive parsing', 'extras after the first were discarded', () => {
  const turn = client.parseAgentTurn({
    tool_calls: [
      { id: '1', name: 'read_file', arguments: { path: 'a' } },
      { id: '2', name: 'read_file', arguments: { path: 'b' } },
      { id: '3', name: 'read_file', arguments: { path: 'c' } }
    ]
  });
  return turn.toolCalls.length === 3 && turn.toolCall === undefined;
});

check('#3 tool results carry tool_call_id', 'results were plain strings with no id', () => {
  const messages = engine.createObservationMessages(
    { rawResponse: 'r', assistantText: 'text', nativeTools: true },
    [
      { step: { type: 'tool_call', id: 'A', tool: 'read_file', arguments: {} }, result: { ok: true, output: 'x' } },
      { step: { type: 'tool_call', id: 'B', tool: 'list_directory', arguments: {} }, result: { ok: true, output: 'y' } }
    ]
  );
  return messages[0].tool_calls.length === 2
    && messages[1].tool_call_id === 'A'
    && messages[2].tool_call_id === 'B';
});

check('#4 capability parsed from catalogue', 'model support was a hardcoded name regex', () => {
  const info = client.parseModelInfo({
    name: '@cf/unknown/model-released-tomorrow',
    properties: [{ property_id: 'function_calling', value: 'true' }]
  });
  return info.functionCalling === true && engine.supportsNativeTools === undefined;
});

check('#5 agent turns can stream', 'completeAgentTurn always sent stream:false', () => {
  const partials = new Map();
  client.mergeToolCallFragment(partials, 0, { index: 0, id: 'z', function: { name: 'edit_file', arguments: '{"a":' } });
  client.mergeToolCallFragment(partials, 0, { index: 0, function: { arguments: '1}' } });
  const [call] = client.finalizeToolCalls(partials);
  return typeof client.consumeAgentEventStream === 'function' && call.arguments.a === 1;
});

check('#7 installs need confirmation in Auto', 'npm install ran unattended', () => {
  return terminal.isClearlyNonDestructive('npm install') === false
    && terminal.runsUntrustedCode('npm install') === true
    && terminal.isClearlyNonDestructive('npm run build') === true;
});

check('bonus: replaceSnippet is $-safe', 'n/a (new code)', () => {
  return FileSystemTools.replaceSnippet('a', 'a', "$&$'", false).content === "$&$'";
});

check('bonus: pruning keeps tool_call_id', 'truncation stripped it', () => {
  const pruned = new ContextManager().prune([
    { role: 'system', content: 's' },
    { role: 'assistant', content: '', tool_calls: [{ id: 'c1' }] },
    { role: 'tool', tool_call_id: 'c1', name: 'read_file', content: 'q'.repeat(9000) },
    { role: 'user', content: '1' }, { role: 'user', content: '2' }, { role: 'user', content: '3' },
    { role: 'user', content: '4' }, { role: 'user', content: '5' }, { role: 'user', content: '6' }
  ]);
  const tool = pruned.find((m) => m.role === 'tool');
  return tool.tool_call_id === 'c1' && tool.content.length < 9000;
});

Module._load = originalLoad;

let failed = 0;
for (const [ok, name, was] of results) {
  if (!ok) failed += 1;
  console.log((ok ? 'PASS  ' : 'FAIL  ') + name + '\n        was: ' + was);
}
console.log(failed ? '\n' + failed + ' control(s) failed' : '\nAll controls passed.');
process.exitCode = failed ? 1 : 0;
