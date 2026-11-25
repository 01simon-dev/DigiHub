/* DigiHub - vanilla JS layer for feedback, theme and interactions */
(function () {
  'use strict';

  const TOAST_ICONS = {
    success: 'fa-circle-check',
    error: 'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info: 'fa-circle-info'
  };

  function toastStack() {
    let stack = document.getElementById('toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'toast-stack';
      stack.className = 'toast-stack';
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }
    return stack;
  }

  function showToast(message, type) {
    if (!message) return;
    type = TOAST_ICONS[type] ? type : 'info';
    const stack = toastStack();
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.innerHTML = '<i class="fa-solid ' + TOAST_ICONS[type] + '" aria-hidden="true"></i>';

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;

    const close = document.createElement('button');
    close.className = 'toast-close';
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

    const dismiss = () => {
      if (toast.classList.contains('leaving')) return;
      toast.classList.add('leaving');
      setTimeout(() => toast.remove(), 200);
    };

    close.addEventListener('click', dismiss);
    toast.append(icon, body, close);
    stack.appendChild(toast);
    setTimeout(dismiss, 4200);
    return toast;
  }

  window.DigiToast = showToast;

  /* Splash screen shown on page visits, hidden once the page is ready. */
  function initSplash() {
    const splash = document.getElementById('site-splash');
    const root = document.documentElement;
    if (!splash) { root.classList.remove('show-splash'); return; }

    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const delay = prefersReduced ? 100 : 900;
    let done = false;
    const hide = () => {
      if (done) return;
      done = true;
      splash.classList.add('is-hidden');
      root.classList.remove('show-splash');
      setTimeout(() => splash.remove(), prefersReduced ? 50 : 450);
    };

    if (document.readyState === 'complete') {
      setTimeout(hide, delay);
    } else {
      window.addEventListener('load', () => setTimeout(hide, delay), { once: true });
    }
    setTimeout(hide, 3000);
  }

  /* Dark mode, persisted between visits. */
  function initTheme() {
    const button = document.querySelector('[data-theme-toggle]');
    const icon = button && button.querySelector('[data-theme-icon]');
    if (!button || !icon) return;

    const themeColor = document.querySelector('meta[name="theme-color"]');

    function syncTheme() {
      const dark = document.documentElement.dataset.theme === 'dark';
      icon.className = 'fa-solid ' + (dark ? 'fa-sun' : 'fa-moon');
      button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      button.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
      if (themeColor) themeColor.content = dark ? '#17120e' : '#6f4e37';
    }

    syncTheme();
    button.addEventListener('click', () => {
      const dark = document.documentElement.dataset.theme === 'dark';
      document.documentElement.dataset.theme = dark ? 'light' : 'dark';
      try { localStorage.setItem('digihub-theme', dark ? 'light' : 'dark'); } catch (e) {}
      syncTheme();
    });
  }

  /* Turn server-rendered flash messages into toasts (keeping them visible without JS). */
  function promoteFlashes() {
    document.querySelectorAll('.flash').forEach((flash) => {
      let type = 'info';
      if (flash.classList.contains('flash-success')) type = 'success';
      if (flash.classList.contains('flash-error')) type = 'error';
      if (flash.classList.contains('flash-warning')) type = 'warning';
      const text = flash.textContent.replace(/\s+/g, ' ').trim();
      if (text) showToast(text, type);
      flash.remove();
    });
  }

  /* Mobile navigation */
  function initNavigation() {
    const toggle = document.querySelector('[data-nav-toggle]');
    const nav = toggle && document.getElementById(toggle.getAttribute('aria-controls'));
    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });

    nav.addEventListener('click', (event) => {
      if (event.target.closest('a')) {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('click', (event) => {
      if (!nav.contains(event.target) && !toggle.contains(event.target)) {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    const current = window.location.pathname.split('/').pop() || 'index.php';
    nav.querySelectorAll('a').forEach((link) => {
      const href = link.getAttribute('href') || '';
      if (href === current || href.endsWith('/' + current)) link.classList.add('active');
    });
  }

  /* Confirmation for destructive / important actions */
  function initConfirmations() {
    document.querySelectorAll('[data-confirm]').forEach((el) => {
      el.addEventListener('click', (event) => {
        if (window.confirm(el.dataset.confirm) === false) event.preventDefault();
      });
    });
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (window.confirm(form.dataset.confirm) === false) event.preventDefault();
      });
    });
  }

  /* Show / hide password fields */
  function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const field = document.getElementById(button.dataset.passwordToggle);
        if (!field) return;
        const reveal = field.type === 'password';
        field.type = reveal ? 'text' : 'password';
        button.textContent = reveal ? 'Hide' : 'Show';
        button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
      });
    });
  }

  /* Cameroon phone numbers: always exactly +237 6XX XXX XXX. */
  function initPhoneFormat() {
    const input = document.getElementById('phone');
    if (!input) return;
    input.addEventListener('input', () => {
      let digits = input.value.replace(/\D/g, '');
      if (digits.startsWith('237')) digits = digits.slice(3);
      digits = digits.slice(0, 9);
      if (digits && digits[0] !== '6') digits = digits.replace(/^./, '6');
      let formatted = '';
      if (digits) {
        formatted = '+237 ' + digits.slice(0, 3);
        if (digits.length > 3) formatted += ' ' + digits.slice(3, 6);
        if (digits.length > 6) formatted += ' ' + digits.slice(6, 9);
      }
      input.value = formatted;
      input.setCustomValidity(formatted ? '' : 'Enter a Cameroon number like +237 6XX XXX XXX.');
    });
    input.addEventListener('blur', () => {
      input.setCustomValidity(input.value && !input.checkValidity()
        ? 'Use exactly +237 6XX XXX XXX.'
        : '');
    });
  }

  /* Live product search */
  function initSearch() {
    const input = document.getElementById('product-search');
    if (!input) return;
    const cards = Array.from(document.querySelectorAll('.product-card[data-search]'));
    const empty = document.getElementById('no-results');

    input.addEventListener('input', () => {
      const query = input.value.trim().toLowerCase();
      let visible = 0;
      cards.forEach((card) => {
        const match = !query || (card.dataset.search || '').toLowerCase().includes(query);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      if (empty) empty.hidden = visible !== 0;
    });
  }

  /* Like / unlike with immediate feedback */
  function initLike() {
    const button = document.getElementById('like-btn');
    if (!button) return;
    const countEl = document.getElementById('like-count');
    const labelEl = document.getElementById('like-label');
    let busy = false;

    button.addEventListener('click', async () => {
      if (busy) return;
      busy = true;
      button.classList.add('is-loading');
      const body = new URLSearchParams({
        product_id: button.dataset.productId,
        user_email: button.dataset.email
      });
      try {
        const response = await fetch(button.dataset.endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body.toString()
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Could not update like.');
        if (countEl) countEl.textContent = data.count;
        if (labelEl) labelEl.textContent = data.liked ? 'Liked' : 'Like';
        button.classList.toggle('active', Boolean(data.liked));
        button.setAttribute('aria-pressed', String(Boolean(data.liked)));
        const icon = button.querySelector('[data-like-icon]');
        if (icon) icon.className = 'fa-solid ' + (data.liked ? 'fa-heart' : 'fa-heart');
        showToast(data.liked ? 'Added to your likes.' : 'Like removed.', 'success');
      } catch (error) {
        showToast(error.message || 'Something went wrong. Please try again.', 'error');
      } finally {
        busy = false;
        button.classList.remove('is-loading');
      }
    });
  }

  /* Ajax comments */
  function initComments() {
    const form = document.getElementById('comment-form');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      if (button) button.classList.add('is-loading');
      const data = new FormData(form);
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: data,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.error || 'Could not post comment.');

        const list = document.getElementById('comment-list');
        if (list) {
          const placeholder = list.querySelector('.empty-comment');
          if (placeholder) placeholder.remove();
          const item = document.createElement('article');
          item.className = 'comment card';
          const head = document.createElement('div');
          head.className = 'comment-head';
          const author = document.createElement('span');
          author.className = 'comment-author';
          author.textContent = data.get('author_name') || result.author_name || 'You';
          const date = document.createElement('span');
          date.className = 'comment-date';
          date.textContent = result.created_at || 'Just now';
          head.append(author, date);
          const text = document.createElement('p');
          text.textContent = data.get('comment_text') || '';
          item.append(head, text);
          list.prepend(item);
        }
        form.reset();
        showToast(result.message || 'Comment posted successfully.', 'success');
      } catch (error) {
        showToast(error.message || 'Something went wrong. Please try again.', 'error');
      } finally {
        if (button) button.classList.remove('is-loading');
      }
    });
  }

  /* Upload form: contextual fields and file name hint */
  function initUploadForm() {
    const typeSelect = document.getElementById('product_type');
    const freeCheck = document.getElementById('is_free');
    if (!typeSelect && !freeCheck) return;

    const priceGroup = document.getElementById('price_group');
    const pageGroup = document.getElementById('page_group');
    const coverGroup = document.getElementById('cover_group');
    const fileInput = document.querySelector('input[name="product_file"]');
    const fileHint = document.getElementById('file-hint');
    const slotHint = document.getElementById('slot-hint');

    function syncType() {
      const isBook = !typeSelect || typeSelect.value === 'book';
      if (pageGroup) pageGroup.hidden = !isBook;
      if (coverGroup) coverGroup.hidden = !isBook;
      if (slotHint) slotHint.hidden = isBook;
      if (fileInput) {
        fileInput.accept = isBook ? '.pdf,application/pdf' : '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp';
        if (fileHint) {
          fileHint.textContent = isBook
            ? 'PDF only for books and comics. Max 50 MB.'
            : 'JPG, PNG or WebP artwork. Max 50 MB. Uses one image slot.';
        }
      }
    }
    function syncPrice() {
      if (priceGroup) priceGroup.hidden = Boolean(freeCheck && freeCheck.checked);
    }
    if (typeSelect) typeSelect.addEventListener('change', syncType);
    if (freeCheck) freeCheck.addEventListener('change', syncPrice);
    if (fileInput && fileHint) {
      fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
          fileHint.textContent = 'Selected: ' + fileInput.files[0].name;
        } else {
          syncType();
        }
      });
    }
    syncType();
    syncPrice();
  }

  /* Ajax product reports */
  function initReports() {
    const form = document.getElementById('report-form');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      if (button) button.classList.add('is-loading');
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.error || 'Could not send report.');
        form.reset();
        showToast(result.message || 'Report sent.', 'success');
      } catch (error) {
        showToast(error.message || 'Something went wrong.', 'error');
      } finally {
        if (button) button.classList.remove('is-loading');
      }
    });
  }

  /* Smooth in-page navigation for preview page links. */
  function initPreviewNav() {
    const reader = document.getElementById('preview-reader');
    if (!reader) return;
    document.querySelectorAll('[data-preview-page]').forEach((link) => {
      link.addEventListener('click', (event) => {
        const page = document.getElementById(link.dataset.previewPage);
        if (!page) return;
        event.preventDefault();
        page.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.querySelectorAll('[data-preview-page]').forEach((item) => item.classList.remove('active'));
        link.classList.add('active');
      });
    });
  }

  /* Small loading state for form submits */
  function initSubmitStates() {
    document.querySelectorAll('form[data-loading]').forEach((form) => {
      form.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');
        if (button) {
          button.classList.add('is-loading');
          button.dataset.originalText = button.textContent;
          button.textContent = 'Please wait...';
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSplash();
    initTheme();
    promoteFlashes();
    initNavigation();
    initConfirmations();
    initPasswordToggles();
    initPhoneFormat();
    initSearch();
    initLike();
    initComments();
    initUploadForm();
    initReports();
    initPreviewNav();
    initSubmitStates();
  });
})();
