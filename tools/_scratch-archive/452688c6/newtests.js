
  // ---------------------------------------------------------------------
  // AI Gateway routing. Verified by observing the request that is actually
  // made, not by reading the code that builds it.
  // ---------------------------------------------------------------------
  {
    const account = { accountId: 'acct-123', apiToken: 'token', alias: 'test' };
    const seen = [];
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async (url, init) => {
      seen.push({ url: String(url), headers: init.headers });
      return { ok: true, status: 200, json: async () => ({ success: true, result: { response: 'ok' } }) };
    };
    try {
      delete settings['aiGatewayId'];
      const direct = await apiClientRef.runModel(account, '@cf/meta/m2m100-1.2b', { text: 'hi' });
      assert.equal(direct.response, 'ok');
      assert.ok(
        seen[0].url.startsWith('https://api.cloudflare.com/client/v4/accounts/acct-123/ai/run/'),
        'Without a gateway the request must go direct: ' + seen[0].url
      );
      assert.equal(seen[0].headers['cf-aig-cache-ttl'], undefined, 'No cache header without a gateway');

      settings['aiGatewayId'] = 'my-gateway';
      settings['aiGatewayCacheTtlSeconds'] = 60;
      await apiClientRef.runModel(account, '@cf/meta/m2m100-1.2b', { text: 'hi' });
      assert.equal(
        seen[1].url,
        'https://gateway.ai.cloudflare.com/v1/acct-123/my-gateway/workers-ai/%40cf/meta/m2m100-1.2b',
        'A configured gateway must reroute inference: ' + seen[1].url
      );
      assert.equal(seen[1].headers['cf-aig-cache-ttl'], '60');

      // Embeddings are deterministic, so they may be cached; the model
      // catalogue is a control-plane call and must stay on the direct API.
      await apiClientRef.embed(account, '@cf/baai/bge-m3', ['a']).catch(() => undefined);
      assert.ok(seen[2].url.includes('gateway.ai.cloudflare.com'), 'Embeddings route through the gateway too');
      assert.equal(seen[2].headers['cf-aig-cache-ttl'], '60');
    } finally {
      globalThis.fetch = originalFetch;
      delete settings['aiGatewayId'];
      delete settings['aiGatewayCacheTtlSeconds'];
    }
  }

  // ---------------------------------------------------------------------
  // Summarise instead of truncate.
  // ---------------------------------------------------------------------
  {
    const calls = [];
    const summarise = async (text, target) => {
      calls.push({ length: text.length, target });
      return 'src/a.ts, src/b.ts, src/c.ts listed.';
    };

    const listing = 'src/file-' + 'x'.repeat(40) + '.ts\n'.repeat(1);
    const long = listing.repeat(400);
    const messages = [
      { role: 'system', content: 'system' },
      {
        role: 'assistant',
        content: '',
        tool_calls: [{ id: 'c1', type: 'function', function: { name: 'list_directory', arguments: '{}' } }]
      },
      { role: 'tool', tool_call_id: 'c1', name: 'list_directory', content: JSON.stringify({ ok: true, output: long }) },
      { role: 'user', content: 'and then?' },
      { role: 'assistant', content: 'ok' },
      { role: 'user', content: 'x' },
      { role: 'assistant', content: 'y' },
      { role: 'user', content: 'z' },
      { role: 'assistant', content: 'w' }
    ];

    const compressed = await contextManagerRef.compress(messages, summarise);
    assert.equal(compressed, 1, 'The oversized old observation should be summarised');
    assert.equal(calls.length, 1);
    // Only the payload is summarised: the ok flag is how the agent knows the
    // step succeeded, and the id is how the result is matched to its call.
    const rewritten = JSON.parse(messages[2].content);
    assert.equal(rewritten.ok, true);
    assert.ok(rewritten.output.includes('src/a.ts'), 'The summary must replace the payload');
    assert.ok(messages[2].content.length < 1000, 'The compressed message must actually be smaller');
    assert.equal(messages[2].tool_call_id, 'c1');
    assert.equal(messages[2].name, 'list_directory');

    // Compression happens once. Re-running must not spend a second call.
    assert.equal(await contextManagerRef.compress(messages, summarise), 0);
    assert.equal(calls.length, 1, 'An already-compressed observation must never be re-summarised');

    // Errors are the highest-value tokens in the context and are never compressed.
    const withError = [
      { role: 'system', content: 'system' },
      { role: 'tool', tool_call_id: 'c9', name: 'verify_project', content: JSON.stringify({ ok: false, output: 'VERIFICATION FAILED\n' + long }) },
      { role: 'user', content: 'a' },
      { role: 'assistant', content: 'b' },
      { role: 'user', content: 'c' },
      { role: 'assistant', content: 'd' },
      { role: 'user', content: 'e' },
      { role: 'assistant', content: 'f' }
    ];
    const before = withError[1].content;
    assert.equal(await contextManagerRef.compress(withError, summarise), 0);
    assert.equal(withError[1].content, before, 'A verification failure must survive compression untouched');

    // A summariser that is unavailable must leave the context exactly as it was.
    const unavailable = [
      { role: 'system', content: 'system' },
      { role: 'tool', tool_call_id: 'c2', name: 'list_directory', content: JSON.stringify({ ok: true, output: long }) },
      { role: 'user', content: 'a' },
      { role: 'assistant', content: 'b' },
      { role: 'user', content: 'c' },
      { role: 'assistant', content: 'd' },
      { role: 'user', content: 'e' },
      { role: 'assistant', content: 'f' }
    ];
    const untouched = unavailable[1].content;
    assert.equal(await contextManagerRef.compress(unavailable, async () => undefined), 0);
    assert.equal(unavailable[1].content, untouched);
  }
