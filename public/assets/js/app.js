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

  function initOfficeMap() {
    const mapEl = document.getElementById('officeMap');
    const latInput = document.getElementById('office_lat');
    const lngInput = document.getElementById('office_lng');
    const radiusInput = document.getElementById('geofence_radius_m');
    const pasteInput = document.getElementById('paste_coords');
    const applyPasteBtn = document.getElementById('applyPastedCoordsBtn');
    const statusEl = document.getElementById('officeGpsStatus');

    if (!mapEl || !latInput || !lngInput || typeof window.L === 'undefined') {
      return;
    }

    if (window.L.Icon && window.L.Icon.Default) {
      delete window.L.Icon.Default.prototype._getIconUrl;
      window.L.Icon.Default.mergeOptions({
        iconRetinaUrl: '/assets/css/images/marker-icon-2x.png',
        iconUrl: '/assets/css/images/marker-icon.png',
        shadowUrl: '/assets/css/images/marker-shadow.png',
      });
    }

    let lat = parseFloat(latInput.value);
    let lng = parseFloat(lngInput.value);
    if (!Number.isFinite(lat) || lat < -90 || lat > 90) lat = -6.200000;
    if (!Number.isFinite(lng) || lng < -180 || lng > 180) lng = 106.816666;

    let radius = parseFloat(radiusInput ? radiusInput.value : 100) || 100;

    const map = window.L.map('officeMap', {
      center: [lat, lng],
      zoom: 16,
      scrollWheelZoom: false,
    });

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
    }).addTo(map);

    const marker = window.L.marker([lat, lng], {
      draggable: true,
      title: 'Titik Kantor (Geser untuk memindahkan)',
    }).addTo(map);

    marker.bindPopup('<strong>Titik Kantor</strong><br>Geser pin atau klik di peta untuk menentukan titik koordinat.');

    const circle = window.L.circle([lat, lng], {
      radius: radius,
      color: '#5B3FD6',
      fillColor: '#5B3FD6',
      fillOpacity: 0.16,
      weight: 2,
    }).addTo(map);

    function setLocation(newLat, newLng, pan) {
      latInput.value = newLat.toFixed(6);
      lngInput.value = newLng.toFixed(6);
      marker.setLatLng([newLat, newLng]);
      circle.setLatLng([newLat, newLng]);
      if (pan) {
        map.panTo([newLat, newLng]);
      }
      if (statusEl) {
        statusEl.textContent = 'Koordinat disinkronkan ke peta: ' + newLat.toFixed(6) + ', ' + newLng.toFixed(6);
      }
    }

    marker.on('drag', () => {
      const pos = marker.getLatLng();
      circle.setLatLng(pos);
      latInput.value = pos.lat.toFixed(6);
      lngInput.value = pos.lng.toFixed(6);
    });

    marker.on('dragend', () => {
      const pos = marker.getLatLng();
      setLocation(pos.lat, pos.lng, true);
    });

    map.on('click', (e) => {
      setLocation(e.latlng.lat, e.latlng.lng, true);
    });

    const onManualChange = () => {
      const l = parseFloat(latInput.value);
      const g = parseFloat(lngInput.value);
      if (Number.isFinite(l) && l >= -90 && l <= 90 && Number.isFinite(g) && g >= -180 && g <= 180) {
        marker.setLatLng([l, g]);
        circle.setLatLng([l, g]);
        map.panTo([l, g]);
      }
    };

    latInput.addEventListener('change', onManualChange);
    lngInput.addEventListener('change', onManualChange);

    if (radiusInput) {
      radiusInput.addEventListener('input', () => {
        const rad = Math.max(10, Math.min(5000, parseFloat(radiusInput.value) || 100));
        circle.setRadius(rad);
      });
    }

    function applyCoordsString(raw) {
      if (!raw) return false;
      const clean = raw.trim().replace(/[°'"NSEWnsew]/g, '');
      const parts = clean.split(/[,\s]+/).map(Number.parseFloat).filter((n) => Number.isFinite(n));
      if (parts.length >= 2) {
        const [pLat, pLng] = parts;
        if (pLat >= -90 && pLat <= 90 && pLng >= -180 && pLng <= 180) {
          setLocation(pLat, pLng, true);
          map.setZoom(17);
          return true;
        }
      }
      return false;
    }

    if (applyPasteBtn && pasteInput) {
      applyPasteBtn.addEventListener('click', () => {
        const success = applyCoordsString(pasteInput.value);
        if (success) {
          if (statusEl) statusEl.textContent = 'Koordinat Google Maps berhasil dipasang ke pin peta.';
          pasteInput.value = '';
        } else {
          if (statusEl) statusEl.textContent = 'Format tidak dikenali. Salin koordinat Google Maps seperti: -6.208765, 106.845612';
        }
      });

      pasteInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyPasteBtn.click();
        }
      });
    }

    window._syncOfficeMapCoords = (newLat, newLng) => {
      setLocation(newLat, newLng, true);
      map.setZoom(17);
    };

    setTimeout(() => {
      map.invalidateSize();
    }, 200);
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
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;
          latInput.value = lat.toFixed(6);
          lngInput.value = lng.toFixed(6);
          if (typeof window._syncOfficeMapCoords === 'function') {
            window._syncOfficeMapCoords(lat, lng);
          }
          if (statusEl) {
            statusEl.textContent =
              'Koordinat terisi dari GPS (akurasi +/- ' + Math.round(pos.coords.accuracy) + ' m). Silakan klik Simpan Pengaturan.';
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
    initOfficeMap();
    initOfficeGpsFill();
    initCopyButtons();
    initPwa();
  });
})();
