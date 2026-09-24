// Executed inside the audited page. Never activate a form or dispatch validation events.
function observeForms() {
  return [...document.querySelectorAll('form')].slice(0, 3).map((form, idx) => {
    const inputs = [...form.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]), textarea, select')];
    if (!inputs.length) return null;
    const required = inputs.filter(i => i.required || i.getAttribute('aria-required') === 'true');
    const invalid = form.querySelectorAll('[aria-invalid="true"]').length;
    const alerts = [...form.querySelectorAll('[role=alert], [aria-live]')].filter(el => (el.textContent || '').trim()).length;
    return { form: idx, fields: inputs.length, required: required.length,
      native_constraints_present: !form.noValidate && inputs.some(i => i.willValidate && i.validity && !i.validity.valid),
      existing_error_markup: invalid > 0 || alerts > 0,
      result: 'submission_not_tested',
      limitation: 'Passive markup observation; submission, error announcements and server validation were not exercised.' };
  }).filter(Boolean);
}
module.exports = observeForms;
