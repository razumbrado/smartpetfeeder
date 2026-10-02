/* Smart Pet Feeder - front-end interactions (progressive enhancement) */
(function () {
  'use strict';

  /* ---- Small helpers ---- */
  function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }
  function iconHtml(name) {
    return '<span class="material-symbols-outlined">' + escapeHtml(name) + '</span>';
  }

  /* ---- Modal helpers ---- */
  function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.hidden = false;
  }
  function closeModal(overlay) {
    if (overlay) overlay.hidden = true;
  }
  function closeAllModals() {
    document.querySelectorAll('[data-modal]').forEach(closeModal);
  }
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') closeAllModals();
  });

  document.querySelectorAll('[data-modal-open]').forEach(function (el) {
    el.addEventListener('click', function () {
      const closeTarget = el.hasAttribute('data-modal-close') ? el.closest('[data-modal]') : null;
      openModal(el.dataset.modalOpen);
      closeModal(closeTarget);
    });
  });
  document.querySelectorAll('[data-modal-close]:not([data-modal-open])').forEach(function (el) {
    el.addEventListener('click', function () {
      closeModal(el.closest('[data-modal]'));
    });
  });
  document.querySelectorAll('[data-modal]').forEach(function (overlay) {
    overlay.addEventListener('click', function (ev) {
      if (ev.target === overlay) closeModal(overlay);
    });
  });

  /* ---- Confirm dialog (custom modal instead of window.confirm) ---- */
  let pendingConfirmAction = null;
  function showConfirm(message, onConfirm) {
    const modal = document.getElementById('confirm-modal');
    const text = document.getElementById('confirm-modal-text');
    if (!modal || !text) {
      if (window.confirm(message)) onConfirm();
      return;
    }
    text.textContent = message;
    pendingConfirmAction = onConfirm;
    openModal('confirm-modal');
  }
  const confirmOkBtn = document.getElementById('confirm-modal-ok');
  if (confirmOkBtn) {
    confirmOkBtn.addEventListener('click', function () {
      closeModal(document.getElementById('confirm-modal'));
      const action = pendingConfirmAction;
      pendingConfirmAction = null;
      if (action) action();
    });
  }

  /* ---- Inline form error (used instead of a page-wide flash for AJAX actions) ---- */
  function showFormError(form, message) {
    let box = form.querySelector('[data-form-error]');
    if (!box) {
      box = document.createElement('div');
      box.className = 'login-error';
      box.setAttribute('data-form-error', '');
      box.style.marginBottom = '16px';
      form.prepend(box);
    }
    box.innerHTML = iconHtml('error') + ' ' + escapeHtml(message || 'Something went wrong.');
  }
  function clearFormError(form) {
    const box = form.querySelector('[data-form-error]');
    if (box) box.remove();
  }

  /* ---- Sidebar / topbar unread-alerts badges ---- */
  function syncSidebarUnreadBadge(count) {
    if (typeof count !== 'number') return;

    const link = document.querySelector('.navlink[href$="pages/alerts.php"]');
    if (link) {
      let badge = link.querySelector('.navlink__badge');
      if (count > 0) {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'navlink__badge';
          link.appendChild(badge);
        }
        badge.textContent = count;
      } else if (badge) {
        badge.remove();
      }
    }

    const bell = document.querySelector('.topbar-bell');
    if (bell) {
      let bellBadge = bell.querySelector('.topbar-bell__badge');
      if (count > 0) {
        if (!bellBadge) {
          bellBadge = document.createElement('span');
          bellBadge.className = 'topbar-bell__badge';
          bell.appendChild(bellBadge);
        }
        bellBadge.textContent = count;
      } else if (bellBadge) {
        bellBadge.remove();
      }
    }
  }

  /* ---- Food level display + availability check (Feed page) ---- */
  const foodMeter = document.getElementById('food-meter');
  const feedFormEl = document.getElementById('feed-now-form');
  const foodEndpoint = feedFormEl ? feedFormEl.dataset.foodEndpoint : null;

  // Latest food reading from the server (null on network error).
  async function fetchFood() {
    if (!foodEndpoint) return null;
    try {
      const res = await fetch(foodEndpoint, { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' });
      const json = await res.json();
      return json && json.ok ? json : null;
    } catch (e) {
      return null;
    }
  }

  function updateFoodDisplay(food) {
    if (!foodMeter || !food) return;
    const q = function (sel) { return foodMeter.querySelector(sel); };
    const fill = q('[data-food-fill]');
    if (fill) {
      fill.style.height = food.percent + '%';
      fill.classList.toggle('is-warning', food.state === 'warning');
      fill.classList.toggle('is-danger', food.state === 'danger');
    }
    if (q('[data-food-pct]')) q('[data-food-pct]').textContent = food.percent + '%';
    if (q('[data-food-label]')) q('[data-food-label]').textContent = food.label;
    if (q('[data-food-low]')) q('[data-food-low]').hidden = !food.is_low;
    if (q('[data-food-grams]')) q('[data-food-grams]').textContent = Number(food.available_grams).toLocaleString() + ' g';
    if (q('[data-food-source]')) q('[data-food-source]').textContent = food.source;
    if (q('[data-food-updated]')) q('[data-food-updated]').textContent = food.updated_label;
  }

  function showInsufficientFood(available, required) {
    const modal = document.getElementById('insufficient-food-modal');
    if (!modal) { window.alert('Insufficient food: ' + available + ' g available, ' + required + ' g required.'); return; }
    modal.querySelector('[data-insufficient-available]').textContent = available + ' g';
    modal.querySelector('[data-insufficient-required]').textContent = required + ' g';
    openModal('insufficient-food-modal');
  }

  // Keep the meter current so a refill / dispense reported by the ESP32 shows up.
  if (foodMeter) {
    setInterval(function () {
      if (document.hidden) return;
      fetchFood().then(updateFoodDisplay);
    }, 15000);
  }

  /* ---- Portion picker (Feed page) ---- */
  const portionGrid = document.querySelector('[data-portion-grid]');
  if (portionGrid) {
    const feedNowForm = document.getElementById('feed-now-form');
    const portionsInput = document.querySelector('#portions-input');
    const customInput = document.querySelector('#custom-portions-input');
    const customError = document.querySelector('#custom-portions-error');
    const totalEl = document.querySelector('#portion-grams-total');
    // Fixed system value (1 portion = 40 g); not user-editable.
    const gramsPerPortion = parseInt(portionGrid.dataset.gramsPerPortion || '40', 10);
    const maxPortions = parseInt(portionGrid.dataset.maxPortions || '25', 10);

    function portionLabel(p) { return p + ' portion' + (p === 1 ? '' : 's'); }

    // Returns an error message for the custom field, or '' when valid.
    function validateCustomPortion(raw) {
      return portionInputError(raw, maxPortions);
    }

    function setCustomError(message) {
      if (!customError || !customInput) return;
      customError.textContent = message;
      customError.hidden = !message;
      customInput.classList.toggle('is-invalid', !!message);
    }

    // Reflect the effective portion count in the hidden field, total and confirm text.
    function applyPortions(p) {
      if (portionsInput) portionsInput.value = p ? p : '';
      if (totalEl) totalEl.textContent = p ? portionLabel(p) + ' = ' + (p * gramsPerPortion) + ' g' : '—';
      if (feedNowForm && p) {
        feedNowForm.dataset.confirm = 'Are you sure you want to feed ' + (p * gramsPerPortion) + ' g (' + portionLabel(p) + ')?';
      }
    }

    function selectCard(p) {
      portionGrid.querySelectorAll('.portion-opt').forEach(function (o) {
        o.classList.toggle('is-selected', parseInt(o.dataset.portions, 10) === p);
      });
    }

    portionGrid.addEventListener('click', function (ev) {
      const opt = ev.target.closest('.portion-opt');
      if (!opt) return;
      const p = parseInt(opt.dataset.portions, 10);
      selectCard(p);
      if (customInput) customInput.value = '';
      setCustomError('');
      applyPortions(p);
    });

    if (customInput) {
      customInput.addEventListener('input', function () {
        // Any custom entry overrides the predefined cards.
        selectCard(null);
        if (customInput.value.trim() === '') {
          setCustomError('');
          applyPortions(null);
          return;
        }
        const err = validateCustomPortion(customInput.value);
        setCustomError(err);
        applyPortions(err ? null : parseInt(customInput.value.trim(), 10));
      });
    }

    // Registered before bindActionForms(), so stopping here blocks the
    // confirm dialog and the AJAX submit when the portion is invalid.
    if (feedNowForm) {
      feedNowForm.addEventListener('submit', function (ev) {
        const usingCustom = customInput && !portionGrid.querySelector('.portion-opt.is-selected');
        const err = usingCustom ? validateCustomPortion(customInput.value) : '';
        if (err || !portionsInput || !portionsInput.value) {
          ev.preventDefault();
          ev.stopImmediatePropagation();
          setCustomError(err || 'Please select a portion or enter a custom portion.');
          if (customInput) customInput.focus();
          return;
        }

        // Food check passed a moment ago -> let this submit through to the
        // confirm dialog / AJAX handler.
        if (feedNowForm.dataset.foodChecked === '1') {
          delete feedNowForm.dataset.foodChecked;
          return;
        }

        // Fetch the LATEST food weight and compare before anything is sent.
        // (feed_now.php repeats this check server-side.)
        ev.preventDefault();
        ev.stopImmediatePropagation();
        const required = parseInt(portionsInput.value, 10) * gramsPerPortion;
        fetchFood().then(function (food) {
          if (food) {
            updateFoodDisplay(food);
            if (food.available_grams < required) {
              showInsufficientFood(food.available_grams, required);
              return;
            }
          }
          feedNowForm.dataset.foodChecked = '1';
          if (feedNowForm.requestSubmit) feedNowForm.requestSubmit();
          else feedNowForm.dispatchEvent(new Event('submit', { cancelable: true }));
        });
      });
    }

    // Used after a successful Feed Now to restore the default (1 portion).
    portionGrid.resetPortions = function () {
      selectCard(1);
      if (customInput) customInput.value = '';
      setCustomError('');
      applyPortions(1);
    };
  }

  /* ---- Portion input (Schedule form) ---- */
  const schedulePortionsInput = document.querySelector('#schedule-form #portions');

  // Returns an error message for a typed portion count, or '' when valid.
  function portionInputError(raw, maxPortions) {
    const v = raw.trim();
    if (v === '') return 'Please enter the number of portions.';
    if (!/^\d+$/.test(v)) return 'Use whole numbers only (no letters, symbols, decimals or minus signs).';
    const p = parseInt(v, 10);
    if (p < 1) return 'Portions must be at least 1.';
    if (p > maxPortions) return 'Portions cannot be more than ' + maxPortions + '.';
    return '';
  }

  // Refresh the "Total" line under the schedule portion input. With
  // showEmptyError, an empty field is reported (used on submit).
  function syncSchedulePortions(showEmptyError) {
    if (!schedulePortionsInput) return true;
    const gramsPer = parseInt(schedulePortionsInput.dataset.gramsPerPortion || '40', 10);
    const max = parseInt(schedulePortionsInput.dataset.maxPortions || '25', 10);
    const errEl = document.getElementById('schedule-portions-error');
    const totalEl = document.getElementById('schedule-portions-total');
    const empty = schedulePortionsInput.value.trim() === '';
    const err = empty && !showEmptyError ? '' : portionInputError(schedulePortionsInput.value, max);
    if (errEl) { errEl.textContent = err; errEl.hidden = !err; }
    schedulePortionsInput.classList.toggle('is-invalid', !!err);
    if (totalEl) {
      const p = parseInt(schedulePortionsInput.value.trim(), 10);
      totalEl.textContent = (!err && !empty)
        ? p + ' portion' + (p === 1 ? '' : 's') + ' = ' + (p * gramsPer) + ' g'
        : '—';
    }
    return !err && !empty;
  }

  if (schedulePortionsInput) {
    schedulePortionsInput.addEventListener('input', function () { syncSchedulePortions(false); });
    // Registered before bindActionForms(), so an invalid value blocks the AJAX submit.
    schedulePortionsInput.form.addEventListener('submit', function (ev) {
      if (!syncSchedulePortions(true)) {
        ev.preventDefault();
        ev.stopImmediatePropagation();
        schedulePortionsInput.focus();
      }
    });
  }

  /* ---- Sidebar collapse toggle ---- */
  const sidebarToggle = document.getElementById('sidebar-toggle');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('spf-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}
    });
  }

  /* ---- Mobile nav drawer (small screens: sidebar slides in over content) ---- */
  const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
  const sidebarBackdrop = document.getElementById('sidebar-backdrop');
  function closeMobileNav() { document.body.classList.remove('mobile-nav-open'); }
  if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener('click', function () {
      document.body.classList.toggle('mobile-nav-open');
    });
  }
  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeMobileNav);
  }
  document.querySelectorAll('.navlink').forEach(function (link) {
    link.addEventListener('click', closeMobileNav);
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') closeMobileNav();
  });

  /* ---- Dark / light mode toggle ---- */
  const themeToggle = document.getElementById('theme-toggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      if (isDark) {
        document.documentElement.removeAttribute('data-theme');
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
      try { localStorage.setItem('spf-theme', isDark ? 'light' : 'dark'); } catch (e) {}
    });
  }

  /* ---- Account page: preference switches (mirror the topbar/sidebar toggles) ---- */
  const themeSwitch = document.getElementById('account-theme-switch');
  if (themeSwitch) {
    themeSwitch.checked = document.documentElement.getAttribute('data-theme') === 'dark';
    themeSwitch.addEventListener('change', function () {
      if (themeSwitch.checked) {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }
      try { localStorage.setItem('spf-theme', themeSwitch.checked ? 'dark' : 'light'); } catch (e) {}
    });
  }
  /* ---- Account page: pick a file -> upload immediately ---- */
  document.querySelectorAll('[data-avatar-input]').forEach(function (input) {
    input.addEventListener('change', function () {
      if (!input.files || !input.files[0]) return;
      if (input.form.requestSubmit) {
        input.form.requestSubmit();
      } else {
        input.form.submit();
      }
    });
  });

  const alertsBadgeSwitch = document.getElementById('account-alerts-switch');
  if (alertsBadgeSwitch) {
    alertsBadgeSwitch.checked = !document.body.classList.contains('alerts-badge-off');
    alertsBadgeSwitch.addEventListener('change', function () {
      document.body.classList.toggle('alerts-badge-off', !alertsBadgeSwitch.checked);
      try { localStorage.setItem('spf-alerts-badge', alertsBadgeSwitch.checked ? '1' : '0'); } catch (e) {}
    });
  }

  /* ---- Topbar user dropdown ---- */
  const userMenu = document.querySelector('[data-user-menu]');
  if (userMenu) {
    const trigger = userMenu.querySelector('[data-user-menu-trigger]');
    const dropdown = userMenu.querySelector('[data-user-menu-dropdown]');
    const closeMenu = () => { dropdown.hidden = true; userMenu.classList.remove('is-open'); };
    const openMenu = () => { dropdown.hidden = false; userMenu.classList.add('is-open'); };

    trigger.addEventListener('click', function (ev) {
      ev.stopPropagation();
      dropdown.hidden ? openMenu() : closeMenu();
    });
    document.addEventListener('click', function (ev) {
      if (!userMenu.contains(ev.target)) closeMenu();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') closeMenu();
    });
  }

  /* ---- Small-screen "more actions" menus (e.g. alert row kebab button) ---- */
  document.querySelectorAll('[data-more-menu]').forEach(function (menu) {
    const trigger = menu.querySelector('[data-more-trigger]');
    if (!trigger) return;
    trigger.addEventListener('click', function (ev) {
      ev.stopPropagation();
      const wasOpen = menu.classList.contains('is-open');
      document.querySelectorAll('[data-more-menu].is-open').forEach(m => m.classList.remove('is-open'));
      if (!wasOpen) menu.classList.add('is-open');
    });
  });
  document.addEventListener('click', function (ev) {
    document.querySelectorAll('[data-more-menu].is-open').forEach(function (menu) {
      if (!menu.contains(ev.target)) menu.classList.remove('is-open');
    });
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      document.querySelectorAll('[data-more-menu].is-open').forEach(m => m.classList.remove('is-open'));
    }
  });

  /* ------------------------------------------------------------------
   * Action forms: each POST endpoint under actions/ now answers with
   * JSON (see includes/functions.php respond()/respond_error()) instead
   * of redirecting the browser. A form declares which handler applies
   * via data-action; the handler patches only the DOM it's responsible
   * for - no page is ever re-fetched or re-parsed.
   * ------------------------------------------------------------------ */

  function scheduleEmptyHtml() {
    return '<p class="muted" data-schedule-empty>No schedules yet. Click "Add Schedule" to create one.</p>';
  }

  function handleScheduleDelete(json, form) {
    if (!json.success) { window.alert(json.message || 'Could not delete the schedule.'); return; }
    const item = form.closest('.schedule-item');
    const list = item ? item.parentElement : document.getElementById('schedule-list');
    if (item) item.remove();
    if (list && json.schedule_count === 0) list.innerHTML = scheduleEmptyHtml();
  }

  function buildScheduleItem(s) {
    const csrf = document.querySelector('#schedule-form input[name="csrf"]').value;
    const saveAction = document.getElementById('schedule-form').action;
    const deleteAction = saveAction.replace('schedule_save.php', 'schedule_delete.php');

    const el = document.createElement('div');
    el.className = 'schedule-item';
    el.dataset.scheduleId = s.id;
    el.dataset.feedTime = s.feed_time;
    el.dataset.feedDate = s.feed_date;
    el.dataset.portions = s.portions;
    el.innerHTML =
      '<div class="schedule-item__head">' +
      '<div class="schedule-item__icon">' + iconHtml('schedule') + '</div>' +
      '<div class="schedule-item__main">' +
      '<strong>' + escapeHtml(s.time_label) + '</strong>' +
      '<span class="muted">' + escapeHtml(s.date_label) + '</span>' +
      '</div>' +
      '</div>' +
      '<div class="schedule-item__stats">' +
      '<span>' + iconHtml('restaurant') + ' ' + s.portions + ' portion' + (s.portions > 1 ? 's' : '') + '</span>' +
      '<span>' + iconHtml('scale') + ' ' + s.grams + ' g</span>' +
      '<span class="badge ' + escapeHtml(s.status_class) + '">' + escapeHtml(s.status) + '</span>' +
      '</div>' +
      '<div class="schedule-item__actions">' +
      '<button type="button" class="btn btn--ghost btn--sm" data-schedule-edit>' + iconHtml('edit') + ' Edit</button>' +
      '<form method="post" action="' + escapeHtml(deleteAction) + '" data-action="schedule-delete" data-confirm="Delete this feeding schedule?">' +
      '<input type="hidden" name="csrf" value="' + escapeHtml(csrf) + '">' +
      '<input type="hidden" name="id" value="' + s.id + '">' +
      '<button class="btn btn--danger btn--sm" type="submit">' + iconHtml('delete') + ' Delete</button>' +
      '</form>' +
      '</div>';
    return el;
  }

  function handleScheduleSave(json, form) {
    if (!json.success) { showFormError(form, json.message); return; }
    clearFormError(form);
    const list = document.getElementById('schedule-list');
    if (list) {
      const placeholder = list.querySelector('[data-schedule-empty]');
      if (placeholder) placeholder.remove();

      const newItem = buildScheduleItem(json.schedule);
      const oldItem = list.querySelector('.schedule-item[data-schedule-id="' + json.schedule.id + '"]');
      if (oldItem) oldItem.replaceWith(newItem);
      else list.prepend(newItem);
      bindActionForms(newItem);
      bindScheduleEditButtons(newItem);
    }
    closeModal(document.getElementById('schedule-modal'));
  }

  function resetScheduleForm() {
    const form = document.getElementById('schedule-form');
    if (!form) return;
    clearFormError(form);
    form.querySelector('[data-field="id"]').value = '';
    form.querySelector('#feed_date').value = '';
    form.querySelector('#feed_time').value = '';
    form.querySelector('#portions').value = '';
    syncSchedulePortions(false);

    const modal = document.getElementById('schedule-modal');
    modal.querySelector('[data-modal-icon]').textContent = 'add_circle';
    modal.querySelector('[data-modal-title]').textContent = 'Add Feeding Schedule';
    modal.querySelector('[data-submit-icon]').textContent = 'add_circle';
    modal.querySelector('[data-submit-text]').textContent = 'Add Schedule';
  }

  function populateScheduleForm(row) {
    const form = document.getElementById('schedule-form');
    if (!form) return;
    clearFormError(form);
    const id = row.dataset.scheduleId;
    form.querySelector('[data-field="id"]').value = id;
    form.querySelector('#feed_date').value = row.dataset.feedDate || '';
    form.querySelector('#feed_time').value = row.dataset.feedTime || '';
    form.querySelector('#portions').value = row.dataset.portions || '';
    syncSchedulePortions(false);

    const modal = document.getElementById('schedule-modal');
    modal.querySelector('[data-modal-icon]').textContent = 'edit';
    modal.querySelector('[data-modal-title]').textContent = 'Edit Schedule #' + id;
    modal.querySelector('[data-submit-icon]').textContent = 'save';
    modal.querySelector('[data-submit-text]').textContent = 'Save Changes';
  }

  const scheduleAddBtn = document.querySelector('[data-schedule-add]');
  if (scheduleAddBtn) {
    scheduleAddBtn.addEventListener('click', function () {
      resetScheduleForm();
      openModal('schedule-modal');
    });
  }

  function bindScheduleEditButtons(root) {
    root.querySelectorAll('[data-schedule-edit]').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        populateScheduleForm(btn.closest('.schedule-item'));
        openModal('schedule-modal');
      });
    });
  }
  bindScheduleEditButtons(document);

  function handleFeedNow(json, form) {
    if (json.food) updateFoodDisplay(json.food);
    if (!json.success) {
      if (json.code === 'insufficient_food') {
        clearFormError(form);
        showInsufficientFood(json.available_grams, json.required_grams);
      } else {
        showFormError(form, json.message);
      }
      return;
    }
    clearFormError(form);
    syncSidebarUnreadBadge(json.unread_count);

    if (form.id === 'feed-now-form' && portionGrid && portionGrid.resetPortions) {
      portionGrid.resetPortions();
    }
  }

  function handleAlertRead(json, form) {
    if (!json.success) { window.alert(json.message || 'Could not update the alert.'); return; }
    if (json.id === -1) {
      document.querySelectorAll('.alert-item.is-unread').forEach(function (item) {
        item.classList.remove('is-unread');
        const f = item.querySelector('form[data-action="alert-read"]');
        if (f) f.remove();
      });
    } else {
      const item = document.querySelector('.alert-item[data-alert-id="' + json.id + '"]');
      if (item) item.classList.remove('is-unread');
      form.remove();
    }
    const countEl = document.getElementById('unread-count');
    if (countEl) countEl.textContent = json.unread_count + ' unread';
    syncSidebarUnreadBadge(json.unread_count);
  }

  function updateAlertsSelectionState() {
    const selectAll = document.getElementById('alerts-select-all');
    const deleteSelectedBtn = document.getElementById('alerts-delete-selected');
    const boxes = document.querySelectorAll('.alert-select-cb');
    const checked = document.querySelectorAll('.alert-select-cb:checked');
    if (deleteSelectedBtn) deleteSelectedBtn.disabled = checked.length === 0;
    if (selectAll) selectAll.checked = boxes.length > 0 && checked.length === boxes.length;
  }

  const alertsSelectAll = document.getElementById('alerts-select-all');
  if (alertsSelectAll) {
    alertsSelectAll.addEventListener('change', function () {
      document.querySelectorAll('.alert-select-cb').forEach(function (cb) { cb.checked = alertsSelectAll.checked; });
      updateAlertsSelectionState();
    });
  }
  document.querySelectorAll('.alert-select-cb').forEach(function (cb) {
    cb.addEventListener('change', updateAlertsSelectionState);
  });

  function handleAlertDelete(json, form) {
    if (!json.success) { window.alert(json.message || 'Could not delete the notification(s).'); return; }
    (json.ids || []).forEach(function (id) {
      const item = document.querySelector('.alert-item[data-alert-id="' + id + '"]');
      if (item) item.remove();
    });
    // Drop any section (New / Today / Earlier) left with no items in it.
    document.querySelectorAll('.alerts-section').forEach(function (section) {
      if (!section.querySelector('.alert-item')) section.remove();
    });
    const list = document.getElementById('alerts-list');
    if (list && !list.querySelector('.alert-item') && !list.querySelector('[data-alerts-empty]')) {
      const p = document.createElement('p');
      p.className = 'muted';
      p.setAttribute('data-alerts-empty', '');
      p.textContent = "No alerts. You're all caught up.";
      list.prepend(p);
    }
    const countEl = document.getElementById('unread-count');
    if (countEl) countEl.textContent = json.unread_count + ' unread';
    syncSidebarUnreadBadge(json.unread_count);
    updateAlertsSelectionState();
  }

  const seePreviousBtn = document.getElementById('alerts-see-previous');
  if (seePreviousBtn) {
    seePreviousBtn.addEventListener('click', function () {
      document.querySelectorAll('#alerts-list [data-alerts-more]').forEach(function (item) {
        item.hidden = false;
      });
      seePreviousBtn.remove();
    });
  }

  function handleFoodLevelSet(json, form) {
    if (!json.success) { showFormError(form, json.message); return; }
    clearFormError(form);
    const pctEl = document.getElementById('food-level-current-pct');
    const labelEl = document.getElementById('food-level-current-label');
    if (pctEl) pctEl.textContent = json.level_percent + '%';
    if (labelEl) labelEl.textContent = json.label;
    const input = form.querySelector('#level_percent');
    if (input) input.value = json.level_percent;
    syncSidebarUnreadBadge(json.unread_count);
  }

  function handleProfileUpdate(json, form) {
    if (!json.success) { showFormError(form, json.message); return; }
    clearFormError(form);
    const fullName = json.first_name + ' ' + json.last_name;
    const initial = json.first_name.charAt(0).toUpperCase();
    document.querySelectorAll('[data-user-fullname]').forEach(el => { el.textContent = fullName; });
    document.querySelectorAll('[data-user-avatar-initial]').forEach(el => { el.textContent = initial; });
    document.querySelectorAll('[data-user-first-name]').forEach(el => { el.textContent = json.first_name; });
    document.querySelectorAll('[data-user-last-name]').forEach(el => { el.textContent = json.last_name; });
    document.querySelectorAll('[data-user-username]').forEach(el => { el.textContent = json.username; });
    document.querySelectorAll('[data-user-email]').forEach(el => { el.textContent = json.email; });
    closeModal(document.getElementById('edit-profile-modal'));
  }

  function handlePasswordChange(json, form) {
    if (!json.success) { showFormError(form, json.message); return; }
    clearFormError(form);
    form.reset();
    closeModal(document.getElementById('change-password-modal'));
  }

  function handleAvatarUpload(json, form) {
    if (!json.success) { showFormError(form, json.message); return; }
    clearFormError(form);
    document.querySelectorAll('[data-user-avatar-img]').forEach(function (img) {
      img.src = json.avatar_url + '?t=' + Date.now();
      img.hidden = false;
    });
    document.querySelectorAll('[data-user-avatar-initial]').forEach(function (span) {
      span.hidden = true;
    });
  }

  const actionHandlers = {
    'schedule-delete': handleScheduleDelete,
    'schedule-save': handleScheduleSave,
    'feed-now': handleFeedNow,
    'alert-read': handleAlertRead,
    'alert-delete': handleAlertDelete,
    'food-level-set': handleFoodLevelSet,
    'profile-update': handleProfileUpdate,
    'password-change': handlePasswordChange,
    'avatar-upload': handleAvatarUpload,
  };

  async function runAction(form) {
    const handler = actionHandlers[form.dataset.action];
    const buttons = Array.from(form.querySelectorAll('button[type=submit]'));
    buttons.forEach(function (b) {
      b.dataset.originalHtml = b.innerHTML;
      b.disabled = true;
      if (b.dataset.busyText) b.textContent = b.dataset.busyText;
    });
    document.body.classList.add('is-busy');

    let json;
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'fetch' },
        body: new FormData(form),
      });
      json = await res.json();
    } catch (e) {
      // Not JSON, or a network hiccup: fall back to a real submit so the
      // action isn't silently lost (also covers a session-expired redirect
      // to the login page, which the fallback's real navigation will show).
      document.body.classList.remove('is-busy');
      buttons.forEach(function (b) {
        b.disabled = false;
        b.innerHTML = b.dataset.originalHtml;
        delete b.dataset.originalHtml;
      });
      form.submit();
      return;
    }

    document.body.classList.remove('is-busy');
    buttons.forEach(function (b) {
      b.disabled = false;
      b.innerHTML = b.dataset.originalHtml;
      delete b.dataset.originalHtml;
    });

    if (handler) handler(json, form);
  }

  function bindActionForms(root) {
    root.querySelectorAll('form[data-action]').forEach(function (form) {
      if (form.dataset.ajaxBound) return;
      form.dataset.ajaxBound = '1';
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        // Bulk-action forms (e.g. "Delete Selected") carry no ids of their
        // own; collect them from whichever checkboxes are currently checked
        // right before submitting.
        if (form.dataset.collectChecked) {
          form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
          const checked = document.querySelectorAll(form.dataset.collectChecked + ':checked');
          if (!checked.length) return;
          checked.forEach(function (cb) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            form.appendChild(input);
          });
        }
        if (form.dataset.confirm) {
          showConfirm(form.dataset.confirm, function () { runAction(form); });
        } else {
          runAction(form);
        }
      });
    });
  }
  bindActionForms(document);

  /* ---- Completed Feedings chart (Dashboard): date-range picker -> single bar ---- */
  const completedCard = document.getElementById('completed-feedings');
  if (completedCard) {
    const fromInput = document.getElementById('cf-from');
    const toInput = document.getElementById('cf-to');
    const bar = document.getElementById('cf-bar');
    const countEl = document.getElementById('cf-count');
    const labelEl = document.getElementById('cf-label');
    const endpoint = completedCard.dataset.endpoint;

    const trigger = document.getElementById('cf-trigger');
    const triggerLabel = document.getElementById('cf-trigger-label');
    const panel = document.getElementById('cf-panel');
    const fieldFrom = document.getElementById('cf-field-from');
    const fieldTo = document.getElementById('cf-field-to');
    const monthEl = document.getElementById('cf-month');
    const daysEl = document.getElementById('cf-days');
    const prevBtn = document.getElementById('cf-prev');
    const nextBtn = document.getElementById('cf-next');

    const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const todayDate = parseISO(fromInput.max);

    function parseISO(s) {
      const [y, m, d] = s.split('-').map(Number);
      return new Date(y, m - 1, d);
    }
    function toISO(d) {
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function fmt(d) {
      return String(d.getMonth() + 1).padStart(2, '0') + '/' + String(d.getDate()).padStart(2, '0') + '/' + d.getFullYear();
    }

    let rangeFrom = parseISO(fromInput.value);
    let rangeTo = parseISO(toInput.value);
    let viewMonth = new Date(rangeTo.getFullYear(), rangeTo.getMonth(), 1);
    let pickingSecond = false;

    async function refreshCompletedChart() {
      const params = new URLSearchParams({ from: fromInput.value, to: toInput.value });
      try {
        const res = await fetch(endpoint + '?' + params.toString(), { headers: { 'X-Requested-With': 'fetch' } });
        const json = await res.json();
        if (!json.success) return;
        bar.style.height = json.percent + '%';
        countEl.textContent = json.count;
        labelEl.textContent = 'Completed Feeding' + (json.count === 1 ? '' : 's');
      } catch (e) {
        // Transient network issue: leave the last known values showing.
      }
    }

    function updateTriggerLabel() {
      triggerLabel.textContent = fmt(rangeFrom) === fmt(rangeTo) ? fmt(rangeFrom) : fmt(rangeFrom) + ' – ' + fmt(rangeTo);
    }
    function updateFields() {
      fieldFrom.textContent = fmt(rangeFrom);
      fieldTo.textContent = fmt(rangeTo);
    }

    function renderCalendar() {
      monthEl.textContent = MONTH_NAMES[viewMonth.getMonth()] + ' ' + viewMonth.getFullYear();
      nextBtn.disabled = viewMonth.getFullYear() === todayDate.getFullYear() && viewMonth.getMonth() === todayDate.getMonth();
      daysEl.innerHTML = '';

      const firstOfMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1);
      const daysInMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 0).getDate();

      for (let i = 0; i < firstOfMonth.getDay(); i++) {
        daysEl.appendChild(document.createElement('span'));
      }
      for (let day = 1; day <= daysInMonth; day++) {
        const d = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), day);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'daterange__day';
        btn.textContent = day;
        if (d > todayDate) btn.disabled = true;
        if (rangeFrom.getTime() === rangeTo.getTime() && d.getTime() === rangeFrom.getTime()) {
          btn.classList.add('is-single');
        } else {
          if (d.getTime() === rangeFrom.getTime()) btn.classList.add('is-start');
          if (d.getTime() === rangeTo.getTime()) btn.classList.add('is-end');
          if (d > rangeFrom && d < rangeTo) btn.classList.add('is-inrange');
        }
        btn.addEventListener('click', function () { onDayClick(d); });
        daysEl.appendChild(btn);
      }
    }

    function onDayClick(d) {
      if (!pickingSecond) {
        rangeFrom = d;
        rangeTo = d;
        pickingSecond = true;
        updateFields();
        renderCalendar();
        return;
      }
      if (d < rangeFrom) { rangeTo = rangeFrom; rangeFrom = d; } else { rangeTo = d; }
      pickingSecond = false;
      fromInput.value = toISO(rangeFrom);
      toInput.value = toISO(rangeTo);
      updateFields();
      updateTriggerLabel();
      renderCalendar();
      refreshCompletedChart();
      closePanel();
    }

    function openPanel() {
      viewMonth = new Date(rangeTo.getFullYear(), rangeTo.getMonth(), 1);
      pickingSecond = false;
      updateFields();
      renderCalendar();
      panel.hidden = false;
      trigger.classList.add('is-open');
    }
    function closePanel() {
      panel.hidden = true;
      trigger.classList.remove('is-open');
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (panel.hidden) openPanel(); else closePanel();
    });
    panel.addEventListener('click', function (e) { e.stopPropagation(); });
    prevBtn.addEventListener('click', function () {
      viewMonth.setMonth(viewMonth.getMonth() - 1);
      renderCalendar();
    });
    nextBtn.addEventListener('click', function () {
      viewMonth.setMonth(viewMonth.getMonth() + 1);
      renderCalendar();
    });
    document.addEventListener('click', function (e) {
      if (!panel.hidden && !completedCard.contains(e.target)) closePanel();
    });

    updateTriggerLabel();
  }

  /* ---- Table pagination (Feeding History) ---- */
  const historyTable = document.getElementById('history-table');
  const historyPager = document.getElementById('history-pager');
  if (historyTable && historyPager) {
    const rows = Array.from(historyTable.querySelectorAll('tbody tr'));
    const cardRows = Array.from(document.querySelectorAll('#history-list [data-history-row]'));
    const summaryEl = document.getElementById('history-pager-summary');
    const sizeSelect = document.getElementById('history-pager-size');
    const prevBtn = document.getElementById('history-pager-prev');
    const nextBtn = document.getElementById('history-pager-next');
    const pagesEl = document.getElementById('history-pager-pages');

    let pageSize = parseInt(sizeSelect.value, 10) || 10;
    let currentPage = 1;

    // "Rows per page" lives in the footer pager on wide screens; on phones
    // it moves up above the table instead, so this one control is
    // physically relocated rather than duplicated.
    const perpageWrap = document.getElementById('history-perpage-wrap');
    const perpageMobileSlot = document.getElementById('history-perpage-mobile-slot');
    const perpageDesktopParent = perpageWrap ? perpageWrap.parentNode : null;
    const perpageDesktopNextSibling = perpageWrap ? perpageWrap.nextSibling : null;
    if (perpageWrap && perpageMobileSlot && perpageDesktopParent) {
      const phoneQuery = window.matchMedia('(max-width: 480px)');
      const placePerpageControl = function () {
        if (phoneQuery.matches) {
          if (perpageWrap.parentNode !== perpageMobileSlot) perpageMobileSlot.appendChild(perpageWrap);
        } else if (perpageWrap.parentNode !== perpageDesktopParent) {
          perpageDesktopParent.insertBefore(perpageWrap, perpageDesktopNextSibling);
        }
      };
      placePerpageControl();
      phoneQuery.addEventListener('change', placePerpageControl);
    }

    function totalPages() { return Math.max(1, Math.ceil(rows.length / pageSize)); }

    function renderHistoryPage() {
      const total = rows.length;
      const pages = totalPages();
      currentPage = Math.min(currentPage, pages);
      const start = (currentPage - 1) * pageSize;
      const end = Math.min(start + pageSize, total);

      rows.forEach(function (row, i) {
        const visible = i >= start && i < end;
        row.hidden = !visible;
        if (cardRows[i]) cardRows[i].hidden = !visible;
      });

      summaryEl.textContent = total === 0
        ? 'Showing 0 of 0 entries'
        : 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' entries';

      pagesEl.innerHTML = '';
      const current = document.createElement('span');
      current.className = 'tablepager__page is-active';
      current.textContent = String(currentPage);
      pagesEl.appendChild(current);

      prevBtn.disabled = currentPage <= 1;
      nextBtn.disabled = currentPage >= pages;
    }

    sizeSelect.addEventListener('change', function () {
      pageSize = parseInt(sizeSelect.value, 10) || 10;
      currentPage = 1;
      renderHistoryPage();
    });
    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) { currentPage--; renderHistoryPage(); }
    });
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages()) { currentPage++; renderHistoryPage(); }
    });

    renderHistoryPage();
  }

  /* ---- Auto-hide success/info flash banners (classic full-page-load path) ---- */
  const flash = document.querySelector('.flash');
  if (flash) {
    setTimeout(function () {
      flash.style.transition = 'opacity .4s';
      flash.style.opacity = '0';
      setTimeout(() => flash.remove(), 400);
    }, 6000);
  }
})();
