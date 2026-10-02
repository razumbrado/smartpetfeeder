/*
 * Password fields, any page:
 *
 * 1. Show/hide eye button on every password input (not the OTP code boxes,
 *    which have their own "Show Code" button).
 *
 * 2. Live validation (declarative):
 *   data-pw="required"          -> "<Label> is required."
 *   data-pw="strong"            -> 8+ chars, upper, lower, number, special
 *   data-pw-confirm="#otherId"  -> must match the other field
 * A hint <p class="password-hint"> is added under each field and updated as
 * the user types. Invalid fields block their form's submit. Load this BEFORE
 * main.js so the check runs before the AJAX submit handler.
 */
(function () {
  'use strict';

  /* ---- 1. Eye toggle ---- */
  function setVisible(input, btn, visible) {
    input.type = visible ? 'text' : 'password';
    btn.innerHTML = '<span class="material-symbols-outlined">' + (visible ? 'visibility_off' : 'visibility') + '</span>';
    btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
  }

  document.querySelectorAll('input[type="password"]:not(.otp-digit)').forEach(function (input) {
    const wrap = document.createElement('div');
    wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    setVisible(input, btn, false);
    btn.addEventListener('click', function () {
      setVisible(input, btn, input.type === 'password');
      input.focus({ preventScroll: true });
    });
    wrap.appendChild(btn);
  });

  /** Put every password field under `root` back to hidden (e.g. when a form is reset). */
  function hidePasswords(root) {
    (root || document).querySelectorAll('.pw-wrap').forEach(function (wrap) {
      const input = wrap.querySelector('input');
      const btn = wrap.querySelector('.pw-toggle');
      if (input && btn) setVisible(input, btn, false);
    });
  }
  window.hidePasswords = hidePasswords;
  document.querySelectorAll('form').forEach(function (form) {
    if (form.querySelector('.pw-wrap')) form.addEventListener('reset', function () { hidePasswords(form); });
  });

  /* ---- 2. Live validation ---- */
  const rules = {
    length: function (p) { return p.length >= 8; },
    uppercase: function (p) { return /[A-Z]/.test(p); },
    lowercase: function (p) { return /[a-z]/.test(p); },
    number: function (p) { return /[0-9]/.test(p); },
    special: function (p) { return /[^A-Za-z0-9]/.test(p); },
  };
  const ruleLabels = {
    length: 'at least 8 characters', uppercase: 'an uppercase letter', lowercase: 'a lowercase letter',
    number: 'a number', special: 'a special character',
  };

  function labelText(input) {
    const label = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
    return label ? label.textContent.trim() : 'Password';
  }

  function hintFor(input) {
    const anchor = input.closest('.pw-wrap') || input;   // below the eye-toggle wrapper
    let hint = anchor.parentElement.querySelector('.password-hint[data-for="' + input.id + '"]');
    if (!hint) {
      hint = document.createElement('p');
      hint.className = 'password-hint';
      hint.dataset.for = input.id;
      hint.setAttribute('aria-live', 'polite');
      anchor.insertAdjacentElement('afterend', hint);
    }
    return hint;
  }

  function setState(input, text, ok) {
    const hint = hintFor(input);
    hint.textContent = text;
    hint.className = 'password-hint' + (text ? (ok ? ' is-ok' : ' is-bad') : '');
    input.classList.toggle('is-invalid', !!text && !ok);
  }

  /** Returns true when the field is valid. `force` reports empty fields too (blur / submit). */
  function check(input, force) {
    const value = input.value;
    const mode = input.dataset.pw;
    const confirmOf = input.dataset.pwConfirm ? document.querySelector(input.dataset.pwConfirm) : null;

    if (!value) {
      setState(input, force ? (confirmOf ? 'Please confirm your password.' : labelText(input) + ' is required.') : '', false);
      return false;
    }
    if (confirmOf) {
      const same = value === confirmOf.value;
      setState(input, same ? 'Passwords match.' : 'Passwords do not match.', same);
      return same;
    }
    if (mode === 'strong') {
      const missing = Object.keys(rules).filter(function (k) { return !rules[k](value); });
      if (missing.length) {
        setState(input, 'Password must include ' + missing.map(function (k) { return ruleLabels[k]; }).join(', ') + '.', false);
        return false;
      }
      setState(input, 'Great! Your password meets all requirements.', true);
      return true;
    }
    setState(input, '', true);   // "required" and filled in
    return true;
  }

  function fieldsOf(form) {
    return Array.from(form.querySelectorAll('input[data-pw], input[data-pw-confirm]'));
  }

  document.querySelectorAll('input[data-pw], input[data-pw-confirm]').forEach(function (input) {
    input.addEventListener('input', function () {
      input.dataset.pwTouched = '1';
      check(input, false);
      // Re-check any confirm field that points at this one.
      document.querySelectorAll('input[data-pw-confirm="#' + input.id + '"]').forEach(function (c) {
        if (c.value) check(c, false);
      });
    });
    input.addEventListener('blur', function () { if (input.dataset.pwTouched) check(input, true); });
  });

  const forms = new Set();
  document.querySelectorAll('input[data-pw], input[data-pw-confirm]').forEach(function (i) { if (i.form) forms.add(i.form); });
  forms.forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      const bad = fieldsOf(form).filter(function (input) { return !check(input, true); });
      if (bad.length) {
        ev.preventDefault();
        ev.stopImmediatePropagation();
        bad[0].focus();
      }
    });
    // Clear hints when the form is reset (e.g. after a successful AJAX save).
    form.addEventListener('reset', function () {
      setTimeout(function () {
        fieldsOf(form).forEach(function (i) { delete i.dataset.pwTouched; setState(i, '', true); });
      }, 0);
    });
  });
})();
