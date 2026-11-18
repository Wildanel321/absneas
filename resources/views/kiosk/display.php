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
  <div id="kioskApp" data-kiosk-key="<?= $e($kioskKey ?? '') ?>" style="display:flex;flex-direction:column;min-height:100dvh;">
    <header class="kiosk-header">
      <div class="emp-header-brand">
        <img src="/icons/icon.svg" alt="" width="40" height="40" class="brand-mark">
        <div>
          <h1 style="color:#ffffff;font-size:1.3rem;margin:0;"><?= $e($officeName ?? 'Kantor Pusat') ?></h1>
          <div id="kioskDate" style="color:#cbd5e1;font-size:0.9rem;">Memuat tanggal...</div>
        </div>
      </div>

      <div class="action-row">
        <div id="kioskClock" class="kiosk-clock tabular">--:--:--</div>
        <button type="button" id="kioskFullscreenBtn" class="btn btn-sm" style="background:#162647;color:#ffffff;border-color:rgba(255,255,255,0.25);">Layar Penuh</button>
      </div>
    </header>

    <main class="kiosk-main">
      <section id="kioskQrCard" class="kiosk-qr-card" aria-live="polite">
        <div id="kioskQrBox" class="kiosk-qr-box">
          <span class="muted">Memuat QR Code...</span>
        </div>

        <div style="width:100%;">
          <div style="display:flex;justify-content:space-between;font-size:0.88rem;margin-bottom:6px;">
            <span>QR diperbarui otomatis dalam</span>
            <strong id="kioskCountdown" class="tabular">-- detik</strong>
          </div>
          <div class="kiosk-progress" aria-hidden="true">
            <div id="kioskProgressBar" class="kiosk-progress-bar"></div>
          </div>
        </div>
      </section>

      <section class="kiosk-info">
        <span id="kioskModeTag" class="kiosk-mode-tag">Memuat sesi...</span>
        <p id="kioskModeDesc" style="color:#e2e8f0;font-size:1.05rem;margin:0;">
          Buka aplikasi Smart Attendance di ponsel Anda, aktifkan GPS, lalu pindai QR di layar ini.
        </p>

        <div id="kioskError" class="alert alert-error" role="alert" hidden></div>

        <div class="kiosk-stat-row">
          <div class="kiosk-stat">
            <div id="kioskCountIn" class="kiosk-stat-num tabular">0</div>
            <div class="kiosk-stat-label">Sudah Check-in Hari Ini</div>
          </div>
          <div class="kiosk-stat">
            <div id="kioskCountOut" class="kiosk-stat-num tabular">0</div>
            <div class="kiosk-stat-label">Sudah Check-out Hari Ini</div>
          </div>
        </div>

        <div class="kiosk-stat" style="font-size:0.9rem;color:#cbd5e1;">
          <div>Jadwal kerja: <strong style="color:#fff;" class="tabular"><?= $e($schedule['work_start_time'] ?? '08:00') ?> - <?= $e($schedule['work_end_time'] ?? '17:00') ?></strong></div>
          <div>Peralihan otomatis ke QR Check-out pukul <strong style="color:#fff;" class="tabular"><?= $e($schedule['checkout_switch_time'] ?? '16:30') ?></strong></div>
        </div>

        <div class="action-row">
          <button type="button" id="kioskPauseBtn" class="btn btn-sm" style="background:#162647;color:#ffffff;border-color:rgba(255,255,255,0.25);" aria-pressed="false">Jeda Rotasi QR</button>
          <button type="button" id="kioskRefreshBtn" class="btn btn-sm" style="background:#162647;color:#ffffff;border-color:rgba(255,255,255,0.25);">Perbarui QR Sekarang</button>
          <?php if (!empty($isAdmin)): ?>
            <label for="kioskModeSelect" class="sr-only">Mode QR</label>
            <select id="kioskModeSelect" style="width:auto;min-height:36px;background:#162647;color:#ffffff;border-color:rgba(255,255,255,0.25);">
              <option value="auto">Otomatis sesuai jadwal</option>
              <option value="checkin">Paksa Mode Check-in</option>
              <option value="checkout">Paksa Mode Check-out</option>
            </select>
            <a href="/admin" class="btn btn-sm" style="background:#5b3fd6;color:#ffffff;">Kembali ke Dashboard</a>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>

  <script src="/assets/js/kiosk.js" defer></script>
</body>
</html>
