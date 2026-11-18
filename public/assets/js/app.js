/* Smart Attendance core UI script: theme toggle, drawer, modals, PWA install, GPS autofill */
(function () {
  'use strict';

  const THEME_KEY = 'smart_attendance_theme';

  function applyTheme(theme) {
    const t = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.setAttribute('aria-pressed', t === 'dark' ? 'true' : 'false');
      const labelEl = btn.querySelector('[data-theme-label]');
      if (labelEl) {
        labelEl.textContent = t === 'dark' ? 'Mode Terang' : 'Mode Gelap';
      }
    });
    window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: t } }));
  }

  function initTheme() {
    let saved = null;
    try {
      saved = localStorage.getItem(THEME_KEY);
    } catch (_) {
      saved = null;
    }
    if (saved === 'dark' || saved === 'light') {
      applyTheme(saved);
    } else {
      applyTheme('light');
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        try {
          localStorage.setItem(THEME_KEY, next);
        } catch (_) {}
        applyTheme(next);
      });
    });
  }

  function initDrawer() {
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const openBtn = document.getElementById('openSidebarBtn');
    const closeBtn = document.getElementById('closeSidebarBtn');
    if (!sidebar || !backdrop || !openBtn) return;

    function setOpen(isOpen) {
      sidebar.classList.toggle('open', isOpen);
      backdrop.classList.toggle('open', isOpen);
      openBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen && closeBtn) {
        closeBtn.focus();
      }
    }

    openBtn.addEventListener('click', () => setOpen(true));
    if (closeBtn) closeBtn.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && sidebar.classList.contains('open')) {
        setOpen(false);
        openBtn.focus();
      }
    });
  }

  function initModals() {
    let lastTrigger = null;

    function closeAll() {
      document.querySelectorAll('.modal-backdrop.open').forEach((m) => {
        m.classList.remove('open');
        m.setAttribute('aria-hidden', 'true');
      });
      if (lastTrigger && typeof lastTrigger.focus === 'function') {
        lastTrigger.focus();
        lastTrigger = null;
      }
    }

    document.querySelectorAll('[data-modal-open]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-modal-open');
        const modal = targetId ? document.getElementById(targetId) : null;
        if (!modal) return;
        lastTrigger = btn;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (firstInput) firstInput.focus();
      });
    });

    document.querySelectorAll('[data-modal-close]').forEach((btn) => {
      btn.addEventListener('click', closeAll);
    });

    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
      backdrop.addEventListener('click', (ev) => {
        if (ev.target === backdrop) closeAll();
      });
    });

    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape') {
        if (document.querySelector('.modal-backdrop.open')) {
          closeAll();
        }
      }
    });
  }

  function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (ev) => {
        const msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
          ev.preventDefault();
        }
      });
    });
  }

  function initOfficeGpsFill() {
    const btn = document.getElementById('fillOfficeGpsBtn');
    const latInput = document.getElementById('office_lat');
    const lngInput = document.getElementById('office_lng');
    const statusEl = document.getElementById('officeGpsStatus');
    if (!btn || !latInput || !lngInput) return;

    btn.addEventListener('click', () => {
      if (!navigator.geolocation) {
        if (statusEl) statusEl.textContent = 'Browser ini tidak mendukung Geolocation API.';
        return;
      }
      btn.disabled = true;
      if (statusEl) statusEl.textContent = 'Mengambil koordinat GPS saat ini...';

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          latInput.value = pos.coords.latitude.toFixed(6);
          lngInput.value = pos.coords.longitude.toFixed(6);
          if (statusEl) {
            statusEl.textContent =
              'Koordinat terisi (akurasi +/- ' + Math.round(pos.coords.accuracy) + ' m). Klik Simpan Pengaturan.';
          }
          btn.disabled = false;
        },
        (err) => {
          const msg =
            err.code === 1
              ? 'Izin lokasi ditolak oleh browser.'
              : 'Gagal mendapatkan koordinat GPS. Pastikan lokasi perangkat aktif.';
          if (statusEl) statusEl.textContent = msg;
          btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
      );
    });
  }

  function initCopyButtons() {
    document.querySelectorAll('[data-copy-target]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const targetId = btn.getAttribute('data-copy-target');
        const el = targetId ? document.getElementById(targetId) : null;
        if (!el) return;
        const text = 'value' in el ? el.value : el.textContent;
        try {
          await navigator.clipboard.writeText(text.trim());
          const orig = btn.textContent;
          btn.textContent = 'Tersalin';
          setTimeout(() => {
            btn.textContent = orig;
          }, 1800);
        } catch (_) {
          if ('select' in el) el.select();
        }
      });
    });
  }

  function initPwa() {
    if ('serviceWorker' in navigator && !window.location.pathname.startsWith('/kiosk')) {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    let deferredPrompt = null;
    const installBtn = document.getElementById('pwaInstallBtn');

    window.addEventListener('beforeinstallprompt', (ev) => {
      ev.preventDefault();
      deferredPrompt = ev;
      if (installBtn) {
        installBtn.hidden = false;
      }
    });

    if (installBtn) {
      installBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        try {
          await deferredPrompt.userChoice;
        } catch (_) {}
        deferredPrompt = null;
        installBtn.hidden = true;
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initDrawer();
    initModals();
    initConfirmForms();
    initOfficeGpsFill();
    initCopyButtons();
    initPwa();
  });
})();
