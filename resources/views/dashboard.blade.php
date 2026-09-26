<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Monitoring Unit Service</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <script>
        // FIX kedip: sembunyiin dulu SELURUH halaman (sebelum browser sempat nge-paint
        // apapun) kalau ketauan ada posisi scroll yang mau dipulihin. Jadi user ga pernah
        // liat momen "render dari atas dulu, baru lompat ke bawah" — yang tadi kelihatan
        // kayak kedip. Ini HARUS sinkron & di <head>, biar kejalan SEBELUM body kerender.
        (function () {
            if (sessionStorage.getItem('dashboardScrollY') !== null) {
                document.documentElement.style.visibility = 'hidden';
            }
        })();
    </script>
</head>
<body>
@php
    $rp  = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $pct = fn ($n) => $totalRevenue > 0 ? round($n / $totalRevenue * 100, 1) : 0;
    $tgl = fn ($d) => \Carbon\Carbon::parse($d)->format('d/m/Y');
    $maxTipe = $perTipe->max('jumlah') ?: 1;
    $maxJenis = $perJenisService->max('jumlah') ?: 1;
    $maxBooking = $perBookingWalkin->max('jumlah') ?: 1;
    $maxSparepart = $topSparepart->max('jumlah') ?: 1;
    $maxBahan = $topBahan->max('jumlah') ?: 1;
@endphp

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <span class="badge">KS</span>
            <div>
                <strong>Kharisma Sentosa</strong>
                <small>Monitoring Unit Service</small>
            </div>
            <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Tutup menu">✕</button>
        </div>

        <button type="button" id="riwayatBtnSidebar" class="sidebar-riwayat-btn">
            <span class="btn-icon">🕓</span> Riwayat Import
        </button>

        <form method="GET" action="{{ url('/') }}" class="filters">
            <fieldset class="group">
                <legend>Filter periode</legend>
                <div class="pills">
                    @foreach (['harian' => 'Harian', 'bulanan' => 'Bulanan', 'tahunan' => 'Tahunan'] as $key => $label)
                        <label class="choice">
                            <input type="radio" name="periode" value="{{ $key }}" @checked($periode === $key) onchange="this.form.submit()">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="group">
                <span class="legend">Tanggal</span>
                <input type="date" name="tanggal" value="{{ $tanggal }}" onchange="this.form.submit()">
            </label>

            <fieldset class="group">
                <legend>Filter cabang</legend>
                <div class="options">
                    <label class="choice">
                        <input type="radio" name="cabang" value="" @checked(! $cabang) onchange="this.form.submit()">
                        <span>Semua cabang</span>
                    </label>
                    @foreach ($cabangList as $c)
                        <label class="choice">
                            <input type="radio" name="cabang" value="{{ $c }}" @checked($c === $cabang) onchange="this.form.submit()">
                            <span>{{ $c }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <noscript><button type="submit">Terapkan</button></noscript>
        </form>
    </aside>

    <main class="content">
        <header class="page-header sticky-header">
            <div class="header-left">
                <button type="button" id="menuToggle" class="menu-btn" aria-label="Buka menu filter">☰</button>
                <div class="header-titles">
                    <h1>Dashboard</h1>
                    <p class="muted">Menampilkan data <strong>{{ $labelPeriode }}</strong>, {{ $cabang ?: 'semua cabang' }}</p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" id="riwayatBtn" class="btn-header btn-riwayat"
                        style="background: var(--surface); color: var(--text); border: 1px solid var(--border);">
                    <span class="btn-icon">🕓</span>
                    <span class="btn-label">Riwayat Import</span>
                </button>
                <button type="button" id="importBtn" class="btn-header">
                    <span class="btn-icon">📥</span>
                    <span class="btn-label">Import Data</span>
                </button>
                <button type="button" id="themeToggle" class="theme-icon-btn" aria-label="Ganti tema">
                    <span class="icon-light">☀️</span>
                    <span class="icon-dark">🌙</span>
                </button>
            </div>
        </header>

        @if (session('status'))
            <div class="card" style="border-color: var(--teal); margin-bottom: 14px;">
                <p class="muted" style="margin: 0; color: var(--text);">{{ session('status') }}</p>
            </div>
        @endif

        {{-- ============ KPI — kartu gradient warna-warni ============ --}}
        <section class="kpis">
            <div class="card kpi kpi-unit">
                <span class="kpi-icon">🚗</span>
                <h2>Unit entry</h2>
                <p class="value">{{ number_format($unitEntry, 0, ',', '.') }}</p>
                <p class="note">Booking {{ $countBooking }} · Walk-in {{ $countWalkIn }}</p>
            </div>
            <div class="card kpi kpi-jasa">
                <span class="kpi-icon">🛠️</span>
                <h2>Jasa</h2>
                <p class="value">{{ $rp($rev->jasa) }}</p>
                <p class="note">{{ $pct($rev->jasa) }}% dari revenue</p>
            </div>
            <div class="card kpi kpi-sparepart">
                <span class="kpi-icon">⚙️</span>
                <h2>Sparepart</h2>
                <p class="value">{{ $rp($rev->sparepart) }}</p>
                <p class="note">{{ $pct($rev->sparepart) }}% dari revenue</p>
            </div>
            <div class="card kpi kpi-bahan">
                <span class="kpi-icon">🧴</span>
                <h2>Bahan</h2>
                <p class="value">{{ $rp($rev->bahan) }}</p>
                <p class="note">{{ $pct($rev->bahan) }}% dari revenue</p>
            </div>
            <div class="card kpi kpi-accent">
                <span class="kpi-icon">💰</span>
                <h2>Revenue</h2>
                <p class="value">{{ $rp($totalRevenue) }}</p>
                <p class="note">Jasa + sparepart + bahan</p>
            </div>
        </section>

        {{-- ============ Bento: tipe kendaraan (5) · jenis service (4) · booking/walkin (3) ============ --}}
        <div class="visual-group">
        <section class="breakdown">
            <div class="card span-5">
                <h2>Unit entry per tipe kendaraan</h2>
                <div class="bar-list">
                    @forelse ($perTipe as $row)
                        <div class="bar-row">
                            <div class="bar-top"><span>{{ $row->label ?: '-' }}</span><b>{{ number_format($row->jumlah, 0, ',', '.') }}</b></div>
                            <div class="bar-track"><div class="bar-fill" style="width: {{ round($row->jumlah / $maxTipe * 100) }}%"></div></div>
                        </div>
                    @empty
                        <p class="empty">Belum ada data untuk periode/cabang ini.</p>
                    @endforelse
                </div>
            </div>

            <div class="card span-4">
                <h2>Unit entry per jenis service</h2>
                <div class="bar-list">
                    @forelse ($perJenisService as $row)
                        <div class="bar-row">
                            <div class="bar-top"><span>{{ $row->label }}</span><b>{{ number_format($row->jumlah, 0, ',', '.') }}</b></div>
                            <div class="bar-track"><div class="bar-fill bar-fill-alt" style="width: {{ round($row->jumlah / $maxJenis * 100) }}%"></div></div>
                        </div>
                    @empty
                        <p class="empty">Belum ada data untuk periode/cabang ini.</p>
                    @endforelse
                </div>
            </div>

            <div class="card span-3">
                <h2>Booking / walk-in <span class="hint">(bulanan)</span></h2>
                @if ($perBookingWalkin->isEmpty())
                    <p class="empty">Belum ada data untuk periode/cabang ini.</p>
                @else
                    @php $bwTotal = $perBookingWalkin->sum('jumlah') ?: 1; @endphp
                    <div class="donut-wrap">
                        <div class="donut-canvas-wrap">
                            <canvas id="chartBooking"></canvas>
                            <div class="donut-hole">
                                <span class="donut-total">{{ number_format($bwTotal, 0, ',', '.') }}</span>
                                <span class="donut-label">Unit</span>
                            </div>
                        </div>
                        <div class="donut-legend">
                            @foreach ($perBookingWalkin as $i => $row)
                                <div class="legend-row">
                                    <span class="legend-dot" style="background: var(--chart-color-{{ $i % 5 }});"></span>
                                    <span class="legend-label">{{ $row->label }}</span>
                                    <b class="legend-value">{{ number_format($row->jumlah, 0, ',', '.') }}</b>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- ============ Sparepart & bahan — bar chart, bukan list ============ --}}
        <section class="breakdown">
            <div class="card span-6">
                <h2>Sparepart terlaris <span class="hint">(top 8, dari billing part & service)</span></h2>
                @if ($topSparepart->isEmpty())
                    <p class="empty">Belum ada data part & bahan untuk periode/cabang ini. Klik "Import Data" di atas.</p>
                @else
                    <div class="chart-summary">
                        <span class="total-value">{{ $rp($topSparepart->sum('jumlah')) }}</span>
                        <span class="total-label">total 8 sparepart teratas</span>
                    </div>
                    <div class="chart-box"><canvas id="chartSparepart"></canvas></div>
                @endif
            </div>

            <div class="card span-6">
                <h2>Bahan paling banyak dipakai <span class="hint">(top 8, dari nota bahan)</span></h2>
                @if ($topBahan->isEmpty())
                    <p class="empty">Belum ada data part & bahan untuk periode/cabang ini. Klik "Import Data" di atas.</p>
                @else
                    <div class="chart-summary">
                        <span class="total-value">{{ $rp($topBahan->sum('jumlah')) }}</span>
                        <span class="total-label">total 8 bahan teratas</span>
                    </div>
                    <div class="chart-box"><canvas id="chartBahan"></canvas></div>
                @endif
            </div>
        </section>
        </div>

        <section class="card" id="daftar-entry">
            <h2>Daftar unit entry</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No. PKB</th>
                            <th>Cabang</th>
                            <th>Tipe kendaraan</th>
                            <th>Type</th>
                            <th class="num">Total revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $e)
                            <tr>
                                <td>{{ $tgl($e->tanggal) }}</td>
                                <td>{{ $e->no_pkb }}</td>
                                <td>{{ $e->cabang }}</td>
                                <td>{{ $e->tipe_kendaraan }}</td>
                                <td>{{ $e->type }}</td>
                                <td class="num">{{ $rp($e->total_jasa + $e->total_sparepart + $e->total_bahan) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">Belum ada unit entry untuk periode/cabang ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($entries->lastPage() > 1)
                @php $current = $entries->currentPage(); $last = $entries->lastPage(); $prev = 0; @endphp
                <nav class="pager">
                    <a class="page-nav @if ($entries->onFirstPage()) disabled @endif"
                       @if (! $entries->onFirstPage()) href="{{ $entries->previousPageUrl() }}#daftar-entry" @endif>&lsaquo;</a>

                    @for ($p = 1; $p <= $last; $p++)
                        @if ($p == 1 || $p == $last || abs($p - $current) <= 1)
                            @if ($prev && $p - $prev > 1)
                                <span class="page-dots">…</span>
                            @endif
                            @if ($p === $current)
                                <span class="page-num page-num-active">{{ $p }}</span>
                            @else
                                <a class="page-num" href="{{ $entries->url($p) }}#daftar-entry">{{ $p }}</a>
                            @endif
                            @php $prev = $p; @endphp
                        @endif
                    @endfor

                    <a class="page-nav @if (! $entries->hasMorePages()) disabled @endif"
                       @if ($entries->hasMorePages()) href="{{ $entries->nextPageUrl() }}#daftar-entry" @endif>&rsaquo;</a>
                </nav>
            @endif
        </section>
    </main>
</div>

<div class="modal-overlay @if ($errors->any()) is-open @endif" id="importOverlay"></div>
<div class="modal @if ($errors->any()) is-open @endif" id="importModal" style="max-width: 640px;">
    <div class="modal-header">
        <h2>Import Data</h2>
        <button type="button" class="modal-close" id="importClose" aria-label="Tutup">✕</button>
    </div>

    @if ($errors->any())
        <div class="card" style="border-color: #ef4444; margin-bottom: 16px;">
            @foreach ($errors->all() as $error)
                <p class="muted" style="color: #ef4444; margin: 0;">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('part-bahan.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="group" style="margin-bottom: 18px;">
            <span class="legend">Cabang</span>
            <select name="cabang" required>
                <option value="">-- Pilih cabang --</option>
                @foreach ($cabangList as $c)
                    <option value="{{ $c }}" @selected(old('cabang') === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>

        <div style="border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px;">
            <p style="margin: 0 0 4px; font-size: .75rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--purple);">Revenue & Maintain PKB</p>
            <p class="note" style="margin: 0 0 12px;">Sumber 5 card revenue & 3 card unit entry.</p>

            <div class="import-grid">
                <div class="group">
                    <span class="legend">Maintain PKB (unit entry)</span>
                    <input type="file" name="maintain_pkb_file" accept=".xls,.xlsx,.html,.htm">
                    <p class="note" style="margin-top: 6px;">MaintainPKB_*.xls (opsional)</p>
                </div>

                <div class="group">
                    <span class="legend">Service Penjualan (revenue)</span>
                    <input type="file" name="service_penjualan_file" accept=".xls,.xlsx">
                    <p class="note" style="margin-top: 6px;">RptServicePenjualan_*.xls (opsional — cabang ini harus udah pernah upload Maintain PKB)</p>
                </div>
            </div>
        </div>

        <div style="border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px;">
            <p style="margin: 0 0 4px; font-size: .75rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--purple);">Sparepart</p>
            <p class="note" style="margin: 0 0 12px;">Sumber chart "Sparepart Terlaris".</p>

            <div class="import-grid">
                <div class="group">
                    <span class="legend">Billing Part</span>
                    <input type="file" name="billing_part" accept=".xls,.xlsx">
                    <p class="note" style="margin-top: 6px;">RptPenjualanPart_billing_part_*.xls (opsional)</p>
                </div>

                <div class="group">
                    <span class="legend">Billing Service</span>
                    <input type="file" name="billing_service" accept=".xls,.xlsx">
                    <p class="note" style="margin-top: 6px;">RptPenjualanPart_billing_service_*.xls (opsional)</p>
                </div>
            </div>
        </div>

        <div style="border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; margin-bottom: 22px;">
            <p style="margin: 0 0 4px; font-size: .75rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--purple);">Bahan</p>
            <p class="note" style="margin: 0 0 12px;">Sumber chart "Bahan Paling Banyak Dipakai".</p>

            <div class="import-grid">
                <div class="group">
                    <span class="legend">Nota Bahan — Consumable</span>
                    <input type="file" name="comsumable" accept=".xls,.xlsx">
                    <p class="note" style="margin-top: 6px;">RptNotaBahan_comsumable_*.xls (opsional)</p>
                </div>

                <div class="group">
                    <span class="legend">Nota Bahan — PKB</span>
                    <input type="file" name="nota_pkb" accept=".xls,.xlsx">
                    <p class="note" style="margin-top: 6px;">RptNotaBahan_pkb_*.xls (opsional)</p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-header" style="width: 100%; justify-content: center; border-radius: 10px; padding: 12px;">
            Import Semua
        </button>
    </form>
</div>

<div class="modal-overlay @if (session('riwayat_terbuka')) is-open @endif" id="riwayatOverlay"></div>
<div class="modal @if (session('riwayat_terbuka')) is-open @endif" id="riwayatModal" style="max-width: 860px;">
    <div class="modal-header">
        <h2>Riwayat Import</h2>
        <button type="button" class="modal-close" id="riwayatClose" aria-label="Tutup">✕</button>
    </div>

    <p class="muted" style="margin: -8px 0 16px;">Salah upload file? Cari batch-nya di bawah, klik Hapus — datanya ikut kehapus otomatis dari dashboard.</p>

    @if (session('status'))
        <div class="card" style="border-color: var(--teal); margin-bottom: 16px;">
            <p class="muted" style="margin: 0; color: var(--text);">{{ session('status') }}</p>
        </div>
    @endif

    <div class="table-scroll" style="max-height: 60vh;">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Data</th>
                    <th>Cabang</th>
                    <th>File</th>
                    <th class="num">Baris</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($importBatches as $batch)
                    <tr>
                        <td>{{ $batch->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $batch->labelJenis() }}</td>
                        <td>{{ $batch->cabang ?: '-' }}</td>
                        <td>{{ $batch->nama_file ?: '-' }}</td>
                        <td class="num">{{ number_format($batch->jumlah_baris, 0, ',', '.') }}</td>
                        <td>
                            <form method="POST" action="{{ route('import-batches.destroy', $batch) }}"
                                  onsubmit="return confirm('Yakin hapus import ini? Semua data yang masuk dari import ini akan ikut terhapus.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        style="padding: 6px 12px; font: inherit; font-size: .76rem; font-weight: 700; color: #ef4444; background: transparent; border: 1px solid #ef4444; border-radius: 999px; cursor: pointer; white-space: nowrap;">
                                    Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada riwayat import.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============ Chart.js — coba cdnjs dulu, kalau gagal fallback ke jsdelivr ============ --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"
        onerror="var s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';s.onload=function(){document.dispatchEvent(new Event('chartjs-ready'));};document.head.appendChild(s);"
        onload="document.dispatchEvent(new Event('chartjs-ready'));"></script>
<script>
    // Palet warna dipakai konsisten di semua chart + legend donut (lihat --chart-color-N di bawah)
    var CHART_PALETTE = ['#ec4899', '#8b5cf6', '#3b82f6', '#14b8a6', '#f59e0b'];
    document.documentElement.style.setProperty('--chart-color-0', CHART_PALETTE[0]);
    document.documentElement.style.setProperty('--chart-color-1', CHART_PALETTE[1]);
    document.documentElement.style.setProperty('--chart-color-2', CHART_PALETTE[2]);
    document.documentElement.style.setProperty('--chart-color-3', CHART_PALETTE[3]);
    document.documentElement.style.setProperty('--chart-color-4', CHART_PALETTE[4]);

    function cssVar(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function formatRupiahCompact(n) {
        n = Number(n) || 0;
        if (n >= 1000000000) return 'Rp' + (n / 1000000000).toFixed(1).replace('.0', '') + 'M';
        if (n >= 1000000) return 'Rp' + (n / 1000000).toFixed(1).replace('.0', '') + 'jt';
        if (n >= 1000) return 'Rp' + Math.round(n / 1000) + 'rb';
        return 'Rp' + n;
    }
    function formatRupiahFull(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    var ALL_CHARTS = [];

    function baseScales(isCurrency) {
        return {
            x: {
                beginAtZero: true,
                grid: { color: cssVar('--grid-line') },
                ticks: {
                    color: cssVar('--muted'),
                    callback: isCurrency ? function (v) { return formatRupiahCompact(v); } : undefined
                }
            },
            y: {
                grid: { display: false },
                ticks: { color: cssVar('--text'), font: { size: 11.5 } }
            }
        };
    }

    function showChartError(canvasId, msg) {
        var el = document.getElementById(canvasId);
        if (!el || !el.parentNode) return;
        var box = document.createElement('p');
        box.className = 'empty';
        box.textContent = msg;
        el.parentNode.replaceChild(box, el);
    }

    function makeHBar(canvasId, labels, values, isCurrency, extraTooltip) {
        if (typeof Chart === 'undefined') {
            showChartError(canvasId, 'Grafik gagal dimuat (Chart.js tidak ke-load — cek koneksi internet / adblock).');
            return null;
        }
        var el = document.getElementById(canvasId);
        if (!el || !labels.length) return null;
        try {
        var chart = new Chart(el.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(function (_, i) { return CHART_PALETTE[i % CHART_PALETTE.length]; }),
                    borderRadius: 6,
                    maxBarThickness: 14,
                    barPercentage: 0.7,
                    categoryPercentage: 0.6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.raw;
                                var main = isCurrency ? formatRupiahFull(v) : v;
                                var extra = extraTooltip ? extraTooltip[ctx.dataIndex] : null;
                                return extra ? (main + ' · ' + extra) : main;
                            }
                        }
                    }
                },
                scales: baseScales(isCurrency)
            }
        });
        ALL_CHARTS.push({ chart: chart, isCurrency: isCurrency });
        return chart;
        } catch (err) {
            console.error('Gagal bikin chart ' + canvasId, err);
            showChartError(canvasId, 'Grafik gagal ditampilkan. Cek console browser untuk detail error.');
            return null;
        }
    }

    function makeDonut(canvasId, labels, values) {
        if (typeof Chart === 'undefined') {
            showChartError(canvasId, 'Grafik gagal dimuat.');
            return null;
        }
        var el = document.getElementById(canvasId);
        if (!el || !labels.length) return null;
        try {
        var chart = new Chart(el.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(function (_, i) { return CHART_PALETTE[i % CHART_PALETTE.length]; }),
                    borderColor: cssVar('--surface'),
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '74%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return ctx.label + ': ' + ctx.raw; }
                        }
                    }
                }
            }
        });
        ALL_CHARTS.push({ chart: chart, isCurrency: false, isDonut: true });
        return chart;
        } catch (err) {
            console.error('Gagal bikin chart ' + canvasId, err);
            showChartError(canvasId, 'Grafik gagal ditampilkan. Cek console browser untuk detail error.');
            return null;
        }
    }

    function refreshChartsTheme() {
        ALL_CHARTS.forEach(function (entry) {
            var c = entry.chart;
            if (entry.isDonut) {
                c.data.datasets[0].borderColor = cssVar('--surface');
            } else {
                c.options.scales.x.grid.color = cssVar('--grid-line');
                c.options.scales.x.ticks.color = cssVar('--muted');
                c.options.scales.y.ticks.color = cssVar('--text');
            }
            c.update();
        });
    }

    var CHARTJS_STATE = { domReady: false, libReady: false, libTimedOut: false, done: false };

    function initAllChartsOnce() {
        if (CHARTJS_STATE.done) return;
        if (!CHARTJS_STATE.domReady) return;
        if (!CHARTJS_STATE.libReady && !CHARTJS_STATE.libTimedOut) return;
        CHARTJS_STATE.done = true;
        initAllCharts();
    }

    document.addEventListener('DOMContentLoaded', function () {
        CHARTJS_STATE.domReady = true;
        initAllChartsOnce();
    });
    document.addEventListener('chartjs-ready', function () {
        CHARTJS_STATE.libReady = true;
        initAllChartsOnce();
    });
    // Kalau dalam 4 detik Chart.js belum juga ke-load (kedua CDN gagal), tetap jalan
    // supaya tiap chart-box nampilin pesan error yang jelas, bukan kosong tanpa keterangan.
    setTimeout(function () {
        CHARTJS_STATE.libTimedOut = true;
        initAllChartsOnce();
    }, 4000);

    function initAllCharts() {
        makeDonut(
            'chartBooking',
            @json($perBookingWalkin->pluck('label')),
            @json($perBookingWalkin->pluck('jumlah'))
        );
        makeHBar(
            'chartSparepart',
            @json($topSparepart->pluck('label')),
            @json($topSparepart->pluck('jumlah')),
            true
        );
        makeHBar(
            'chartBahan',
            @json($topBahan->pluck('label')),
            @json($topBahan->pluck('jumlah')),
            true
        );
    }
</script>

<script>
    (function () {
        // FIX v3: v2 emang udah bener posisinya, tapi masih kedip karena browser SEMPAT
        // nge-render halaman dari atas dulu (state default), baru abis itu kita scroll —
        // mata kamu nangkep momen "kelihatan di atas sebentar" itu. Sudah diredam dari
        // <head> (halaman disembunyiin duluan kalau ada scroll yang mau dipulihin).
        //
        // Di sini kita: (1) langsung scroll begitu skrip ini kebaca — TANPA nunggu event
        // 'load' lagi, karena tinggi halaman udah final dari HTML/CSS (chart-box punya
        // min-height sendiri, jadi ga geser pas Chart.js nyusul render) — lalu (2) baru
        // tampilin lagi halaman yang disembunyiin tadi.
        history.scrollRestoration = 'manual';

        window.addEventListener('beforeunload', function () {
            sessionStorage.setItem('dashboardScrollY', window.scrollY);
        });

        var savedScroll = sessionStorage.getItem('dashboardScrollY');
        if (savedScroll !== null) {
            window.scrollTo(0, parseInt(savedScroll, 10));
            sessionStorage.removeItem('dashboardScrollY');
        }
        document.documentElement.style.visibility = 'visible';
    })();

    (function () {
        var html = document.documentElement;
        var saved = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', saved);

        document.getElementById('themeToggle').addEventListener('click', function () {
            var current = html.getAttribute('data-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            if (typeof refreshChartsTheme === 'function') refreshChartsTheme();
        });
    })();

    (function () {
        var body = document.body;
        var menuToggle = document.getElementById('menuToggle');
        var sidebarClose = document.getElementById('sidebarClose');
        var sidebarOverlay = document.getElementById('sidebarOverlay');

        function openSidebar() { body.classList.add('sidebar-open'); }
        function closeSidebar() { body.classList.remove('sidebar-open'); }

        menuToggle.addEventListener('click', openSidebar);
        sidebarClose.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('click', closeSidebar);
    })();

    (function () {
        function wireModal(btnId, modalId, overlayId, closeId) {
            var btn = document.getElementById(btnId);
            var modal = document.getElementById(modalId);
            var overlay = document.getElementById(overlayId);
            var close = document.getElementById(closeId);

            function open() { modal.classList.add('is-open'); overlay.classList.add('is-open'); }
            function hide() { modal.classList.remove('is-open'); overlay.classList.remove('is-open'); }

            if (btn) btn.addEventListener('click', open);
            close.addEventListener('click', hide);
            overlay.addEventListener('click', hide);
        }

        wireModal('importBtn', 'importModal', 'importOverlay', 'importClose');
        wireModal('riwayatBtn', 'riwayatModal', 'riwayatOverlay', 'riwayatClose');

        var riwayatSidebarBtn = document.getElementById('riwayatBtnSidebar');
        if (riwayatSidebarBtn) {
            riwayatSidebarBtn.addEventListener('click', function () {
                document.getElementById('riwayatModal').classList.add('is-open');
                document.getElementById('riwayatOverlay').classList.add('is-open');
                document.body.classList.remove('sidebar-open');
            });
        }
    })();
</script>
</body>
</html>