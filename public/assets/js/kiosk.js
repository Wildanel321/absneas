/* Fullscreen QR Kiosk Monitor Controller (auto-rotating & schedule-aware) */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('kioskApp');
    if (!root) return;

    const kioskKey = root.dataset.kioskKey || '';
    const qrBox = document.getElementById('kioskQrBox');
    const qrCard = document.getElementById('kioskQrCard');
    const modeTag = document.getElementById('kioskModeTag');
    const modeDesc = document.getElementById('kioskModeDesc');
    const countdownEl = document.getElementById('kioskCountdown');
    const progressEl = document.getElementById('kioskProgressBar');
    const clockEl = document.getElementById('kioskClock');
    const dateEl = document.getElementById('kioskDate');
    const countInEl = document.getElementById('kioskCountIn');
    const countOutEl = document.getElementById('kioskCountOut');
    const errorEl = document.getElementById('kioskError');
    const fullscreenBtn = document.getElementById('kioskFullscreenBtn');
    const pauseBtn = document.getElementById('kioskPauseBtn');
    const refreshBtn = document.getElementById('kioskRefreshBtn');
    const modeSelect = document.getElementById('kioskModeSelect');

    let refreshSeconds = 30;
    let nextRefreshAt = Date.now() + 30000;
    let paused = false;
    let selectedMode = 'auto';

    function updateClock() {
      const now = new Date();
      if (clockEl) {
        clockEl.textContent = now.toLocaleTimeString('id-ID', {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: false,
        });
      }
      if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('id-ID', {
          weekday: 'long',
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        });
      }
    }

    async function loadQr() {
      const params = new URLSearchParams();
      if (kioskKey) params.set('key', kioskKey);
      if (selectedMode !== 'auto') params.set('mode', selectedMode);

      try {
        const res = await fetch('/api/kiosk/qr?' + params.toString(), {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (!data.ok) throw new Error(data.message || 'Gagal memuat QR');

        if (qrBox) qrBox.innerHTML = data.svg;
        const isOut = data.mode === 'checkout';

        if (qrCard) qrCard.classList.toggle('mode-checkout', isOut);
        if (modeTag) {
          modeTag.classList.toggle('mode-checkout', isOut);
          modeTag.textContent = data.mode_label || (isOut ? 'QR Check-out' : 'QR Check-in');
        }
        if (modeDesc) {
          modeDesc.textContent = isOut
            ? 'Pindai QR ini melalui aplikasi Smart Attendance untuk mencatat jam pulang Anda.'
            : 'Pindai QR ini melalui aplikasi Smart Attendance untuk mencatat jam masuk Anda.';
        }
        if (countInEl && data.today_counts) countInEl.textContent = String(data.today_counts.checked_in);
        if (countOutEl && data.today_counts) countOutEl.textContent = String(data.today_counts.checked_out);
        if (errorEl) errorEl.hidden = true;

        refreshSeconds = Math.max(10, Number(data.refresh_seconds) || 30);
        nextRefreshAt = Date.now() + refreshSeconds * 1000;
      } catch (err) {
        if (errorEl) {
          errorEl.textContent = 'Gagal memuat token QR baru dari server. Mencoba kembali otomatis...';
          errorEl.hidden = false;
        }
        nextRefreshAt = Date.now() + 5000;
      }
    }

    function tick() {
      updateClock();
      if (paused) return;

      const remainingMs = nextRefreshAt - Date.now();
      if (remainingMs <= 0) {
        loadQr();
        return;
      }

      const sec = Math.ceil(remainingMs / 1000);
      if (countdownEl) countdownEl.textContent = sec + ' detik';
      if (progressEl) {
        const pct = Math.max(0, Math.min(100, (remainingMs / (refreshSeconds * 1000)) * 100));
        progressEl.style.width = pct.toFixed(1) + '%';
      }
    }

    if (fullscreenBtn) {
      fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
          document.documentElement.requestFullscreen?.().catch(() => {});
        } else {
          document.exitFullscreen?.().catch(() => {});
        }
      });
    }

    if (pauseBtn) {
      pauseBtn.addEventListener('click', () => {
        paused = !paused;
        pauseBtn.setAttribute('aria-pressed', paused ? 'true' : 'false');
        pauseBtn.textContent = paused ? 'Lanjutkan Rotasi QR' : 'Jeda Rotasi QR';
        if (!paused) loadQr();
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => loadQr());
    }

    if (modeSelect) {
      modeSelect.addEventListener('change', () => {
        selectedMode = modeSelect.value;
        loadQr();
      });
    }

    updateClock();
    loadQr();
    window.setInterval(tick, 250);
  });
})();
