const assert = require('node:assert/strict');
const observeForms = require('../bin/wcag/form-observation.js');
const {chromium} = require(process.env.CC_TEST_PLAYWRIGHT || 'playwright');
(async () => {
  const browser = await chromium.launch({headless:true, channel:'chrome'});
  try {
    const page = await browser.newPage();
    let requests = 0;
    await page.route('**/*', route => { requests++; return route.abort(); });
    await page.setContent('<form><input required><button type="submit">Send</button></form><form novalidate><input aria-required="true"><button>Send</button></form><form><input></form>');
    await page.evaluate(() => {
      window.events = [];
      for (const name of ['submit', 'click', 'invalid']) document.addEventListener(name, e => { window.events.push(name); fetch('https://fixture.invalid/event'); }, true);
    });
    const result = await page.evaluate(observeForms);
    assert.equal(result.length, 3);
    assert.ok(result.every(r => r.result === 'submission_not_tested'));
    assert.equal(result[0].native_constraints_present, true);
    assert.equal(result[1].native_constraints_present, false);
    assert.deepEqual(await page.evaluate(() => window.events), []);
    assert.equal(requests, 0);
    console.log('PASS: Native-required, aria-required and optional forms inspected without click, submit, invalid events or network requests.');
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
