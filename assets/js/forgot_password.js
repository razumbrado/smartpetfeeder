/* Forgot password on the login page: email -> 6-digit OTP -> new password */
(function () {
  'use strict';

  const view = document.getElementById('forgot-view');
  const loginForm = document.querySelector('#panel-login form.auth-form');
  const forgotLink = document.getElementById('forgot-link');
  if (!view || !loginForm || !forgotLink) return;

  const OTP_LIFETIME = 60;
  const steps = {};
  view.querySelectorAll('[data-step]').forEach(function (el) { steps[el.dataset.step] = el; });

  const emailInput = document.getElementById('reset-email');
  const sendBtn = document.getElementById('send-otp-btn');
  const otpDigits = Array.from(view.querySelectorAll('.otp-digit'));
  const otpEmailDisplay = document.getElementById('otp-email-display');
  const toggleOtpBtn = document.getElementById('toggle-otp-btn');
  const resendBtn = document.getElementById('resend-otp-btn');
  const timerEl = document.getElementById('otp-timer');
  const verifyBtn = document.getElementById('verify-otp-btn');
  const newPw = document.getElementById('new-password');
  const confirmPw = document.getElementById('confirm-new-password');
  const strengthHint = document.getElementById('password-strength-hint');
  const matchHint = document.getElementById('password-match-hint');
  const resetBtn = document.getElementById('reset-password-btn');
  const doneBtn = document.getElementById('reset-done-btn');

  let currentEmail = '';
  let timerId = null;
  let otpExpired = false;
  let otpVisible = false;

  /* ---- helpers ---- */
  function icon(name) { return '<span class="material-symbols-outlined">' + name + '</span>'; }

  function showAlert(step, message, type) {
    const box = steps[step].querySelector('[data-alert]');
    box.className = 'auth-alert auth-alert--' + (type || 'error');
    box.innerHTML = icon(type === 'success' ? 'check_circle' : 'error') + ' <span></span>';
    box.querySelector('span:last-child').textContent = message;
    box.hidden = false;
  }
  function clearAlert(step) { steps[step].querySelector('[data-alert]').hidden = true; }

  function setFieldError(key, message) {
    const el = view.querySelector('[data-error="' + key + '"]');
    if (el) el.textContent = message || '';
    if (key === 'email') emailInput.classList.toggle('is-invalid', !!message);
    if (key === 'otp') otpDigits.forEach(function (d) { d.classList.toggle('is-invalid', !!message); });
  }

  function showStep(name) {
    Object.keys(steps).forEach(function (k) { steps[k].hidden = k !== name; });
  }

  /** juan.delacruz@gmail.com -> ju*********uz@gmail.com */
  function maskEmail(email) {
    const parts = email.split('@');
    const local = parts[0], domain = parts[1];
    if (!domain) return email;
    if (local.length <= 4) return local[0] + '*'.repeat(Math.max(local.length - 1, 1)) + '@' + domain;
    return local.slice(0, 2) + '*'.repeat(local.length - 4) + local.slice(-2) + '@' + domain;
  }

  async function post(url, body) {
    body.csrf = view.dataset.csrf;
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
      body: JSON.stringify(body),
    });
    return res.json();
  }

  function busy(btn, on, text) {
    if (on) { btn.dataset.html = btn.innerHTML; btn.disabled = true; btn.textContent = text; }
    else { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; }
  }

  /* ---- switching views ---- */
  function showForgot() {
    currentEmail = '';
    emailInput.value = (loginForm.querySelector('#identifier').value.indexOf('@') > -1)
      ? loginForm.querySelector('#identifier').value.trim() : '';
    setFieldError('email', '');
    Object.keys(steps).forEach(function (k) { if (steps[k].querySelector('[data-alert]')) clearAlert(k); });
    stopTimer();
    clearOtp();
    newPw.value = ''; confirmPw.value = '';
    if (window.hidePasswords) window.hidePasswords(view);
    updateStrength(); updateMatch();
    showStep('email');
    loginForm.hidden = true;
    view.hidden = false;
    emailInput.focus();
  }
  function showLogin() {
    stopTimer();
    view.hidden = true;
    loginForm.hidden = false;
  }

  forgotLink.addEventListener('click', function (e) { e.preventDefault(); showForgot(); });
  document.getElementById('back-to-login').addEventListener('click', function (e) { e.preventDefault(); showLogin(); });
  doneBtn.addEventListener('click', showLogin);
  // Switching to Register and back always lands on the normal login form.
  document.querySelectorAll('[data-switch]').forEach(function (el) { el.addEventListener('click', showLogin); });

  /* ---- step 1: email ---- */
  async function requestOtp(isResend) {
    const email = isResend ? currentEmail : emailInput.value.trim();
    const step = isResend ? 'otp' : 'email';
    clearAlert(step);
    if (!isResend) {
      setFieldError('email', '');
      if (!email) { setFieldError('email', 'Email is required.'); emailInput.focus(); return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setFieldError('email', 'Please enter a valid email address.'); emailInput.focus(); return; }
    }

    const btn = isResend ? resendBtn : sendBtn;
    busy(btn, true, 'Sending...');
    try {
      const data = await post(view.dataset.sendUrl, { email: email });
      if (data.success) {
        currentEmail = email;
        otpEmailDisplay.textContent = maskEmail(email);
        showOtpStep(data.expires_in);
        if (isResend) showAlert('otp', 'A new code has been sent to your email.', 'success');
      } else if (isResend || data.cooldown) {
        showAlert(step, data.message, 'error');
      } else {
        setFieldError('email', data.message);   // e.g. "No account found with that email address."
      }
    } catch (e) {
      showAlert(step, 'Something went wrong. Please try again.', 'error');
    } finally {
      busy(btn, false);
      if (isResend) resendBtn.disabled = !otpExpired;
    }
  }

  sendBtn.addEventListener('click', function () { requestOtp(false); });
  emailInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); requestOtp(false); } });
  emailInput.addEventListener('input', function () { setFieldError('email', ''); clearAlert('email'); });
  resendBtn.addEventListener('click', function () { if (currentEmail) requestOtp(true); });

  /* ---- step 2: 6 OTP boxes, show/hide, 1-minute countdown ---- */
  function otpValue() { return otpDigits.map(function (d) { return d.value; }).join(''); }
  function clearOtp() { otpDigits.forEach(function (d) { d.value = ''; d.classList.remove('is-filled'); }); }
  function focusOtp(i) {
    const d = otpDigits[Math.max(0, Math.min(i, otpDigits.length - 1))];
    d.focus(); d.select();
  }
  function fillOtpFrom(i, digits) {
    digits.split('').forEach(function (ch, off) {
      const d = otpDigits[i + off];
      if (d) { d.value = ch; d.classList.add('is-filled'); }
    });
    focusOtp(i + digits.length);
  }
  function setOtpVisible(on) {
    otpVisible = on;
    otpDigits.forEach(function (d) { d.type = on ? 'text' : 'password'; });
    toggleOtpBtn.innerHTML = icon(on ? 'visibility_off' : 'visibility') + ' <span>' + (on ? 'Hide Code' : 'Show Code') + '</span>';
  }

  function stopTimer() { clearInterval(timerId); timerId = null; }
  function renderTimer(s) { timerEl.textContent = 'Code expires in ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }
  function expireOtp() {
    stopTimer();
    otpExpired = true;
    clearOtp();
    setFieldError('otp', '');
    otpDigits.forEach(function (d) { d.disabled = true; });
    timerEl.textContent = 'The code has expired. Please request a new one.';
    timerEl.classList.add('is-expired');
    resendBtn.disabled = false;
  }
  function startTimer(seconds) {
    stopTimer();
    otpExpired = false;
    otpDigits.forEach(function (d) { d.disabled = false; });
    timerEl.classList.remove('is-expired');
    resendBtn.disabled = true;
    let remaining = seconds;
    renderTimer(remaining);
    timerId = setInterval(function () {
      remaining -= 1;
      if (remaining <= 0) { expireOtp(); return; }
      renderTimer(remaining);
    }, 1000);
  }

  function showOtpStep(expiresIn) {
    clearOtp();
    setFieldError('otp', '');
    setOtpVisible(false);
    clearAlert('otp');
    showStep('otp');
    startTimer(expiresIn || OTP_LIFETIME);
    focusOtp(0);
  }

  otpDigits.forEach(function (input, index) {
    input.addEventListener('input', function () {
      const digits = input.value.replace(/\D/g, '');
      input.value = '';
      input.classList.remove('is-filled');
      clearAlert('otp');
      setFieldError('otp', '');
      if (digits) fillOtpFrom(index, digits);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !input.value && index > 0) {
        e.preventDefault();
        otpDigits[index - 1].value = '';
        otpDigits[index - 1].classList.remove('is-filled');
        focusOtp(index - 1);
      } else if (e.key === 'ArrowLeft' && index > 0) { e.preventDefault(); focusOtp(index - 1); }
      else if (e.key === 'ArrowRight' && index < otpDigits.length - 1) { e.preventDefault(); focusOtp(index + 1); }
      else if (e.key === 'Enter') { e.preventDefault(); verifyBtn.click(); }
    });
    input.addEventListener('paste', function (e) {
      e.preventDefault();
      const digits = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      if (digits) { clearAlert('otp'); setFieldError('otp', ''); fillOtpFrom(0, digits); }
    });
    input.addEventListener('focus', function () { input.select(); });
  });

  toggleOtpBtn.addEventListener('click', function () { setOtpVisible(!otpVisible); });

  verifyBtn.addEventListener('click', async function () {
    const otp = otpValue();
    clearAlert('otp');
    setFieldError('otp', '');
    if (otpExpired) { showAlert('otp', 'The code has expired. Please click Resend Code to get a new one.'); return; }
    if (otp.length < otpDigits.length) {
      setFieldError('otp', otp ? 'Please enter the complete 6-digit code.' : 'Verification code is required.');
      focusOtp(otpDigits.findIndex(function (d) { return !d.value; }));
      return;
    }

    busy(verifyBtn, true, 'Verifying...');
    try {
      const data = await post(view.dataset.verifyUrl, { email: currentEmail, otp: otp });
      if (data.success) {
        stopTimer();
        showResetStep();
      } else {
        clearOtp();
        setFieldError('otp', data.message);
        if (!otpExpired) focusOtp(0);
      }
    } catch (e) {
      showAlert('otp', 'Something went wrong. Please try again.');
    } finally {
      busy(verifyBtn, false);
    }
  });

  /* ---- step 3: new password ---- */
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
  function isStrong(p) { return Object.keys(rules).every(function (k) { return rules[k](p); }); }

  function setHint(el, text, ok) {
    el.textContent = text;
    el.className = 'password-hint' + (text ? (ok ? ' is-ok' : ' is-bad') : '');
  }
  function updateStrength() {
    const p = newPw.value;
    if (!p) { setHint(strengthHint, ''); return; }
    const missing = Object.keys(rules).filter(function (k) { return !rules[k](p); });
    if (!missing.length) setHint(strengthHint, 'Great! Your password meets all requirements.', true);
    else setHint(strengthHint, 'Password must include ' + missing.map(function (k) { return ruleLabels[k]; }).join(', ') + '.', false);
  }
  function updateMatch() {
    if (!confirmPw.value) { setHint(matchHint, ''); return; }
    const same = newPw.value === confirmPw.value;
    setHint(matchHint, same ? 'Passwords match.' : 'Passwords do not match.', same);
  }
  newPw.addEventListener('input', function () { newPw.classList.remove('is-invalid'); updateStrength(); updateMatch(); });
  confirmPw.addEventListener('input', function () { confirmPw.classList.remove('is-invalid'); updateMatch(); });

  function showResetStep() {
    clearAlert('reset');
    newPw.value = ''; confirmPw.value = '';
    if (window.hidePasswords) window.hidePasswords(view);
    newPw.classList.remove('is-invalid'); confirmPw.classList.remove('is-invalid');
    updateStrength(); updateMatch();
    showStep('reset');
    newPw.focus();
  }

  resetBtn.addEventListener('click', async function () {
    const p = newPw.value, c = confirmPw.value;
    clearAlert('reset');
    updateStrength(); updateMatch();

    let bad = false;
    if (!p) { setHint(strengthHint, 'New password is required.', false); bad = true; }
    else if (!isStrong(p)) bad = true;
    if (!c) { setHint(matchHint, 'Please confirm your new password.', false); bad = true; }
    else if (p !== c) bad = true;
    newPw.classList.toggle('is-invalid', !p || !isStrong(p));
    confirmPw.classList.toggle('is-invalid', !c || p !== c);
    if (bad) return;

    busy(resetBtn, true, 'Resetting...');
    try {
      const data = await post(view.dataset.resetUrl, { email: currentEmail, password: p, confirm: c });
      if (data.success) {
        showStep('done');
      } else if (data.restart) {
        emailInput.value = currentEmail;
        showStep('email');
        showAlert('email', data.message);
      } else {
        showAlert('reset', data.message);
      }
    } catch (e) {
      showAlert('reset', 'Something went wrong. Please try again.');
    } finally {
      busy(resetBtn, false);
    }
  });
})();
