
// Settings must be contributed and read, in both directions. A setting read
// but not contributed is invisible in the Settings UI and silently stuck on its
// code fallback; a setting contributed but never read is a promise to the user
// that nothing keeps.
{
  const declared = new Set(
    Object.keys(manifest.contributes.configuration.properties).map((key) =>
      key.replace(/^cloudflareAi\./, '')
    )
  );
  const read = new Set();
  const walk = (directory) => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const target = path.join(directory, entry.name);
      if (entry.isDirectory()) {
        walk(target);
      } else if (entry.name.endsWith('.ts')) {
        const source = fs.readFileSync(target, 'utf8');
        // Non-greedy across the type argument, so nested generics such as
        // Record<string, McpServerConfig> do not end the match early.
        for (const match of source.matchAll(/\.get<[\s\S]*?>\(\s*'([A-Za-z0-9_.]+)'/g)) {
          read.add(match[1]);
        }
      }
    }
  };
  walk(path.join(root, 'src'));

  assert.deepEqual(
    [...read].filter((key) => !declared.has(key)).sort(),
    [],
    'These settings are read by the code but not contributed in package.json'
  );
  assert.deepEqual(
    [...declared].filter((key) => !read.has(key)).sort(),
    [],
    'These settings are contributed in package.json but never read'
  );
  assert.ok(declared.size > 40, 'Settings parity check found suspiciously few settings');
}
