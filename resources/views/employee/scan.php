<section
  id="scannerApp"
  class="card accent-top"
  data-csrf="<?= $e($csrf) ?>"
  data-office-lat="<?= $e($officeLat) ?>"
  data-office-lng="<?= $e($officeLng) ?>"
  data-radius="<?= $e($radiusM) ?>"
  data-max-accuracy="<?= $e($maxAccuracyM) ?>"
>
  <div class="card-header">
    <div class="card-title-group">
      <h1>Scan QR Absensi</h1>
      <p>Pindai QR pada monitor <?= $e($officeName) ?>. Validasi token dan jarak GPS dilakukan di server.</p>
    </div>
    <span class="badge badge-<?= $e($todayDesc['tone']) ?>"><?= $e($todayDesc['label']) ?></span>
  </div>

  <div id="scanFeedback" class="alert alert-info" role="status">
    Aktifkan GPS dan tekan Mulai Kamera untuk memindai QR Code pada layar monitor kantor.
  </div>

  <div class="gps-panel" style="margin-bottom:14px;">
    <div>
      <div class="muted" style="font-size:0.76rem;">Status Lokasi</div>
      <strong id="gpsStatusText">Menunggu GPS...</strong>
    </div>
    <div>
      <div class="muted" style="font-size:0.76rem;">Jarak ke Kantor</div>
      <strong id="gpsDistanceText" class="tabular">- / <?= (int) $radiusM ?> m</strong>
    </div>
    <div>
      <div class="muted" style="font-size:0.76rem;">Akurasi GPS</div>
      <strong id="gpsAccuracyText" class="tabular">-</strong>
    </div>
  </div>

  <div class="scanner-stage" style="margin-bottom:14px;">
    <video id="scannerVideo" playsinline muted width="720" height="540"></video>
    <div id="scannerReticle" class="scanner-reticle" aria-hidden="true">
      <div class="scanner-reticle-box"></div>
    </div>
    <div id="selfieReticle" class="selfie-reticle" style="display:none;" aria-hidden="true">
      <div class="selfie-face-oval"></div>
      <span class="selfie-label">Posisikan wajah Anda untuk foto selfie singkat</span>
    </div>
  </div>

  <div id="selfieActionRow" class="action-row" style="display:none;margin-bottom:14px;">
    <button type="button" id="snapSelfieBtn" class="btn btn-primary" style="min-height:44px;width:100%;">
      Ambil Foto Selfie &amp; Kirim Absensi
    </button>
  </div>

  <div class="action-row">
    <button type="button" id="startScanBtn" class="btn btn-primary">Mulai Kamera</button>
    <button type="button" id="stopScanBtn" class="btn btn-danger" hidden>Hentikan Kamera</button>
    <button type="button" id="retryGpsBtn" class="btn btn-secondary">Muat Ulang GPS</button>
    <a href="/app" class="btn btn-ghost">Kembali ke Beranda</a>
  </div>
</section>

<section class="card">
  <h2>Ketentuan Absensi</h2>
  <p class="muted" style="margin-bottom:0;">
    Radius geofencing kantor saat ini <?= (int) $radiusM ?> meter dengan batas akurasi GPS maksimal <?= (int) $maxAccuracyM ?> meter.
    Sesi QR di monitor berpindah otomatis ke Check-out pukul <?= $e($schedule['checkout_switch_time']) ?>.
    Absensi memerlukan koneksi aktif ke server dan tidak dapat disimpan secara offline.
  </p>
</section>
