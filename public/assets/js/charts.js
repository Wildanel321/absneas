/* Interactive Vanilla SVG Charts & Real-time Admin Dashboard Controller */
(function () {
  'use strict';

  function getPalette() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    return {
      text: dark ? '#cbd5e1' : '#334155',
      grid: dark ? '#273756' : '#e2e8f0',
      onTime: dark ? '#34d399' : '#0e7c5b',
      late: dark ? '#fbbf24' : '#d97706',
      absent: dark ? '#f87171' : '#b91c1c',
      leave: dark ? '#a899ff' : '#5b3fd6',
      sick: dark ? '#60a5fa' : '#1d4ed8',
    };
  }

  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function attachTooltip(container) {
    let tip = container.querySelector('.chart-tooltip');
    if (!tip) {
      tip = document.createElement('div');
      tip.className = 'chart-tooltip';
      tip.setAttribute('role', 'status');
      container.appendChild(tip);
    }

    container.querySelectorAll('[data-tip]').forEach((el) => {
      const show = () => {
        tip.innerHTML = el.getAttribute('data-tip') || '';
        tip.classList.add('visible');
        const rect = container.getBoundingClientRect();
        const elRect = el.getBoundingClientRect();
        const left = Math.min(Math.max(8, elRect.left - rect.left + elRect.width / 2 - 75), Math.max(8, rect.width - 190));
        const top = Math.max(4, elRect.top - rect.top - 52);
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
      };
      const hide = () => tip.classList.remove('visible');

      el.addEventListener('mouseenter', show);
      el.addEventListener('focus', show);
      el.addEventListener('click', show);
      el.addEventListener('mouseleave', hide);
      el.addEventListener('blur', hide);
    });
  }

  function renderStackedBars(containerId, items, seriesDefs, emptyText) {
    const el = document.getElementById(containerId);
    if (!el) return;

    const pal = getPalette();
    const maxVal = Math.max(
      1,
      ...items.map((item) => seriesDefs.reduce((acc, s) => acc + (Number(item[s.key]) || 0), 0))
    );

    const hasAny = items.some((item) => seriesDefs.some((s) => (Number(item[s.key]) || 0) > 0));
    if (!items.length || !hasAny) {
      el.innerHTML = `<div class="empty-state"><strong>Belum ada data pada periode ini</strong><span>${esc(emptyText)}</span></div>`;
      return;
    }

    const W = 560;
    const H = 220;
    const padL = 34;
    const padR = 12;
    const padT = 14;
    const padB = 34;
    const plotW = W - padL - padR;
    const plotH = H - padT - padB;
    const step = plotW / items.length;
    const barW = Math.max(10, Math.min(28, step * 0.62));

    let svg = `<svg viewBox="0 0 ${W} ${H}" width="${W}" height="${H}" role="img" aria-label="${esc(containerId)}">`;

    // Horizontal gridlines (4 steps)
    for (let i = 0; i <= 4; i++) {
      const val = Math.round((maxVal / 4) * i);
      const y = padT + plotH - (plotH * i) / 4;
      svg += `<line x1="${padL}" y1="${y}" x2="${W - padR}" y2="${y}" stroke="${pal.grid}" stroke-width="1"/>`;
      svg += `<text x="${padL - 6}" y="${y + 4}" text-anchor="end" fill="${pal.text}" font-size="11">${val}</text>`;
    }

    items.forEach((item, idx) => {
      const cx = padL + idx * step + step / 2;
      const x = cx - barW / 2;
      let yCursor = padT + plotH;

      const tipLines = [`<strong>${esc(item.label)}</strong>`];
      seriesDefs.forEach((s) => {
        const v = Number(item[s.key]) || 0;
        tipLines.push(`${esc(s.label)}: ${v}${s.unit || ''}`);
      });
      const tipHtml = tipLines.join('<br>');

      seriesDefs.forEach((s) => {
        const v = Number(item[s.key]) || 0;
        if (v <= 0) return;
        const h = Math.max(2, (v / maxVal) * plotH);
        yCursor -= h;
        svg += `<rect x="${x.toFixed(1)}" y="${yCursor.toFixed(1)}" width="${barW.toFixed(1)}" height="${h.toFixed(1)}" rx="2" fill="${s.color}" tabindex="0" data-tip="${esc(tipHtml)}"/>`;
      });

      const showTick = items.length <= 12 || idx % 2 === 0 || idx === items.length - 1;
      if (showTick) {
        svg += `<text x="${cx.toFixed(1)}" y="${H - 12}" text-anchor="middle" fill="${pal.text}" font-size="11">${esc(item.label)}</text>`;
      }
    });

    svg += '</svg>';
    const legend = seriesDefs
      .map(
        (s) =>
          `<span class="legend-item"><span class="legend-swatch" style="background:${s.color}"></span>${esc(s.label)}</span>`
      )
      .join('');

    el.innerHTML = svg + `<div class="chart-legend">${legend}</div>`;
    attachTooltip(el);
  }

  function renderGroupedBars(containerId, items, seriesDefs, emptyText) {
    const el = document.getElementById(containerId);
    if (!el) return;

    const pal = getPalette();
    const maxVal = Math.max(
      1,
      ...items.flatMap((item) => seriesDefs.map((s) => Number(item[s.key]) || 0))
    );
    const hasAny = items.some((item) => seriesDefs.some((s) => (Number(item[s.key]) || 0) > 0));
    if (!items.length || !hasAny) {
      el.innerHTML = `<div class="empty-state"><strong>Belum ada data pada periode ini</strong><span>${esc(emptyText)}</span></div>`;
      return;
    }

    const W = 560;
    const H = 220;
    const padL = 34;
    const padR = 12;
    const padT = 14;
    const padB = 34;
    const plotW = W - padL - padR;
    const plotH = H - padT - padB;
    const groupW = plotW / items.length;
    const singleW = Math.max(8, Math.min(20, (groupW * 0.72) / seriesDefs.length));

    let svg = `<svg viewBox="0 0 ${W} ${H}" width="${W}" height="${H}" role="img" aria-label="${esc(containerId)}">`;

    for (let i = 0; i <= 4; i++) {
      const val = Math.round((maxVal / 4) * i);
      const y = padT + plotH - (plotH * i) / 4;
      svg += `<line x1="${padL}" y1="${y}" x2="${W - padR}" y2="${y}" stroke="${pal.grid}" stroke-width="1"/>`;
      svg += `<text x="${padL - 6}" y="${y + 4}" text-anchor="end" fill="${pal.text}" font-size="11">${val}</text>`;
    }

    items.forEach((item, idx) => {
      const gx = padL + idx * groupW;
      const totalBarsW = singleW * seriesDefs.length + (seriesDefs.length - 1) * 4;
      const startX = gx + (groupW - totalBarsW) / 2;

      const tipLines = [`<strong>${esc(item.label)}</strong>`];
      seriesDefs.forEach((s) => {
        tipLines.push(`${esc(s.label)}: ${Number(item[s.key]) || 0}${s.unit || ''}`);
      });
      const tipHtml = tipLines.join('<br>');

      seriesDefs.forEach((s, si) => {
        const v = Number(item[s.key]) || 0;
        const h = v > 0 ? Math.max(2, (v / maxVal) * plotH) : 0;
        const y = padT + plotH - h;
        const x = startX + si * (singleW + 4);
        if (h > 0) {
          svg += `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${singleW.toFixed(1)}" height="${h.toFixed(1)}" rx="2" fill="${s.color}" tabindex="0" data-tip="${esc(tipHtml)}"/>`;
        }
      });

      svg += `<text x="${(gx + groupW / 2).toFixed(1)}" y="${H - 12}" text-anchor="middle" fill="${pal.text}" font-size="11">${esc(item.label)}</text>`;
    });

    svg += '</svg>';
    const legend = seriesDefs
      .map(
        (s) =>
          `<span class="legend-item"><span class="legend-swatch" style="background:${s.color}"></span>${esc(s.label)}</span>`
      )
      .join('');

    el.innerHTML = svg + `<div class="chart-legend">${legend}</div>`;
    attachTooltip(el);
  }

  function renderAllCharts(data) {
    if (!data || !data.charts) return;
    const pal = getPalette();

    renderStackedBars(
      'chartDailyAttendance',
      data.charts.daily_attendance || [],
      [
        { key: 'on_time', label: 'Tepat waktu', color: pal.onTime },
        { key: 'late', label: 'Terlambat', color: pal.late },
        { key: 'leave', label: 'Izin', color: pal.leave },
        { key: 'sick', label: 'Sakit', color: pal.sick },
      ],
      'Data kehadiran harian muncul setelah karyawan melakukan scan QR.'
    );

    renderStackedBars(
      'chartDailyLateness',
      data.charts.daily_lateness || [],
      [{ key: 'late', label: 'Jumlah terlambat', color: pal.late, unit: ' orang' }],
      'Tidak ada catatan keterlambatan dalam 14 hari terakhir.'
    );

    renderStackedBars(
      'chartMonthlyTrend',
      data.charts.monthly_trend || [],
      [
        { key: 'on_time', label: 'Tepat waktu', color: pal.onTime },
        { key: 'late', label: 'Terlambat', color: pal.late },
      ],
      'Belum ada catatan kehadiran pada tahun terpilih.'
    );

    renderGroupedBars(
      'chartLeaveSick',
      (data.charts.leave_sick && data.charts.leave_sick.by_week) || [],
      [
        { key: 'leave', label: 'Izin', color: pal.leave, unit: ' hari' },
        { key: 'sick', label: 'Sakit', color: pal.sick, unit: ' hari' },
      ],
      'Belum ada catatan izin atau sakit yang disetujui pada bulan ini.'
    );
  }

  function updateDomFromPayload(data) {
    const k = data.kpis || {};
    const map = {
      kpiPresent: k.present_today,
      kpiTotal: k.total_employees,
      kpiOnTime: k.on_time_today,
      kpiLate: k.late_today,
      kpiLeave: k.leave_today,
      kpiSick: k.sick_today,
      kpiAbsent: k.absent_today,
      kpiNotOut: k.not_checked_out_count,
    };
    Object.keys(map).forEach((id) => {
      const node = document.getElementById(id);
      if (node && map[id] !== undefined) {
        node.textContent = String(map[id]);
      }
    });

    const updatedEl = document.getElementById('dashboardUpdatedAt');
    if (updatedEl && data.generated_at) {
      updatedEl.textContent = 'Diperbarui ' + data.generated_at.slice(11, 19);
    }

    // Update Belum Check-out list
    const notOutBody = document.getElementById('notCheckedOutBody');
    if (notOutBody) {
      const list = data.not_checked_out || [];
      if (!list.length) {
        notOutBody.innerHTML =
          '<div class="empty-state"><strong>Semua karyawan hadir sudah check-out</strong><span>Atau belum ada karyawan yang check-in pada tanggal terpilih.</span></div>';
      } else {
        notOutBody.innerHTML =
          '<div class="table-wrap"><table><thead><tr><th>Karyawan</th><th>Departemen</th><th>Jam Masuk</th></tr></thead><tbody>' +
          list
            .map(
              (r) =>
                `<tr><td><strong>${esc(r.full_name)}</strong><div class="muted">${esc(r.employee_code)}</div></td><td>${esc(r.department_name)}</td><td class="tabular">${esc((r.checkin_at || '').slice(11, 16))}</td></tr>`
            )
            .join('') +
          '</tbody></table></div>';
      }
    }

    // Update Daily Roster table
    const rosterBody = document.getElementById('dailyRosterBody');
    if (rosterBody) {
      const rows = data.daily_roster || [];
      if (!rows.length) {
        rosterBody.innerHTML =
          '<tr><td colspan="6"><div class="empty-state"><strong>Tidak ada karyawan cocok dengan filter</strong><span>Ubah kata kunci pencarian atau pilih departemen lain.</span></div></td></tr>';
      } else {
        rosterBody.innerHTML = rows
          .map((r) => {
            const cin = r.checkin_at ? esc(r.checkin_at.slice(11, 16)) : '-';
            const cout = r.checkout_at ? esc(r.checkout_at.slice(11, 16)) : '-';
            const dist = r.checkin_distance_m !== null && r.checkin_distance_m !== undefined ? `${Number(r.checkin_distance_m).toFixed(0)} m` : '-';
            return `<tr>
              <td><a href="/admin/employees/${Number(r.user_id)}" style="color:inherit;text-decoration:none;"><strong>${esc(r.full_name)}</strong></a><div class="muted">${esc(r.employee_code)}</div></td>
              <td>${esc(r.department_name)}</td>
              <td class="tabular">${cin}</td>
              <td class="tabular">${cout}</td>
              <td class="tabular">${dist}</td>
              <td><span class="badge badge-${esc(r.status_tone)}">${esc(r.status_label)}</span></td>
            </tr>`;
          })
          .join('');
      }
    }

    // Update Ranking table
    const rankBody = document.getElementById('rankingBody');
    if (rankBody) {
      const ranks = data.discipline_ranking || [];
      if (!ranks.length) {
        rankBody.innerHTML =
          '<tr><td colspan="6"><div class="empty-state"><strong>Belum ada karyawan aktif</strong><span>Tambahkan karyawan di menu Karyawan untuk melihat ranking kedisiplinan.</span></div></td></tr>';
      } else {
        rankBody.innerHTML = ranks
          .map(
            (r, idx) => `<tr>
              <td class="tabular"><strong>#${idx + 1}</strong></td>
              <td><strong>${esc(r.full_name)}</strong><div class="muted">${esc(r.department_name)}</div></td>
              <td class="tabular">${Number(r.on_time_days)}</td>
              <td class="tabular">${Number(r.late_days)} (${Number(r.late_minutes)} mnt)</td>
              <td class="tabular">${Number(r.absent_days)}</td>
              <td style="min-width:130px;">
                <div class="tabular" style="font-weight:700;margin-bottom:4px;">${Number(r.discipline_score).toFixed(1)}%</div>
                <div class="progress-track"><div class="progress-fill" style="width:${Math.min(100, Math.max(0, Number(r.discipline_score)))}%"></div></div>
              </td>
            </tr>`
          )
          .join('');
      }
    }

    renderAllCharts(data);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const dataNode = document.getElementById('dashboardInitialData');
    if (!dataNode) return;

    let currentData = null;
    try {
      currentData = JSON.parse(dataNode.textContent || '{}');
    } catch (_) {
      currentData = null;
    }

    if (currentData) {
      renderAllCharts(currentData);
    }

    window.addEventListener('themechange', () => {
      if (currentData) renderAllCharts(currentData);
    });

    const filterForm = document.getElementById('dashboardFilterForm');
    const refreshBtn = document.getElementById('refreshDashboardBtn');
    const liveToggleBtn = document.getElementById('toggleLiveRefreshBtn');
    const statusBanner = document.getElementById('dashboardLiveError');
    let autoRefresh = true;

    async function fetchLatest() {
      const params = new URLSearchParams();
      if (filterForm) {
        const fd = new FormData(filterForm);
        fd.forEach((v, k) => {
          if (String(v).trim() !== '') params.set(k, String(v));
        });
      }
      if (refreshBtn) refreshBtn.disabled = true;
      try {
        const res = await fetch('/api/admin/dashboard?' + params.toString(), {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (json.ok && json.data) {
          currentData = json.data;
          updateDomFromPayload(currentData);
          if (statusBanner) statusBanner.hidden = true;
        }
      } catch (_) {
        if (statusBanner) {
          statusBanner.textContent = 'Gagal memperbarui data real-time. Periksa koneksi jaringan ke Raspberry Pi.';
          statusBanner.hidden = false;
        }
      } finally {
        if (refreshBtn) refreshBtn.disabled = false;
      }
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', fetchLatest);
    }

    if (liveToggleBtn) {
      liveToggleBtn.addEventListener('click', () => {
        autoRefresh = !autoRefresh;
        liveToggleBtn.setAttribute('aria-pressed', autoRefresh ? 'true' : 'false');
        liveToggleBtn.textContent = autoRefresh ? 'Jeda Pembaruan Otomatis' : 'Lanjutkan Pembaruan Otomatis';
      });
    }

    window.setInterval(() => {
      if (autoRefresh && !document.hidden) {
        fetchLatest();
      }
    }, 30000);
  });
})();
