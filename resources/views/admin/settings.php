<?php
$dayLabels = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu',
];
$kioskUrl = '/kiosk?key=' . rawurlencode((string) ($kioskKey ?? ''));
?>

<section class="card accent-top">
  <div class="card-header">
    <div class="card-title-group">
      <h1>Aturan Absensi &amp; Lokasi Kantor</h1>
      <p>Konfigurasi jam kerja, toleransi keterlambatan, radius GPS Haversine, dan rotasi QR dinamis.</p>
    </div>
  </div>

  <form method="post" action="/admin/settings" class="form-grid">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

    <div class="field">
      <label for="office_name">Nama Lokasi Kantor</label>
      <input id="office_name" name="office_name" type="text" required value="<?= $e($settings['office_name'] ?? 'Kantor Pusat') ?>">
    </div>

    <div class="field">
      <label for="timezone">Zona Waktu</label>
      <select id="timezone" name="timezone">
        <?php foreach (['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'] as $tz): ?>
          <option value="<?= $tz ?>" <?= ($settings['timezone'] ?? 'Asia/Jakarta') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column:1 / -1;">
      <label for="paste_coords">Pilih Lokasi Kantor pada Peta (Leaflet OpenStreetMap)</label>
      <div class="action-row" style="margin-bottom:10px;">
        <input id="paste_coords" type="text" placeholder="Tempel koordinat Google Maps (contoh: -6.208765, 106.845612)" style="flex:1;min-width:260px;">
        <button type="button" id="applyPastedCoordsBtn" class="btn btn-secondary btn-sm" style="min-height:44px;">Terapkan dari Google Maps</button>
        <button type="button" id="fillOfficeGpsBtn" class="btn btn-secondary btn-sm" style="min-height:44px;">Gunakan GPS Perangkat Ini</button>
      </div>
      <div id="officeMap" style="height:360px;width:100%;border-radius:8px;border:1px solid var(--border);position:relative;z-index:1;"></div>
      <div style="margin-top:8px;">
        <span id="officeGpsStatus" class="hint">Geser pin biru pada peta atau klik langsung pada titik kantor. Lingkaran ungu menggambarkan radius batas absensi.</span>
      </div>
    </div>

    <div class="field">
      <label for="office_lat">Latitude Kantor (Garis Lintang)</label>
      <input id="office_lat" name="office_lat" type="number" step="0.000001" required value="<?= $e($settings['office_lat'] ?? '-6.200000') ?>">
      <span class="hint">Format desimal bertanda minus (-) untuk wilayah selatan khatulistiwa.</span>
    </div>

    <div class="field">
      <label for="office_lng">Longitude Kantor (Garis Bujur / BT)</label>
      <input id="office_lng" name="office_lng" type="number" step="0.000001" required value="<?= $e($settings['office_lng'] ?? '106.816666') ?>">
      <span class="hint">Format desimal positif untuk Bujur Timur Indonesia (95 s/d 141).</span>
    </div>

    <div class="field">
      <label for="geofence_radius_m">Radius Geofencing (meter)</label>
      <input id="geofence_radius_m" name="geofence_radius_m" type="number" min="10" max="5000" required value="<?= $e($settings['geofence_radius_m'] ?? '100') ?>">
      <span class="hint">Default 100 meter. Absensi di luar radius ditolak di server.</span>
    </div>

    <div class="field">
      <label for="max_gps_accuracy_m">Batas Akurasi GPS Maksimal (meter)</label>
      <input id="max_gps_accuracy_m" name="max_gps_accuracy_m" type="number" min="20" max="1000" required value="<?= $e($settings['max_gps_accuracy_m'] ?? '150') ?>">
      <span class="hint">Tolak absensi jika akurasi GPS browser lebih buruk dari nilai ini.</span>
    </div>

    <div class="field">
      <label for="work_start_time">Jam Masuk (Senin - Sabtu)</label>
      <input id="work_start_time" name="work_start_time" type="time" required value="<?= $e($settings['work_start_time'] ?? '07:45') ?>">
      <span class="hint">Check-in mulai dibuka jam ini.</span>
    </div>

    <div class="field">
      <label for="late_tolerance_minutes">Toleransi Keterlambatan (menit)</label>
      <input id="late_tolerance_minutes" name="late_tolerance_minutes" type="number" min="0" max="180" required value="<?= $e($settings['late_tolerance_minutes'] ?? '20') ?>">
      <span class="hint">20 menit dari 07:45 berarti tepat waktu hingga 08:05.</span>
    </div>

    <div class="field">
      <label for="work_end_time">Jam Pulang (Senin - Jumat)</label>
      <input id="work_end_time" name="work_end_time" type="time" required value="<?= $e($settings['work_end_time'] ?? '14:00') ?>">
      <span class="hint">Jam pulang reguler Senin s/d Jumat (14:00).</span>
    </div>

    <div class="field">
      <label for="saturday_work_end_time">Jam Pulang Khusus Hari Sabtu</label>
      <input id="saturday_work_end_time" name="saturday_work_end_time" type="time" required value="<?= $e($settings['saturday_work_end_time'] ?? '12:00') ?>">
      <span class="hint">Khusus hari Sabtu pulang lebih awal (12:00).</span>
    </div>

    <div class="field">
      <label for="checkout_open_before_minutes">Buka QR Check-out Sebelum Jam Pulang (menit)</label>
      <input id="checkout_open_before_minutes" name="checkout_open_before_minutes" type="number" min="0" max="240" required value="<?= $e($settings['checkout_open_before_minutes'] ?? '30') ?>">
      <span class="hint">Monitor QR otomatis berpindah ke Check-out pada waktu ini.</span>
    </div>

    <div class="field">
      <label for="qr_refresh_seconds">Interval Pergantian QR (detik)</label>
      <input id="qr_refresh_seconds" name="qr_refresh_seconds" type="number" min="10" max="300" required value="<?= $e($settings['qr_refresh_seconds'] ?? '30') ?>">
    </div>

    <div class="field">
      <label for="qr_token_ttl_seconds">Masa Berlaku Token QR (detik)</label>
      <input id="qr_token_ttl_seconds" name="qr_token_ttl_seconds" type="number" min="15" max="600" required value="<?= $e($settings['qr_token_ttl_seconds'] ?? '45') ?>">
      <span class="hint">Disarankan 10 sampai 15 detik lebih lama dari interval refresh.</span>
    </div>

    <div class="field" style="grid-column:1 / -1;">
      <span style="font-size:0.85rem;font-weight:600;">Hari Kerja</span>
      <div class="checkbox-row">
        <?php foreach ($dayLabels as $num => $name): ?>
          <label class="checkbox-chip">
            <input type="checkbox" name="working_days[]" value="<?= $num ?>" <?= in_array($num, $workingDays, true) ? 'checked' : '' ?>>
            <span><?= $name ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="grid-column:1 / -1;">
      <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
    </div>
  </form>
</section>

<section class="split-grid">
  <div class="card">
    <div class="card-header">
      <div class="card-title-group">
        <h2>Hari Libur Kantor</h2>
        <p>Karyawan tidak dianggap absen pada tanggal hari libur yang terdaftar.</p>
      </div>
    </div>

    <form method="post" action="/admin/holidays" class="form-grid" style="margin-bottom:16px;">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
      <div class="field">
        <label for="holDate">Tanggal Libur</label>
        <input id="holDate" name="holiday_date" type="date" required>
      </div>
      <div class="field">
        <label for="holDesc">Keterangan</label>
        <input id="holDesc" name="description" type="text" required minlength="2" maxlength="120" placeholder="Contoh: Libur Nasional">
      </div>
      <div style="align-self:flex-end;">
        <button type="submit" class="btn btn-secondary">Tambah Hari Libur</button>
      </div>
    </form>

    <?php if (empty($holidays)): ?>
      <div class="empty-state">
        <strong>Belum ada hari libur terdaftar</strong>
        <span>Tambahkan tanggal libur nasional atau cuti bersama kantor di atas.</span>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Keterangan</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($holidays as $h): ?>
              <tr>
                <td class="tabular"><strong><?= $e($h['holiday_date']) ?></strong></td>
                <td><?= $e($h['description']) ?></td>
                <td>
                  <form method="post" action="/admin/holidays/<?= (int) $h['id'] ?>/delete">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Tautan Monitor QR Kiosk</h2>
    <p class="muted">
      Buka tautan ini pada monitor kantor (misalnya Chromium kiosk di Raspberry Pi) agar QR tampil fullscreen tanpa perlu login administrator di layar publik.
    </p>
    <div class="field" style="margin-bottom:12px;">
      <label for="kioskLinkInput">URL Kiosk</label>
      <input id="kioskLinkInput" type="text" readonly value="<?= $e($kioskUrl) ?>">
    </div>
    <div class="action-row">
      <button type="button" class="btn btn-secondary btn-sm" data-copy-target="kioskLinkInput">Salin URL</button>
      <a href="<?= $e($kioskUrl) ?>" target="_blank" rel="noopener" class="btn btn-navy btn-sm">Buka Kiosk</a>
      <form method="post" action="/admin/settings/rotate-kiosk" data-confirm="Ganti kunci URL monitor QR? Monitor yang memakai tautan lama perlu dimuat ulang dengan URL baru.">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <button type="submit" class="btn btn-danger btn-sm">Ganti Kunci Kiosk</button>
      </form>
    </div>
  </div>
</section>
