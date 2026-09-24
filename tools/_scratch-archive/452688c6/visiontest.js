
  // ---------------------------------------------------------------------
  // The vision fallback must report the failure that CAUSED the problem,
  // not the one it caused. When the first encoding trips the only account
  // into cooldown, the second attempt fails instantly with "no accounts
  // available" — and that message, left unguarded, is all the user ever saw.
  // ---------------------------------------------------------------------
  {
    const { MediaModels } = require(path.join(root, 'dist/services/mediaModels.js'));
    const { AccountPoolExhaustedError } = require(path.join(root, 'dist/services/accountPoolManager.js'));
    const { CloudflareApiError } = require(path.join(root, 'dist/services/cloudflareApiClient.js'));

    const attempts = [];
    const warnings = [];
    const pool = {
      runWithFailover: async (operation) => {
        if (attempts.length === 0) {
          attempts.push('first');
          throw new CloudflareApiError('Service Unavailable', 503, undefined, undefined, true);
        }
        attempts.push('second');
        throw new AccountPoolExhaustedError('All Cloudflare accounts are disabled, missing a token, or in cooldown.');
      }
    };
    const media = new MediaModels(pool, {}, { info() {}, warn: (line) => warnings.push(line) });

    let thrown;
    await media.describeImage(Uint8Array.from([1, 2, 3]), 'describe').catch((error) => { thrown = error; });

    assert.ok(thrown, 'describeImage must reject when every encoding fails');
    assert.match(
      thrown.message,
      /HTTP 503/,
      `The cause must survive; got: ${thrown.message}`
    );
    assert.doesNotMatch(
      thrown.message,
      /accounts are disabled/,
      'The downstream symptom must not replace the cause'
    );
    // The warning has to carry the reason, or the log says a payload was
    // rejected without ever saying why.
    assert.match(warnings[0], /byte array encoding: HTTP 503/);

    // A second encoding cannot help once no account is left to send it to.
    const exhausted = new AccountPoolExhaustedError('none left');
    const stopper = new MediaModels(
      { runWithFailover: async () => { attempts.push('extra'); throw exhausted; } },
      {},
      undefined
    );
    attempts.length = 0;
    await stopper.describeImage(Uint8Array.from([1]), 'describe').catch(() => undefined);
    assert.equal(attempts.length, 1, 'Pool exhaustion must stop the encoding loop immediately');
  }
