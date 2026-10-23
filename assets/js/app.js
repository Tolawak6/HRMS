/* ESS HRMS — progressive enhancement. No inline scripts (CSP-friendly). */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initConfirmDialogs();
    initPasswordToggles();
    initLoadingStates();
    initLeaveEstimate();
    initAutoSubmit();
    initFlashDismiss();
    initPrintButtons();
    initSidebarClose();
  });

  function initConfirmDialogs() {
    var modalEl = document.getElementById('confirmModal');
    if (!modalEl || !window.bootstrap) return;

    var modal = new bootstrap.Modal(modalEl);
    var titleEl = document.getElementById('confirmTitle');
    var messageEl = document.getElementById('confirmMessage');
    var confirmBtn = document.getElementById('confirmButton');
    var pendingForm = null;

    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!form.dataset.confirm || form.dataset.confirmed === '1') return;
      event.preventDefault();
      pendingForm = form;
      titleEl.textContent = form.dataset.confirmTitle || 'Confirm action';
      messageEl.textContent = form.dataset.confirm;
      confirmBtn.textContent = form.dataset.confirmButton || 'Confirm';
      confirmBtn.className = 'btn ' + (form.dataset.confirmVariant === 'success' ? 'btn-success' : 'btn-danger');
      modal.show();
    });

    confirmBtn.addEventListener('click', function () {
      if (!pendingForm) return;
      pendingForm.dataset.confirmed = '1';
      modal.hide();
      pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
      pendingForm = null;
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      pendingForm = null;
    });
  }

  function initPasswordToggles() {
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
      var target = document.getElementById(button.getAttribute('data-toggle-password'));
      if (!target) return;
      button.addEventListener('click', function () {
        var visible = target.getAttribute('type') === 'text';
        target.setAttribute('type', visible ? 'password' : 'text');
        button.setAttribute('aria-pressed', visible ? 'false' : 'true');
        button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
        var icon = button.querySelector('.bi');
        if (icon) {
          icon.classList.toggle('bi-eye', visible);
          icon.classList.toggle('bi-eye-slash', !visible);
        }
      });
    });
  }

  function initLoadingStates() {
    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      if ((form.getAttribute('method') || 'GET').toUpperCase() === 'GET') return;
      window.setTimeout(function () {
        var submitter = event.submitter;
        if (submitter && submitter.classList.contains('btn')) {
          submitter.setAttribute('aria-busy', 'true');
          submitter.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">Submitting</span>';
          submitter.disabled = true;
        }
      }, 20);
    });
  }

  function initLeaveEstimate() {
    document.querySelectorAll('[data-leave-form]').forEach(function (form) {
      var start = form.querySelector('[name="start_date"]');
      var end = form.querySelector('[name="end_date"]');
      var output = form.querySelector('[data-working-days]');
      if (!start || !end || !output) return;
      var update = function () {
        if (!start.value || !end.value) {
          output.textContent = '—';
          return;
        }
        var from = new Date(start.value + 'T00:00:00');
        var to = new Date(end.value + 'T00:00:00');
        if (isNaN(from) || isNaN(to) || to < from) {
          output.textContent = '—';
          return;
        }
        var days = 0;
        var cursor = new Date(from);
        var guard = 0;
        while (cursor <= to && guard < 400) {
          var day = cursor.getDay();
          if (day !== 0 && day !== 6) days++;
          cursor.setDate(cursor.getDate() + 1);
          guard++;
        }
        output.textContent = days + (days === 1 ? ' working day' : ' working days');
      };
      start.addEventListener('change', update);
      end.addEventListener('change', update);
      update();
    });
  }

  function initAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach(function (select) {
      select.addEventListener('change', function () {
        if (select.form) select.form.requestSubmit ? select.form.requestSubmit() : select.form.submit();
      });
    });
  }

  function initFlashDismiss() {
    document.querySelectorAll('.alert-auto').forEach(function (alert) {
      var timer = window.setTimeout(function () {
        if (!window.bootstrap) return;
        bootstrap.Alert.getOrCreateInstance(alert).close();
      }, 8000);
      alert.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
      alert.addEventListener('focusin', function () { window.clearTimeout(timer); });
    });
  }

  function initPrintButtons() {
    document.querySelectorAll('[data-print]').forEach(function (button) {
      button.addEventListener('click', function () { window.print(); });
    });
  }

  function initSidebarClose() {
    var sidebar = document.getElementById('appSidebar');
    if (!sidebar || !window.bootstrap) return;
    if (window.matchMedia('(min-width: 992px)').matches) return;
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        var instance = bootstrap.Offcanvas.getInstance(sidebar);
        if (instance) instance.hide();
      });
    });
  }
})();

/* Keep the position list aligned with the selected department. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-department-select]').forEach(function (departmentSelect) {
      var form = departmentSelect.closest('form');
      var positionSelect = form ? form.querySelector('[data-position-select]') : null;
      if (!positionSelect) return;
      var allOptions = Array.prototype.slice.call(positionSelect.querySelectorAll('option'));
      var apply = function () {
        var department = departmentSelect.value;
        var current = positionSelect.value;
        positionSelect.innerHTML = '';
        allOptions.forEach(function (option) {
          if (!option.value || option.getAttribute('data-department') === department) {
            positionSelect.appendChild(option);
          }
        });
        var stillValid = Array.prototype.some.call(positionSelect.options, function (option) {
          return option.value === current && option.value !== '';
        });
        positionSelect.value = stillValid ? current : '';
        positionSelect.disabled = department === '';
      };
      departmentSelect.addEventListener('change', apply);
      if (positionSelect.getAttribute('data-locked') !== '1') {
        apply();
      }
    });
  });
})();
