
// Every Workers AI model the extension names must actually exist.
//
// A model id that does not exist fails at runtime in the quietest possible way:
// the assist model degrades to "unavailable" and both triage and tool-output
// summarisation silently stop happening, while everything still appears to
// work. That is exactly how @cf/meta/llama-3.1-8b-instruct-fast shipped as a
// default — a plausible name pattern-matched from a real one.
//
// @cloudflare/workers-types declares the catalogue as the keys of AiModels, so
// the check is against Cloudflare's own generated list rather than a hand-kept
// copy that would drift.
{
  const typesPath = path.join(root, 'node_modules/@cloudflare/workers-types/index.d.ts');
  assert.ok(
    fs.existsSync(typesPath),
    '@cloudflare/workers-types must be installed so model ids can be checked'
  );
  const catalogue = fs.readFileSync(typesPath, 'utf8');
  const known = new Set();
  for (const match of catalogue.matchAll(/^\s+"(@cf\/[^"]+)":/gm)) {
    known.add(match[1]);
  }
  assert.ok(known.size > 40, `Parsed only ${known.size} models from workers-types; the format changed`);

  const referenced = new Set();
  const collect = (source) => {
    for (const match of source.matchAll(/@cf\/[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+/g)) {
      // Trailing punctuation from prose in setting descriptions.
      referenced.add(match[0].replace(/[.]+$/, ''));
    }
  };
  const walk = (directory) => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const target = path.join(directory, entry.name);
      if (entry.isDirectory()) {
        walk(target);
      } else if (entry.name.endsWith('.ts')) {
        collect(fs.readFileSync(target, 'utf8'));
      }
    }
  };
  walk(path.join(root, 'src'));
  collect(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));

  const missing = [...referenced].filter((model) => !known.has(model)).sort();
  assert.deepEqual(missing, [], 'These model ids do not exist in the Workers AI catalogue');
  assert.ok(referenced.size >= 8, `Only found ${referenced.size} model references; the scan is not working`);
}
