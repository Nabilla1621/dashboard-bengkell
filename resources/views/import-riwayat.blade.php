<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Import - Monitoring Unit Service</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>
    <div class="content" style="max-width: 960px; margin: 0 auto; padding-top: 26px;">
        <header class="page-header" style="margin: 0 0 18px; border-radius: var(--radius); border-bottom: 0;">
            <div>
                <h1>Riwayat Import</h1>
                <p class="muted">Salah upload file? Cari batch-nya di bawah, klik Hapus — datanya ikut kehapus otomatis dari dashboard.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="card" style="border-color: var(--teal); margin-bottom: 14px;">
                <p class="muted" style="margin: 0; color: var(--text);">{{ session('status') }}</p>
            </div>
        @endif

        <section class="card">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Import</th>
                            <th>Jenis Data</th>
                            <th>Cabang</th>
                            <th>Nama File</th>
                            <th class="num">Baris Tersimpan</th>
                            <th class="num">Baris Dilewati</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr>
                                <td>{{ $batch->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $batch->labelJenis() }}</td>
                                <td>{{ $batch->cabang ?: '-' }}</td>
                                <td>{{ $batch->nama_file ?: '-' }}</td>
                                <td class="num">{{ number_format($batch->jumlah_baris, 0, ',', '.') }}</td>
                                <td class="num">{{ number_format($batch->jumlah_dilewati, 0, ',', '.') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('import-batches.destroy', $batch) }}"
                                          onsubmit="return confirm('Yakin hapus import ini? Semua data yang masuk dari import ini akan ikut terhapus.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                style="padding: 7px 14px; font: inherit; font-size: .78rem; font-weight: 700; color: #ef4444; background: transparent; border: 1px solid #ef4444; border-radius: 999px; cursor: pointer; white-space: nowrap;">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty">Belum ada riwayat import.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($batches->hasPages())
                <div style="margin-top: 14px;">{{ $batches->links() }}</div>
            @endif
        </section>

        <p class="muted" style="text-align: center; margin-top: 16px;">
            <a href="{{ route('dashboard') }}">&larr; Kembali ke dashboard</a>
        </p>
    </div>
</body>
</html>