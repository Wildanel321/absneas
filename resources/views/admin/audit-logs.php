<section class="card accent-top">
  <div class="card-header">
    <div class="card-title-group">
      <h1>Log Audit Aktivitas Administrator</h1>
      <p>Catatan 150 aktivitas terakhir administrator pada sistem absensi.</p>
    </div>
  </div>

  <?php if (empty($logs)): ?>
    <div class="empty-state">
      <strong>Belum ada catatan audit</strong>
      <span>Aktivitas administrator seperti login, perubahan karyawan, aturan, dan backup akan muncul di sini.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Administrator</th>
            <th>Aksi</th>
            <th>Entitas</th>
            <th>Alamat IP</th>
            <th>Detail</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td class="tabular"><?= $e($log['created_at']) ?></td>
              <td>
                <strong><?= $e($log['actor_name'] ?? 'Sistem') ?></strong>
                <?php if (!empty($log['actor_username'])): ?>
                  <div class="muted"><?= $e($log['actor_username']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-neutral"><?= $e($log['action']) ?></span></td>
              <td class="tabular"><?= $e($log['entity_type']) ?> #<?= $e($log['entity_id']) ?></td>
              <td class="tabular"><?= $e($log['ip_address']) ?></td>
              <td style="max-width:320px;word-break:break-word;font-size:0.82rem;" class="muted"><?= $e($log['details_json']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
