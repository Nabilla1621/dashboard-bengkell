<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import Data - Monitoring Unit Service</title>
    <link rel="stylesheet" href="{{ secure_asset('css/dashboard.css') }}">
</head>
<body>
    <div class="content" style="max-width: 720px; margin: 0 auto; padding-top: 26px;">
        <header class="page-header" style="margin: 0 0 18px; border-radius: var(--radius); border-bottom: 0;">
            <div>
                <h1>Import Data</h1>
                <p class="muted">Pilih cabang, isi bagian yang mau diupdate (boleh sebagian aja), lalu klik Import Semua.</p>
            </div>
        </header>

        @if ($errors->any())
            <div class="card" style="border-color: #ef4444; margin-bottom: 16px;">
                @foreach ($errors->all() as $error)
                    <p class="muted" style="color: #ef4444; margin: 0;">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('part-bahan.store') }}" enctype="multipart/form-data" class="card" style="padding: 24px 26px;">
            @csrf

            <div class="group" style="margin-bottom: 24px;">
                <span class="legend">Cabang</span>
                <select name="cabang" required
                        style="width: 100%; padding: 11px 13px; font: inherit; font-size: .9rem; color: var(--text); background: var(--bg); border: 1px solid var(--border); border-radius: 10px;">
                    <option value="">-- Pilih cabang --</option>
                    @foreach ($cabangList as $c)
                        <option value="{{ $c }}" @selected(old('cabang') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Section 1: Revenue & Maintain PKB digabung — MaintainPKB WAJIB diproses duluan
                 kalau dua-duanya diupload bareng (faktur butuh no_pkb yang udah ada). --}}
            <div style="border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px;">
                <p style="margin: 0 0 4px; font-size: .75rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--purple);">Revenue & Maintain PKB</p>
                <p class="note" style="margin: 0 0 14px;">Sumber 5 card revenue (Jasa/Sparepart/Bahan) & 3 card unit entry di dashboard.</p>

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

            {{-- Section 2: Sparepart --}}
            <div style="border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px;">
                <p style="margin: 0 0 4px; font-size: .75rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--purple);">Sparepart</p>
                <p class="note" style="margin: 0 0 14px;">Sumber chart "Sparepart Terlaris".</p>

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

            {{-- Section 3: Bahan --}}
            <div style="border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
                <p style="margin: 0 0 4px; font-size: .75rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--purple);">Bahan</p>
                <p class="note" style="margin: 0 0 14px;">Sumber chart "Bahan Paling Banyak Dipakai".</p>

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

            <button type="submit"
                    style="width: 100%; padding: 13px; font: inherit; font-size: .95rem; font-weight: 700; color: #fff; background: linear-gradient(135deg, var(--pink), var(--purple)); border: none; border-radius: 10px; cursor: pointer;">
                Import Semua
            </button>
        </form>

        <p class="muted" style="text-align: center; margin-top: 16px;">
            <a href="{{ route('dashboard') }}">&larr; Kembali ke dashboard</a>
        </p>
    </div>
</body>
</html>
