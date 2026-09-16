/* Khamis Computers — shared front-end helpers.
   Additional scripts (POS, shop, offline sync) are added in later pieces. */
(function () {
  'use strict';

  var toggle = document.querySelector('.nav-toggle');
  var nav = toggle ? document.getElementById(toggle.getAttribute('aria-controls')) : null;
  var backdrop = document.querySelector('.nav-backdrop');
  var actions = document.querySelector('.nav-actions');
  var lastFocused = null;

  function setNavigation(open) {
    if (!toggle || !nav || !backdrop) return;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    nav.classList.toggle('is-open', open);
    backdrop.classList.toggle('is-open', open);
    document.body.classList.toggle('nav-open', open);
    if (actions) {
      actions.inert = open;
      if (open) actions.setAttribute('aria-hidden', 'true');
      else actions.removeAttribute('aria-hidden');
    }
    if (open) {
      lastFocused = document.activeElement;
      var firstLink = nav.querySelector('a');
      if (firstLink) firstLink.focus();
    } else if (lastFocused && document.contains(lastFocused)) {
      lastFocused.focus();
    }
  }

  if (toggle && nav && backdrop) {
    toggle.addEventListener('click', function () {
      setNavigation(toggle.getAttribute('aria-expanded') !== 'true');
    });
    backdrop.addEventListener('click', function () { setNavigation(false); });
    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) setNavigation(false);
    });
    document.addEventListener('keydown', function (event) {
      if (toggle.getAttribute('aria-expanded') !== 'true') return;
      if (event.key === 'Escape') {
        setNavigation(false);
        return;
      }
      if (event.key === 'Tab') {
        var links = Array.prototype.slice.call(nav.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])'));
        if (!links.length) return;
        var first = links[0];
        var last = links[links.length - 1];
        if (event.shiftKey && document.activeElement === toggle) {
          event.preventDefault();
          last.focus();
        } else if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          toggle.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          toggle.focus();
        } else if (!event.shiftKey && document.activeElement === toggle) {
          event.preventDefault();
          first.focus();
        }
      }
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 760 && toggle.getAttribute('aria-expanded') === 'true') setNavigation(false);
    });
  }

  // Preserve column meaning when tables become mobile data cards. The source
  // table remains semantic; each cell receives its matching header as a label.
  document.querySelectorAll('table.table').forEach(function (table) {
    var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (header) {
      return header.textContent.trim();
    });
    if (!headers.length) return;
    table.querySelectorAll('tbody tr').forEach(function (row) {
      Array.prototype.forEach.call(row.children, function (cell, index) {
        if (cell.tagName !== 'TD' || cell.hasAttribute('colspan')) return;
        cell.setAttribute('data-label', headers[index] || 'Details');
      });
    });
    table.classList.add('mobile-cards');
  });

  var generatedId = 0;

  function humanLabel(control) {
    var source = control.getAttribute('placeholder') || control.getAttribute('name') || control.id || control.type || 'Field';
    source = source.replace(/\[\]/g, '').replace(/^co-/, '').replace(/^fld-/, '').replace(/[-_]+/g, ' ').trim();
    if (/^0(?:\.00)?$/.test(source) || source.length < 2) source = control.type || 'Field';
    return source.charAt(0).toUpperCase() + source.slice(1);
  }

  function controlId(control) {
    if (control.id) return control.id;
    generatedId += 1;
    var base = (control.getAttribute('name') || control.type || 'field').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase();
    control.id = 'field-' + (base || 'control') + '-' + generatedId;
    return control.id;
  }

  function hasLabel(control) {
    if (control.closest('label') || control.getAttribute('aria-label') || control.getAttribute('aria-labelledby')) return true;
    return !!document.querySelector('label[for="' + CSS.escape(control.id || '') + '"]');
  }

  function enhanceFormControls(root) {
    root.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(function (control) {
      if (control.dataset.labelReady === 'true') return;
      var id = controlId(control);
      if (!hasLabel(control)) {
        var field = control.closest('.field');
        var caption = field ? Array.prototype.find.call(field.children, function (child) {
          return child.tagName === 'SPAN' && !child.classList.contains('hint');
        }) : null;
        if (caption && !caption.dataset.labelUsed) {
          if (caption.querySelector('a, button, input, select, textarea')) {
            caption.id = caption.id || id + '-label';
            control.setAttribute('aria-labelledby', caption.id);
            caption.dataset.labelUsed = 'true';
          } else {
            var label = document.createElement('label');
            label.setAttribute('for', id);
            label.className = caption.className;
            label.innerHTML = caption.innerHTML;
            caption.replaceWith(label);
            label.dataset.labelUsed = 'true';
          }
        } else {
          var hiddenLabel = document.createElement('label');
          hiddenLabel.className = 'sr-only';
          hiddenLabel.setAttribute('for', id);
          hiddenLabel.textContent = control.getAttribute('aria-label') || humanLabel(control);
          control.parentNode.insertBefore(hiddenLabel, control);
        }
      }
      control.dataset.labelReady = 'true';
    });
  }

  function showFieldError(control) {
    control.setAttribute('aria-invalid', 'true');
    var id = controlId(control) + '-error';
    var error = document.getElementById(id);
    if (!error) {
      error = document.createElement('span');
      error.id = id;
      error.className = 'field-error';
      control.insertAdjacentElement('afterend', error);
    }
    error.textContent = control.validationMessage;
    var describedBy = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (describedBy.indexOf(id) === -1) describedBy.push(id);
    control.setAttribute('aria-describedby', describedBy.join(' '));
  }

  function clearFieldError(control) {
    control.removeAttribute('aria-invalid');
    var errorId = controlId(control) + '-error';
    var error = document.getElementById(errorId);
    if (error) error.remove();
    var describedBy = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (id) {
      return id && id !== errorId;
    });
    if (describedBy.length) control.setAttribute('aria-describedby', describedBy.join(' '));
    else control.removeAttribute('aria-describedby');
  }

  enhanceFormControls(document);
  document.addEventListener('invalid', function (event) {
    showFieldError(event.target);
    setTimeout(function () {
      var first = document.querySelector('[aria-invalid="true"]');
      if (first) first.focus();
    }, 0);
  }, true);
  document.addEventListener('input', function (event) {
    if (event.target.matches('input, select, textarea') && event.target.validity.valid) clearFieldError(event.target);
  });
  document.addEventListener('change', function (event) {
    if (event.target.matches('input, select, textarea') && event.target.validity.valid) clearFieldError(event.target);
  });

  new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      mutation.addedNodes.forEach(function (node) {
        if (node.nodeType !== 1) return;
        if (node.matches('input:not([type="hidden"]), select, textarea')) enhanceFormControls(node.parentNode || document);
        else if (node.querySelector('input:not([type="hidden"]), select, textarea')) enhanceFormControls(node);
      });
    });
  }).observe(document.body, { childList: true, subtree: true });

  /* Shared feedback: accessible confirmations, lightweight toasts, and a
     single-submit guard for server-bound forms. */
  var KC = window.KC = window.KC || {};
  var activeConfirmation = null;

  function confirmationDialog() {
    var dialog = document.getElementById('kc-confirm-dialog');
    if (dialog) return dialog;
    dialog = document.createElement('dialog');
    dialog.id = 'kc-confirm-dialog';
    dialog.className = 'confirm-dialog';
    dialog.setAttribute('aria-labelledby', 'kc-confirm-title');
    dialog.setAttribute('aria-describedby', 'kc-confirm-message');
    dialog.innerHTML =
      '<form method="dialog" class="confirm-dialog-card">' +
        '<div class="confirm-dialog-icon" aria-hidden="true">!</div>' +
        '<div class="confirm-dialog-copy">' +
          '<h2 id="kc-confirm-title">Confirm action</h2>' +
          '<p id="kc-confirm-message"></p>' +
        '</div>' +
        '<div class="confirm-dialog-actions">' +
          '<button class="btn btn-outline" value="cancel">Cancel</button>' +
          '<button class="btn btn-danger" value="confirm" data-confirm-button>Confirm</button>' +
        '</div>' +
      '</form>';
    document.body.appendChild(dialog);
    dialog.addEventListener('close', function () {
      if (!activeConfirmation) return;
      var pending = activeConfirmation;
      activeConfirmation = null;
      pending.resolve(dialog.returnValue === 'confirm');
      if (pending.trigger && document.contains(pending.trigger)) pending.trigger.focus();
    });
    return dialog;
  }

  KC.confirm = function (options) {
    options = typeof options === 'string' ? { message: options } : (options || {});
    if (activeConfirmation) return Promise.resolve(false);
    var dialog = confirmationDialog();
    var confirmButton = dialog.querySelector('[data-confirm-button]');
    dialog.querySelector('#kc-confirm-title').textContent = options.title || 'Confirm action';
    dialog.querySelector('#kc-confirm-message').textContent = options.message || 'Are you sure you want to continue?';
    confirmButton.textContent = options.action || 'Confirm';
    confirmButton.classList.toggle('btn-danger', options.tone !== 'primary');
    confirmButton.classList.toggle('btn-primary', options.tone === 'primary');
    dialog.returnValue = '';
    return new Promise(function (resolve) {
      activeConfirmation = { resolve: resolve, trigger: options.trigger || document.activeElement };
      dialog.showModal();
      dialog.querySelector('[value="cancel"]').focus();
    });
  };

  var toastTimer = null;
  KC.toast = function (message, type) {
    var region = document.getElementById('kc-toast-region');
    if (!region) {
      region = document.createElement('div');
      region.id = 'kc-toast-region';
      region.className = 'toast-region';
      region.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
      region.setAttribute('aria-atomic', 'true');
      document.body.appendChild(region);
    }
    region.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
    region.innerHTML = '';
    var toast = document.createElement('div');
    toast.className = 'kc-toast show' + (type ? ' toast-' + type : '');
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    region.appendChild(toast);
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () { if (toast.parentNode) toast.remove(); }, 220);
    }, type === 'error' ? 4500 : 2600);
  };

  function confirmationOptions(source, trigger) {
    var triggerAction = trigger && (trigger.getAttribute('data-confirm-action') ||
      (trigger.tagName === 'INPUT' ? trigger.value : trigger.textContent.trim()));
    return {
      title: source.getAttribute('data-confirm-title') || 'Confirm action',
      message: source.getAttribute('data-confirm'),
      action: source.getAttribute('data-confirm-action') || triggerAction || 'Confirm',
      tone: source.getAttribute('data-confirm-tone') || 'danger',
      trigger: trigger
    };
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    var submitter = event.submitter;
    var source = submitter && submitter.hasAttribute('data-confirm') ? submitter : form;
    var message = source.getAttribute('data-confirm');
    if (message && form.dataset.confirmed !== 'true') {
      event.preventDefault();
      KC.confirm(confirmationOptions(source, submitter || form)).then(function (confirmed) {
        if (!confirmed) return;
        form.dataset.confirmed = 'true';
        form.requestSubmit(submitter || undefined);
      });
      return;
    }
    if (form.dataset.confirmed === 'true') delete form.dataset.confirmed;
  }, true);

  // Run the submission lock during bubbling, after form-level JavaScript has
  // had a chance to prevent AJAX and client-validation submissions.
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    var submitter = event.submitter;
    var method = ((submitter && submitter.formMethod) || form.method || 'get').toLowerCase();
    if (method !== 'post') return;
    if (form.dataset.submitting === 'true') {
      event.preventDefault();
      return;
    }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    var button = submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    if (!button) return;
    // Keep the submitter enabled so named buttons remain in the native POST
    // payload. The form-level lock above blocks every repeated submission.
    button.setAttribute('aria-disabled', 'true');
    button.classList.add('is-loading');
    if (button.tagName === 'BUTTON') {
      button.dataset.originalHtml = button.innerHTML;
      button.innerHTML = '<span class="button-spinner" aria-hidden="true"></span>' + (button.getAttribute('data-loading-label') || 'Working…');
    }
  });

  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-confirm]');
    if (!link) return;
    event.preventDefault();
    KC.confirm(confirmationOptions(link, link)).then(function (confirmed) {
      if (confirmed) window.location.assign(link.href);
    });
  });

  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-submitting="true"]').forEach(function (form) {
      delete form.dataset.submitting;
      form.removeAttribute('aria-busy');
      form.querySelectorAll('.is-loading').forEach(function (button) {
        button.removeAttribute('aria-disabled');
        button.classList.remove('is-loading');
        if (button.dataset.originalHtml) {
          button.innerHTML = button.dataset.originalHtml;
          delete button.dataset.originalHtml;
        }
      });
    });
  });

  // Auto-dismiss flash alerts after 5 seconds (but keep them hoverable).
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 600);
    }, 5000);
  });
})();
