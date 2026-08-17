/* Codeface — live email existence check for the login/register forms.
 * Debounced POST to ../backend/api/auth/check-email.php; paints a red warning
 * when the domain can't receive mail (the mailbox cannot exist), amber when
 * offline DNS makes the answer unknown, and green when it looks deliverable.
 * A Gmail typo suggestion is clickable — it fixes the address for you. */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  document.querySelectorAll('input[data-emailcheck]').forEach(function (input) {
    var hint = input.closest('label') ? input.closest('label').querySelector('[data-emailhint]') : null;
    if (!hint) hint = document.querySelector('[data-emailhint="' + input.name + '"]');
    if (!hint) {
      hint = document.createElement('small');
      hint.className = 'email-warn';
      input.parentNode.appendChild(hint);
    }
    var onlyIfEmail = input.hasAttribute('data-emailcheck-if-email'); // login's identity field may be a username
    var timer = null;

    function paint(cls, html) { hint.className = 'email-warn ' + cls; hint.innerHTML = html || ''; }
    function clear() { paint('', ''); }

    function run(email) {
      var fd = new FormData();
      fd.append('email', email);
      fetch('../backend/api/auth/check-email.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json().then(function (d) { return { status: r.status, d: d }; }); })
        .then(function (res) {
          var d = res.d || {};
          if (res.status === 429) { paint('dim', 'too many checks — slow down a little'); return; }
          if (d.ok !== true) { clear(); return; }
          if (!d.format) { paint('err', '✕ That doesn\u2019t look like a real email address.'); return; }
          var fix = d.suggestion
            ? ' — <a href="#" data-fix="' + d.suggestion + '">did you mean <strong>' + d.suggestion + '</strong>?</a>'
            : '';
          if (d.mx === false) {
            paint('err', '✕ <strong>' + d.domain + '</strong> has no mail server — this mailbox can\u2019t exist' + fix);
          } else if (d.mx === true) {
            paint('ok', '✓ ' + d.domain + ' accepts email' + fix);
          } else {
            paint('dim', 'looks OK — offline, so the domain check was skipped' + fix);
          }
        })
        .catch(function () { /* offline / API unreachable → leave the field alone */ });
    }

    hint.addEventListener('click', function (e) {
      var a = e.target.closest('[data-fix]');
      if (!a) return;
      e.preventDefault();
      input.value = input.value.replace(/@.*$/, '@' + a.getAttribute('data-fix'));
      input.focus();
      run(input.value.trim());
    });

    function schedule() {
      var v = input.value.trim();
      if (onlyIfEmail && v.indexOf('@') === -1) { clear(); return; }   // typing a username — no check
      if (v.indexOf('@') === -1 || v.length < 6) { clear(); return; }
      clearTimeout(timer);
      timer = setTimeout(function () { run(v); }, 450);
    }
    input.addEventListener('input', schedule);
    input.addEventListener('blur', function () {
      var v = input.value.trim();
      if (v.length >= 6 && v.indexOf('@') !== -1) run(v);
    });
  });
});
