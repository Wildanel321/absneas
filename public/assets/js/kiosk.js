/* Fullscreen QR Kiosk Monitor Controller (auto-rotating, schedule-aware, live attendance alerts & voice) */
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
    const soundBtn = document.getElementById('kioskSoundBtn');
    const recentListEl = document.getElementById('kioskRecentList');

    // Pop-up alert elements
    const alertOverlay = document.getElementById('kioskAttendanceAlert');
    const alertBadge = document.getElementById('kioskAlertBadge');
    const alertPhoto = document.getElementById('kioskAlertPhoto');
    const alertInitial = document.getElementById('kioskAlertInitial');
    const alertName = document.getElementById('kioskAlertName');
    const alertMsg = document.getElementById('kioskAlertMsg');

    let refreshSeconds = 30;
    let nextRefreshAt = Date.now() + 30000;
    let paused = false;
    let selectedMode = 'auto';

    // Audio & Speech State
    let audioCtx = null;
    let soundEnabled = localStorage.getItem('kiosk_sound') !== '0';
    let alertHideTimeout = null;
    let isInitialLoad = true;
    const knownEventIds = new Set();

    // Populate initially rendered event IDs from HTML
    if (recentListEl) {
      recentListEl.querySelectorAll('[data-event-id]').forEach((el) => {
        const id = el.dataset.eventId;
        if (id) knownEventIds.add(id);
      });
    }

    function initAudio() {
      if (!audioCtx) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
          audioCtx = new AudioContextClass();
        }
      }
      if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume().catch(() => {});
      }
      updateSoundButtonUI();
    }

    function updateSoundButtonUI() {
      if (!soundBtn) return;
      if (!soundEnabled) {
        soundBtn.textContent = '🔈 Suara: Nonaktif';
        soundBtn.style.background = '#162647';
        soundBtn.setAttribute('aria-pressed', 'false');
      } else if (audioCtx && audioCtx.state === 'suspended') {
        soundBtn.textContent = '🔈 Aktifkan Suara TV';
        soundBtn.style.background = '#854d0e';
        soundBtn.setAttribute('aria-pressed', 'false');
      } else {
        soundBtn.textContent = '🔊 Suara: Aktif';
        soundBtn.style.background = '#0e7c5b';
        soundBtn.setAttribute('aria-pressed', 'true');
      }
    }

    // Modern 2-tone melodic chime: E5 (659Hz) -> A5 (880Hz)
    function playChime() {
      if (!soundEnabled) return;
      try {
        initAudio();
        if (!audioCtx || audioCtx.state !== 'running') return;

        const now = audioCtx.currentTime;
        const osc1 = audioCtx.createOscillator();
        const osc2 = audioCtx.createOscillator();
        const gain = audioCtx.createGain();

        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(659.25, now); // E5

        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(880.0, now + 0.14); // A5

        gain.gain.setValueAtTime(0.001, now);
        gain.gain.linearRampToValueAtTime(0.35, now + 0.04);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.85);

        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(audioCtx.destination);

        osc1.start(now);
        osc1.stop(now + 0.3);
        osc2.start(now + 0.14);
        osc2.stop(now + 0.85);
      } catch (err) {
        console.warn('Gagal memutar audio chime:', err);
      }
    }

    // Voice announcement using Web Speech Synthesis API
    function speakAnnouncement(fullName, mode) {
      if (!soundEnabled) return;
      if (!('speechSynthesis' in window)) return;

      try {
        window.speechSynthesis.cancel();
        const actionLabel = mode === 'checkout' ? 'absen pulang' : 'absen masuk';
        const cleanName = fullName && fullName.trim() !== '' && fullName.toLowerCase() !== 'user'
          ? fullName.trim()
          : 'Karyawan';
        const phrase = `${cleanName} berhasil ${actionLabel}.`;
        const utter = new SpeechSynthesisUtterance(phrase);
        utter.lang = 'id-ID';
        utter.rate = 0.95;
        utter.pitch = 1.05;

        // Try to pick an Indonesian voice
        const voices = window.speechSynthesis.getVoices();
        const idVoice = voices.find(
          (v) => v.lang && (v.lang.toLowerCase().startsWith('id') || v.lang.toLowerCase().includes('indonesia'))
        );
        if (idVoice) utter.voice = idVoice;

        window.speechSynthesis.speak(utter);
      } catch (err) {
        console.warn('Gagal memanggil speech synthesis:', err);
      }
    }

    // Display TV Pop-up banner
    function showAttendanceAlert(ev) {
      if (!alertOverlay) return;

      const isOut = ev.mode === 'checkout';
      alertOverlay.classList.toggle('mode-checkout', isOut);

      if (alertBadge) {
        alertBadge.className = 'kiosk-alert-badge ' + (isOut ? 'mode-checkout' : 'mode-checkin');
        alertBadge.textContent = isOut ? 'BERHASIL ABSEN PULANG' : 'BERHASIL ABSEN MASUK';
      }

      const cleanName = ev.full_name && ev.full_name.trim() !== '' && ev.full_name.toLowerCase() !== 'user'
        ? ev.full_name.trim()
        : 'Karyawan';

      if (alertName) {
        alertName.textContent = cleanName;
      }

      if (alertMsg) {
        const time = ev.time_formatted || '--:--';
        const status = ev.status_label || (isOut ? 'Pulang' : 'Tepat Waktu');
        alertMsg.textContent = isOut
          ? `Berhasil absen pulang pada pukul ${time}`
          : `Berhasil absen masuk pada pukul ${time} (${status})`;
      }

      if (alertPhoto && alertInitial) {
        if (ev.photo_url) {
          const keyParam = kioskKey ? '?key=' + encodeURIComponent(kioskKey) : '';
          alertPhoto.src = ev.photo_url + keyParam;
          alertPhoto.hidden = false;
          alertInitial.hidden = true;
          alertPhoto.onerror = () => {
            alertPhoto.hidden = true;
            alertInitial.hidden = false;
          };
        } else {
          alertPhoto.hidden = true;
          alertInitial.textContent = (ev.full_name || 'K').substring(0, 2).toUpperCase();
          alertInitial.hidden = false;
        }
      }

      alertOverlay.classList.add('visible');

      if (alertHideTimeout) clearTimeout(alertHideTimeout);
      alertHideTimeout = setTimeout(() => {
        alertOverlay.classList.remove('visible');
      }, 5500);
    }

    // Render Recent Attendance Feed
    function renderRecentList(recent) {
      if (!recentListEl) return;
      if (!Array.isArray(recent) || recent.length === 0) {
        recentListEl.innerHTML = '<div class="kiosk-recent-empty">Belum ada absensi tercatat hari ini.</div>';
        return;
      }

      const keyParam = kioskKey ? '?key=' + encodeURIComponent(kioskKey) : '';
      const html = recent
        .map((rec) => {
          const isOut = rec.mode === 'checkout';
          const badgeClass = isOut ? 'mode-checkout' : 'mode-checkin';
          const badgeLabel = (isOut ? 'Pulang ' : 'Masuk ') + (rec.time_formatted || '');
          const initials = (rec.full_name || 'K').substring(0, 2).toUpperCase();

          const avatarHtml = rec.photo_url
            ? `<img src="${rec.photo_url}${keyParam}" alt="" class="kiosk-recent-avatar" onerror="this.outerHTML='<div class=\\'kiosk-recent-avatar kiosk-avatar-placeholder\\'>${initials}</div>'">`
            : `<div class="kiosk-recent-avatar kiosk-avatar-placeholder">${initials}</div>`;

          return `
            <div class="kiosk-recent-item" data-event-id="${rec.event_id || ''}">
              ${avatarHtml}
              <div class="kiosk-recent-meta">
                <div class="kiosk-recent-name">${escapeHtml(rec.full_name || '')}</div>
                <div class="kiosk-recent-sub">${escapeHtml(rec.employee_code || '')} • ${escapeHtml(rec.status_label || '')}</div>
              </div>
              <div class="kiosk-recent-badge ${badgeClass}">${escapeHtml(badgeLabel)}</div>
            </div>
          `;
        })
        .join('');

      recentListEl.innerHTML = html;
    }

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    // Process new attendances detected from server
    function handleNewAttendances(recent) {
      if (!Array.isArray(recent)) return;

      const newEvents = [];
      for (const ev of recent) {
        if (!ev || !ev.event_id) continue;
        if (!knownEventIds.has(ev.event_id)) {
          knownEventIds.add(ev.event_id);
          newEvents.push(ev);
        }
      }

      // First page load: just remember current events without retroactive sound
      if (isInitialLoad) {
        isInitialLoad = false;
        return;
      }

      if (newEvents.length > 0) {
        // Trigger announcement for the newest event
        const latest = newEvents[0];
        playChime();
        window.setTimeout(() => {
          speakAnnouncement(latest.full_name, latest.mode);
        }, 220);
        showAttendanceAlert(latest);
      }
    }

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

        if (Array.isArray(data.recent_attendances)) {
          handleNewAttendances(data.recent_attendances);
          renderRecentList(data.recent_attendances);
        }

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

    // Fast 4-second activity polling for real-time notification
    async function pollRecentActivity() {
      if (document.hidden) return;
      const params = new URLSearchParams();
      if (kioskKey) params.set('key', kioskKey);

      try {
        const res = await fetch('/api/kiosk/activity?' + params.toString(), {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.ok) return;

        if (countInEl && data.today_counts) countInEl.textContent = String(data.today_counts.checked_in);
        if (countOutEl && data.today_counts) countOutEl.textContent = String(data.today_counts.checked_out);

        if (Array.isArray(data.recent_attendances)) {
          handleNewAttendances(data.recent_attendances);
          renderRecentList(data.recent_attendances);
        }
      } catch (err) {
        // Silently retry on next poll cycle
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

    // Event Listeners
    if (soundBtn) {
      soundBtn.addEventListener('click', () => {
        initAudio();
        soundEnabled = !soundEnabled;
        localStorage.setItem('kiosk_sound', soundEnabled ? '1' : '0');
        updateSoundButtonUI();
        if (soundEnabled) {
          playChime();
        }
      });
    }

    // Auto unlock audio on any user interaction anywhere on the screen
    window.addEventListener(
      'click',
      () => {
        initAudio();
      },
      { once: true }
    );
    window.addEventListener(
      'keydown',
      () => {
        initAudio();
      },
      { once: true }
    );

    // Warm voices up when available
    if ('speechSynthesis' in window) {
      window.speechSynthesis.onvoiceschanged = () => {
        window.speechSynthesis.getVoices();
      };
    }

    if (fullscreenBtn) {
      fullscreenBtn.addEventListener('click', () => {
        initAudio();
        if (!document.fullscreenElement) {
          document.documentElement.requestFullscreen?.().catch(() => {});
        } else {
          document.exitFullscreen?.().catch(() => {});
        }
      });
    }

    if (pauseBtn) {
      pauseBtn.addEventListener('click', () => {
        initAudio();
        paused = !paused;
        pauseBtn.setAttribute('aria-pressed', paused ? 'true' : 'false');
        pauseBtn.textContent = paused ? 'Lanjutkan Rotasi QR' : 'Jeda Rotasi QR';
        if (!paused) loadQr();
      });
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        initAudio();
        loadQr();
      });
    }

    if (modeSelect) {
      modeSelect.addEventListener('change', () => {
        selectedMode = modeSelect.value;
        loadQr();
      });
    }

    updateClock();
    updateSoundButtonUI();
    loadQr();

    window.setInterval(tick, 250);
    window.setInterval(pollRecentActivity, 4000);
  });
})();
