
// The diagnostics probe image. A mangled base64 constant would not fail here
// at build time; it would fail as a confusing vision-model error against a live
// account, which is the expensive place to discover it.
{
  const diagnosticsSource = fs.readFileSync(path.join(root, 'src/services/diagnostics.ts'), 'utf8');
  const match = /PROBE_IMAGE_BASE64 =\s*'([A-Za-z0-9+/=]+)'/.exec(diagnosticsSource);
  assert.ok(match, 'diagnostics.ts must define PROBE_IMAGE_BASE64');
  const png = Buffer.from(match[1], 'base64');
  assert.ok(
    png.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])),
    'The probe image must be a real PNG'
  );
  assert.equal(png.subarray(12, 16).toString('ascii'), 'IHDR');
  assert.equal(png.readUInt32BE(16), 16, 'Probe image width');
  assert.equal(png.readUInt32BE(20), 16, 'Probe image height');
  assert.equal(png.subarray(-8, -4).toString('ascii'), 'IEND', 'The probe image must be complete');
  assert.ok(png.length < 1000, 'The probe image must stay small enough to cost nothing');

  // Each new capability must be reported, so a silent regression cannot hide
  // behind a diagnostics run that still says everything passed.
  for (const name of [
    'AI Gateway',
    'Assist Model',
    'Embeddings',
    'Reranker',
    'Vision Model',
    'Translation',
    'Image Generation'
  ]) {
    assert.ok(
      diagnosticsSource.includes(`name: '${name}'`),
      `Diagnostics no longer reports the ${name} check`
    );
  }
}
