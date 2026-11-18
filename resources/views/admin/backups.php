<section class="card accent-top">
  <div class="card-header">
    <div class="card-title-group">
      <h1>Backup &amp; Restore Database SQLite</h1>
      <p>Backup menggunakan SQLite Online Backup API agar aman saat database aktif menerima absensi, dengan retensi <?= (int) $retentionDays ?> hari.</p>
    </div>
    <form method="post" action="/admin/backups/create">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
      <button type="submit" class="btn btn-primary">Buat Backup Sekarang</button>
    </form>
  </div>

  <div class="kpi-grid" style="margin-bottom:16px;">
    <div class="kpi-card">
      <span class="kpi-label">Status Target USB</span>
      <span class="kpi-value" style="font-size:1.1rem;">
        <span class="badge <?= $usbAvailable ? 'badge-success' : 'badge-warning' ?>">
          <?= $usbAvailable ? 'USB Siap Ditulis' : 'USB Belum Terpasang' ?>
        </span>
      </span>
      <span class="kpi-sub"><?= $e($usbPath) ?></span>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Backup Lokal Tersimpan</span>
      <span class="kpi-value tabular"><?= count($backups) ?></span>
      <span class="kpi-sub">Disimpan di storage/backups</span>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Retensi Otomatis</span>
      <span class="kpi-value tabular"><?= (int) $retentionDays ?> hari</span>
      <span class="kpi-sub">File lebih lama dihapus otomatis</span>
    </div>
  </div>

  <?php if (empty($backups)): ?>
    <div class="empty-state">
      <strong>Belum ada file backup lokal</strong>
      <span>Tekan tombol Buat Backup Sekarang untuk membuat snapshot pertama database SQLite.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nama File Backup</th>
            <th>Waktu Dibuat</th>
            <th>Ukuran</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($backups as $b): ?>
            <tr>
              <td><strong><?= $e($b['filename']) ?></strong></td>
              <td class="tabular"><?= $e($b['created_at']) ?></td>
              <td class="tabular"><?= number_format(((int) $b['size_bytes']) / 1024, 1) ?> KB</td>
              <td>
                <div class="action-row">
                  <a href="/admin/backups/download/<?= rawurlencode((string) $b['filename']) ?>" class="btn btn-secondary btn-sm">Unduh</a>
                  <form method="post" action="/admin/backups/restore" data-confirm="Pulihkan database dari <?= $e($b['filename']) ?>? Snapshot keamanan otomatis dibuat sebelum restore.">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="backup_file" value="<?= $e($b['filename']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Restore</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card-header">
    <div class="card-title-group">
      <h2>Restore dari File Backup Eksternal (.sqlite)</h2>
      <p>Unggah file backup dari USB atau komputer Anda. Sistem memeriksa <code>PRAGMA integrity_check</code> dan menyimpan snapshot sebelum menimpa database aktif.</p>
    </div>
  </div>

  <form method="post" action="/admin/backups/restore" enctype="multipart/form-data" class="form-grid" data-confirm="Yakin ingin memulihkan database dari file unggahan ini?">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <div class="field">
      <label for="backupUpload">Pilih File Database (.sqlite)</label>
      <input id="backupUpload" name="backup_upload" type="file" accept=".sqlite" required>
    </div>
    <div style="align-self:flex-end;">
      <button type="submit" class="btn btn-danger">Unggah &amp; Restore Database</button>
    </div>
  </form>
</section>
