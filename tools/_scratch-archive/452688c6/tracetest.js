
// The reasoning panel, exercised rather than pattern-matched: build real
// message nodes from the delivered script and check what a reader would see.
{
  const created = [];
  const element = (tag) => {
    const node = {
      tag,
      className: '',
      textContent: '',
      hidden: false,
      open: false,
      children: [],
      listeners: {},
      dataset: {},
      appendChild(child) { this.children.push(child); return child; },
      append(...kids) { this.children.push(...kids); },
      replaceChildren(...kids) { this.children = kids; },
      querySelector() { return null; },
      addEventListener(name, handler) { this.listeners[name] = handler; },
      classList: { add() {}, remove() {}, toggle() {} }
    };
    created.push(node);
    return node;
  };
  const stored = {};
  const helpers = deliveredScript.slice(
    deliveredScript.indexOf('function createMessage'),
    deliveredScript.indexOf('function addActions')
  );
  const harness = 'let traceExpanded = Boolean((vscode.getState() || {}).traceExpanded);\n' +
    helpers +
    '\nreturn { createMessage, traceLine, settleTrace, expanded: () => traceExpanded };';
  const api = new Function(
    'document', 'vscode', 'messages', 'welcome', 'responseElements', 'setContent', 'scrollToBottom',
    harness
  )(
    { createElement: element },
    { getState: () => stored, setState: (value) => Object.assign(stored, value) },
    element('div'),
    element('div'),
    new Map(),
    () => {},
    () => {}
  );

  const first = api.createMessage('assistant', 'Working...');
  const details = first.traceDetails;
  assert.equal(details.tag, 'details');
  assert.equal(details.open, false, 'The reasoning panel must start folded');
  assert.equal(details.hidden, true, 'An assistant message with no steps must show no panel at all');

  api.traceLine(first, 'Tool: read_file {"path":"a.ts"}');
  assert.equal(details.hidden, false, 'The first step reveals the panel');
  assert.equal(first.traceHeadline.textContent, 'Tool: read_file {"path":"a.ts"}',
    'A folded panel must still say what the agent is doing');
  assert.equal(first.traceCount.textContent, '1');
  api.traceLine(first, 'Completed: read_file');
  assert.equal(first.traceCount.textContent, '2');
  assert.equal(first.trace.children.length, 2, 'Steps belong inside the folded body');

  api.settleTrace(first);
  assert.equal(first.traceHeadline.textContent, '2 steps', 'A finished run stops reading as live activity');

  // Opening one panel is a standing preference: the next message honours it.
  details.open = true;
  details.listeners.toggle();
  assert.equal(api.expanded(), true);
  assert.equal(stored.traceExpanded, true, 'The choice must survive reopening the view');
  const second = api.createMessage('assistant', 'Working...');
  assert.equal(second.traceDetails.open, true, 'Once opened, later messages open too');

  // And folding it again goes back to quiet.
  second.traceDetails.open = false;
  second.traceDetails.listeners.toggle();
  assert.equal(api.createMessage('assistant', 'x').traceDetails.open, false);

  // A user message has no reasoning to show.
  assert.equal(api.createMessage('user', 'hello').traceDetails, null);
}
