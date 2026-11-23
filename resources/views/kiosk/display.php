<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F1D3A">
  <title><?= $e($title ?? 'Monitor QR Absensi') ?></title>
  <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="kiosk-body">
  <div id="kioskApp" data-kiosk-key="<?= $e($kioskKey ?? '') ?>" class="kiosk-app-wrap">
    <header class="kiosk-header">
      <div class="emp-header-brand">
        <img src="/icons/icon.svg" alt="" width="38" height="38" class="brand-mark">
        <div>
          <h1 style="color:#ffffff;font-size:1.2rem;margin:0;"><?= $e($officeName ?? 'Kantor Pusat') ?></h1>
          <div id="kioskDate" style="color:#cbd5e1;font-size:0.85rem;">Memuat tanggal...</div>
        </div>
      </div>

      <div class="action-row">
        <div id="kioskClock" class="kiosk-clock tabular">--:--:--</div>
        <button type="button" id="kioskSoundBtn" class="btn btn-sm" style="background:#162647;color:#ffffff;border-color:rgba(255,255,255,0.25);" aria-pressed="true" title="Klik untuk mengaktifkan atau menonaktifkan suara notifikasi TV">🔊 Suara: Aktif</button>
        <button type="button" id="kioskFullscreenBtn" class="btn btn-sm" style="background:#162647;color:#ffffff;border-color:rgba(255,255,255,0.25);">Layar Penuh</button>
        <?php if (!empty($isAdmin)): ?>
          <a href="/admin" class="btn btn-sm" style="background:#5b3fd6;color:#ffffff;">Kembali ke Dashboard</a>
        <?php endif; ?>
      </div>
    </header>

    <main class="kiosk-main">
      <!-- Tag Sesi di atas QR -->
      <div style="display:flex;justify-content:center;">
        <span id="kioskModeTag" class="kiosk-mode-tag">Memuat sesi...</span>
      </div>

      <!-- Kartu QR Utama yang Besar & Tengah -->
      <section id="kioskQrCard" class="kiosk-qr-card" aria-live="polite">
        <div id="kioskQrBox" class="kiosk-qr-box">
          <span class="muted">Memuat QR Code...</span>
        </div>

        <div class="kiosk-progress-wrap">
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:6px;color:#475569;">
            <span>QR diperbarui otomatis dalam:</span>
            <strong id="kioskCountdown" class="tabular">-- detik</strong>
          </div>
          <div class="kiosk-progress" aria-hidden="true">
            <div id="kioskProgressBar" class="kiosk-progress-bar"></div>
          </div>
        </div>
      </section>

      <!-- Informasi & Tulisan di Bawah QR -->
      <section class="kiosk-bottom-info">
        <p id="kioskModeDesc" class="kiosk-desc">
          Buka aplikasi Smart Attendance di ponsel Anda, aktifkan GPS, lalu pindai QR di atas untuk mencatat kehadiran.
        </p>

        <div id="kioskError" class="alert alert-error" role="alert" hidden></div>

        <!-- Baris Status Ringkas di Bawah -->
        <div class="kiosk-chips-row">
          <div class="kiosk-chip">
            <span class="kiosk-chip-label">Check-in Hari Ini:</span>
            <strong id="kioskCountIn" class="tabular">0</strong>
          </div>
          <div class="kiosk-chip">
            <span class="kiosk-chip-label">Check-out Hari Ini:</span>
            <strong id="kioskCountOut" class="tabular">0</strong>
          </div>
          <div class="kiosk-chip">
            <span class="kiosk-chip-label">Jadwal:</span>
            <strong class="tabular"><?= $e($schedule['work_start_time'] ?? '07:45') ?> - <?= $e($schedule['work_end_time'] ?? '14:00') ?></strong>
            <span style="opacity:0.75;font-size:0.8rem;">(Switch <?= $e($schedule['checkout_switch_time'] ?? '13:30') ?>)</span>
          </div>
        </div>

        <!-- Tombol Aksi Kontrol di Paling Bawah -->
        <div class="kiosk-controls-row">
          <button type="button" id="kioskPauseBtn" class="btn btn-sm kiosk-subtle-btn" aria-pressed="false">Jeda Rotasi QR</button>
          <button type="button" id="kioskRefreshBtn" class="btn btn-sm kiosk-subtle-btn">Perbarui QR Sekarang</button>
          <?php if (!empty($isAdmin)): ?>
            <label for="kioskModeSelect" class="sr-only">Mode QR</label>
            <select id="kioskModeSelect" class="kiosk-mode-select">
              <option value="auto">Otomatis sesuai jadwal</option>
              <option value="checkin">Paksa Mode Check-in</option>
              <option value="checkout">Paksa Mode Check-out</option>
            </select>
          <?php endif; ?>
        </div>
      </section>
    </main>

    <!-- Pop-up Notifikasi Suara & Visual TV Kantor -->
    <div id="kioskAttendanceAlert" class="kiosk-alert-overlay" aria-live="assertive" aria-atomic="true">
      <div class="kiosk-alert-card">
        <div id="kioskAlertBadge" class="kiosk-alert-badge mode-checkin">BERHASIL ABSEN MASUK</div>
        <div class="kiosk-alert-body">
          <div class="kiosk-alert-avatar-wrap">
            <img id="kioskAlertPhoto" src="" alt="Foto Karyawan" class="kiosk-alert-avatar" hidden>
            <div id="kioskAlertInitial" class="kiosk-alert-avatar kiosk-avatar-placeholder">--</div>
          </div>
          <div class="kiosk-alert-content">
            <div id="kioskAlertName" class="kiosk-alert-name">Nama Karyawan</div>
            <div id="kioskAlertMsg" class="kiosk-alert-msg">Berhasil absen masuk</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="/assets/js/kiosk.js" defer></script>
</body>
</html>
