/* Employee PWA QR Camera Scanner + GPS Geofencing Validator */
(function () {
  'use strict';

  function haversineMeters(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a =
      Math.sin(dLat / 2) ** 2 +
      Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
  }

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('scannerApp');
    if (!root) return;

    const csrf = root.dataset.csrf || '';
    const officeLat = parseFloat(root.dataset.officeLat || '0');
    const officeLng = parseFloat(root.dataset.officeLng || '0');
    const radiusM = parseFloat(root.dataset.radius || '100');
    const maxAccuracyM = parseFloat(root.dataset.maxAccuracy || '150');

    const video = document.getElementById('scannerVideo');
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    const retryGpsBtn = document.getElementById('retryGpsBtn');
    const gpsStatusEl = document.getElementById('gpsStatusText');
    const gpsDistanceEl = document.getElementById('gpsDistanceText');
    const gpsAccuracyEl = document.getElementById('gpsAccuracyText');
    const scanFeedbackEl = document.getElementById('scanFeedback');
    const scannerReticle = document.getElementById('scannerReticle');
    const selfieReticle = document.getElementById('selfieReticle');
    const selfieActionRow = document.getElementById('selfieActionRow');
    const snapSelfieBtn = document.getElementById('snapSelfieBtn');

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    let stream = null;
    let rafId = null;
    let watchId = null;
    let detector = null;
    let scanning = false;
    let submitting = false;
    let lastPos = null;

    if ('BarcodeDetector' in window) {
      try {
        detector = new window.BarcodeDetector({ formats: ['qr_code'] });
      } catch (_) {
        detector = null;
      }
    }

    function showFeedback(tone, text) {
      if (!scanFeedbackEl) return;
      scanFeedbackEl.className = 'alert alert-' + tone;
      scanFeedbackEl.textContent = text;
      scanFeedbackEl.hidden = false;
    }

    function startGps() {
      if (!navigator.geolocation) {
        if (gpsStatusEl) gpsStatusEl.textContent = 'GPS tidak didukung browser';
        showFeedback('error', 'Browser ini tidak mendukung Geolocation API untuk verifikasi lokasi.');
        return;
      }

      if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
      }

      if (gpsStatusEl) gpsStatusEl.textContent = 'Mencari sinyal GPS...';

      watchId = navigator.geolocation.watchPosition(
        (pos) => {
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;
          const acc = pos.coords.accuracy;
          const dist = haversineMeters(lat, lng, officeLat, officeLng);

          lastPos = { latitude: lat, longitude: lng, accuracy: acc, distance: dist };

          if (gpsDistanceEl) gpsDistanceEl.textContent = dist.toFixed(1) + ' m / ' + radiusM.toFixed(0) + ' m';
          if (gpsAccuracyEl) gpsAccuracyEl.textContent = '+/- ' + Math.round(acc) + ' m';

          if (acc > maxAccuracyM) {
            if (gpsStatusEl) gpsStatusEl.textContent = 'Akurasi GPS terlalu rendah';
          } else if (dist > radiusM) {
            if (gpsStatusEl) gpsStatusEl.textContent = 'Di luar radius kantor';
          } else {
            if (gpsStatusEl) gpsStatusEl.textContent = 'Dalam radius kantor';
          }
        },
        (err) => {
          lastPos = null;
          if (err.code === 1) {
            if (gpsStatusEl) gpsStatusEl.textContent = 'Izin GPS ditolak';
            showFeedback(
              'error',
              'Izin lokasi GPS ditolak. Aktifkan izin lokasi di pengaturan browser Anda lalu klik Muat Ulang GPS.'
            );
          } else if (err.code === 2) {
            if (gpsStatusEl) gpsStatusEl.textContent = 'Sinyal GPS tidak tersedia';
            showFeedback('warning', 'Sinyal GPS tidak tersedia. Aktifkan layanan lokasi perangkat Anda.');
          } else {
            if (gpsStatusEl) gpsStatusEl.textContent = 'Waktu permintaan GPS habis';
            showFeedback('warning', 'Gagal mengunci sinyal GPS tepat waktu. Coba pindah ke area terbuka.');
          }
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
      );
    }

    function stopCamera() {
      scanning = false;
      if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
      }
      if (stream) {
        stream.getTracks().forEach((t) => t.stop());
        stream = null;
      }
      if (video) video.srcObject = null;
      if (startBtn) startBtn.hidden = false;
      if (stopBtn) stopBtn.hidden = true;
    }

    let pendingToken = null;

    async function promptOrCaptureSelfie(rawToken) {
      if (submitting) return;

      if (!navigator.onLine) {
        showFeedback('error', 'Perangkat sedang offline. Absensi QR memerlukan koneksi aktif ke server.');
        return;
      }

      if (!lastPos) {
        showFeedback('warning', 'Menunggu sinyal GPS terkunci sebelum mengirim absensi. Pastikan izin lokasi aktif.');
        return;
      }

      if (lastPos.accuracy > maxAccuracyM) {
        showFeedback(
          'error',
          `Akurasi GPS terlalu rendah (+/- ${Math.round(lastPos.accuracy)} m, batas ${Math.round(maxAccuracyM)} m). Tunggu sinyal lebih stabil.`
        );
        return;
      }

      pendingToken = rawToken;
      scanning = false;
      if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
      }

      // Ambil snapshot awal dari video saat ini sebagai cadangan
      let initialSnapshot = null;
      try {
        canvas.width = 320;
        canvas.height = 240;
        ctx.drawImage(video, 0, 0, 320, 240);
        initialSnapshot = canvas.toDataURL('image/jpeg', 0.65);
      } catch (_) {}

      // Tampilkan panduan selfie & tombol
      if (scannerReticle) scannerReticle.style.display = 'none';
      if (selfieReticle) selfieReticle.style.display = 'flex';
      if (selfieActionRow) selfieActionRow.style.display = 'flex';
      showFeedback('info', 'QR Terdeteksi! Posisikan wajah Anda lalu tekan Ambil Foto Selfie.');

      // Coba buka kamera depan (user) untuk selfie
      try {
        if (stream) {
          stream.getTracks().forEach((t) => t.stop());
        }
        stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 480 }, height: { ideal: 480 } },
          audio: false,
        });
        video.srcObject = stream;
        await video.play();
      } catch (_) {
        // Jika kamera depan tidak dapat diakses (misal laptop), tetap lanjutkan dengan video yang ada
      }

      if (snapSelfieBtn) {
        snapSelfieBtn.onclick = async () => {
          let finalPhoto = initialSnapshot;
          try {
            canvas.width = 320;
            canvas.height = 240;
            ctx.drawImage(video, 0, 0, 320, 240);
            finalPhoto = canvas.toDataURL('image/jpeg', 0.65);
          } catch (_) {}
          await sendAttendanceRequest(pendingToken, finalPhoto);
        };
      }
    }

    async function sendAttendanceRequest(rawToken, photoData) {
      submitting = true;
      stopCamera();
      if (selfieReticle) selfieReticle.style.display = 'none';
      if (selfieActionRow) selfieActionRow.style.display = 'none';
      if (scannerReticle) scannerReticle.style.display = '';
      showFeedback('info', 'Mengirim absensi dan memvalidasi lokasi di server...');

      try {
        const res = await fetch('/api/attendance/scan', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-Token': csrf,
          },
          body: JSON.stringify({
            token: rawToken,
            latitude: lastPos.latitude,
            longitude: lastPos.longitude,
            accuracy: lastPos.accuracy,
            photo: photoData,
          }),
        });

        const data = await res.json();
        if (res.ok && data.ok) {
          showFeedback('success', data.message || 'Absensi berhasil dicatat.');
          setTimeout(() => {
            window.location.href = '/app';
          }, 1800);
        } else {
          showFeedback('error', data.message || 'Absensi ditolak oleh server.');
        }
      } catch (_) {
        showFeedback('error', 'Gagal terhubung ke server absensi. Periksa koneksi jaringan Anda.');
      } finally {
        submitting = false;
        pendingToken = null;
      }
    }

    async function scanFrame() {
      if (!scanning || !video || video.readyState < 2) {
        if (scanning) rafId = requestAnimationFrame(scanFrame);
        return;
      }

      try {
        if (detector) {
          const codes = await detector.detect(video);
          if (codes && codes.length > 0 && codes[0].rawValue) {
            await promptOrCaptureSelfie(codes[0].rawValue);
            return;
          }
        } else if (typeof window.jsQR === 'function' && ctx) {
          const w = video.videoWidth;
          const h = video.videoHeight;
          if (w > 0 && h > 0) {
            canvas.width = w;
            canvas.height = h;
            ctx.drawImage(video, 0, 0, w, h);
            const imgData = ctx.getImageData(0, 0, w, h);
            const code = window.jsQR(imgData.data, w, h, { inversionAttempts: 'dontInvert' });
            if (code && code.data) {
              await promptOrCaptureSelfie(code.data);
              return;
            }
          }
        }
      } catch (_) {
        // Lanjutkan ke frame berikutnya
      }

      if (scanning) {
        rafId = requestAnimationFrame(scanFrame);
      }
    }

    async function startCamera() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showFeedback(
          'error',
          'Browser tidak menyediakan akses kamera. Pastikan aplikasi dibuka melalui HTTPS (Cloudflare Tunnel) atau localhost.'
        );
        return;
      }

      showFeedback('info', 'Meminta izin kamera dan memindai QR Code pada monitor kantor...');
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' }, width: { ideal: 720 }, height: { ideal: 540 } },
          audio: false,
        });
        video.srcObject = stream;
        await video.play();
        scanning = true;
        if (startBtn) startBtn.hidden = true;
        if (stopBtn) stopBtn.hidden = false;
        showFeedback('info', 'Arahkan kamera ke QR Code pada monitor absensi.');
        rafId = requestAnimationFrame(scanFrame);
      } catch (err) {
        const denied = err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError');
        showFeedback(
          'error',
          denied
            ? 'Izin kamera ditolak. Berikan izin kamera di pengaturan browser lalu tekan Mulai Kamera.'
            : 'Kamera tidak dapat dibuka pada perangkat ini.'
        );
      }
    }

    if (startBtn) startBtn.addEventListener('click', startCamera);
    if (stopBtn) stopBtn.addEventListener('click', stopCamera);
    if (retryGpsBtn) retryGpsBtn.addEventListener('click', startGps);

    window.addEventListener('beforeunload', () => {
      stopCamera();
      if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    });

    startGps();
  });
})();
