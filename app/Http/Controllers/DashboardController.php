<?php

namespace App\Http\Controllers;

use App\Models\Faktur;
use App\Models\ImportBatch;
use App\Models\PartBahanItem;
use App\Models\Pkb;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $cabangList = collect([
            'Kharisma Sentosa Makassar',
            'Kharisma Sentosa Kendari',
            'Kharisma Sentosa Ambon',
        ]);

        $cabang = $request->query('cabang') ?: null;
        $periode = in_array($request->query('periode'), ['harian', 'bulanan', 'tahunan'], true)
            ? $request->query('periode')
            : 'bulanan';

        $tanggal = $request->query('tanggal') ?: (Pkb::max('tanggal') ?: now()->toDateString());
        $t = Carbon::parse($tanggal);

        [$dari, $sampai, $labelPeriode] = match ($periode) {
            'harian'  => [
                $t->toDateString(),
                $t->toDateString(),
                'tanggal ' . $t->copy()->locale('id')->translatedFormat('j F Y'),
            ],
            'tahunan' => [
                $t->copy()->startOfYear()->toDateString(),
                $t->copy()->endOfYear()->toDateString(),
                'tahun ' . $t->year,
            ],
            default   => [
                $t->copy()->startOfMonth()->toDateString(),
                $t->copy()->endOfMonth()->toDateString(),
                'bulan ' . $t->copy()->locale('id')->translatedFormat('F Y'),
            ],
        };

        // Unit Entry: jumlah PKB (tabel pkbs)
        $pkbQuery = Pkb::query()->whereBetween('tanggal', [$dari, $sampai]);
        if ($cabang) {
            $pkbQuery->where('cabang', $cabang);
        }
        $unitEntry = (clone $pkbQuery)->count();
        $countBooking = (clone $pkbQuery)->where('type', 'Booking')->count();
        $countWalkIn = (clone $pkbQuery)->where('type', 'Walk In')->count();

        // Revenue: Jasa + Sparepart + Bahan (tabel fakturs), di-join ke pkbs
        // pakai no_pkb DAN cabang, karena no_pkb bisa collision antar cabang.
        $fakturQuery = Faktur::query()
            ->join('pkbs', function ($join) {
                $join->on('pkbs.no_pkb', '=', 'fakturs.no_pkb')
                     ->on('pkbs.cabang', '=', 'fakturs.cabang');
            })
            ->whereBetween('fakturs.tanggal', [$dari, $sampai]);
        if ($cabang) {
            $fakturQuery->where('fakturs.cabang', $cabang);
        }
        $rev = (clone $fakturQuery)
            ->selectRaw('COALESCE(SUM(fakturs.jasa), 0) as jasa,
                         COALESCE(SUM(fakturs.sparepart), 0) as sparepart,
                         COALESCE(SUM(fakturs.bahan), 0) as bahan')
            ->first();
        $totalRevenue = (float) $rev->jasa + (float) $rev->sparepart + (float) $rev->bahan;

        // Unit Entry per tipe kendaraan
        $perTipe = (clone $pkbQuery)
            ->selectRaw('tipe_kendaraan as label, COUNT(*) as jumlah')
            ->groupBy('tipe_kendaraan')->orderByDesc('jumlah')->get();

        // Unit Entry per Jenis Service, dikelompokkan dari deskripsi_pekerjaan
        $perJenisService = (clone $pkbQuery)
            ->pluck('deskripsi_pekerjaan')
            ->map(fn ($d) => $this->jenisService((string) $d))
            ->countBy()
            ->sortDesc()
            ->map(fn ($jumlah, $label) => (object) ['label' => $label, 'jumlah' => $jumlah])
            ->values();

        // Unit Entry per Booking/Walk-in: akumulasi bulanan (angka, bukan diagram)
        $bulanDari = $periode === 'tahunan'
            ? $t->copy()->startOfYear()->toDateString()
            : $t->copy()->startOfMonth()->toDateString();
        $bulanSampai = $periode === 'tahunan'
            ? $t->copy()->endOfYear()->toDateString()
            : $t->copy()->endOfMonth()->toDateString();

        $bulanQuery = Pkb::query()->whereBetween('tanggal', [$bulanDari, $bulanSampai])
            ->whereIn('type', ['Booking', 'Walk In']);
        if ($cabang) {
            $bulanQuery->where('cabang', $cabang);
        }

        $perBookingWalkin = $bulanQuery
            ->selectRaw("DATE_FORMAT(tanggal, '%Y-%m') as bulan, type, COUNT(*) as jumlah")
            ->groupBy('bulan', 'type')->orderBy('bulan')->get()
            ->map(function ($row) use ($periode) {
                $labelBulan = Carbon::createFromFormat('Y-m', $row->bulan)->locale('id')->translatedFormat('M Y');
                return (object) [
                    'label'  => $periode === 'tahunan' ? "{$row->type} - {$labelBulan}" : $row->type,
                    'jumlah' => $row->jumlah,
                ];
            })
            ->sortByDesc('jumlah')->values();

        // Part & Bahan Terpakai: top item dari 4 laporan detail (tabel part_bahan_items)
        $partBahanQuery = PartBahanItem::query()->whereBetween('tanggal', [$dari, $sampai]);
        if ($cabang) {
            $partBahanQuery->where('cabang', $cabang);
        }

        $topSparepart = (clone $partBahanQuery)
            ->where('kategori', 'sparepart')
            ->selectRaw('nama_item as label, SUM(qty) as qty, SUM(total) as jumlah')
            ->groupBy('nama_item')
            ->orderByDesc('jumlah')
            ->limit(8)
            ->get();

        $topBahan = (clone $partBahanQuery)
            ->where('kategori', 'bahan')
            ->selectRaw('nama_item as label, SUM(qty) as qty, SUM(total) as jumlah')
            ->groupBy('nama_item')
            ->orderByDesc('jumlah')
            ->limit(8)
            ->get();

        // Daftar Unit Entry beserta revenue dari fakturnya
        $entries = (clone $pkbQuery)
            ->withSum('faktur as total_jasa', 'jasa')
            ->withSum('faktur as total_sparepart', 'sparepart')
            ->withSum('faktur as total_bahan', 'bahan')
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Riwayat import — dipakai buat isi modal "Riwayat Import" di dashboard
        $importBatches = ImportBatch::query()->orderByDesc('created_at')->limit(30)->get();

        return view('dashboard', compact(
            'cabangList', 'cabang', 'periode', 'tanggal', 'labelPeriode',
            'unitEntry', 'countBooking', 'countWalkIn', 'rev', 'totalRevenue',
            'perTipe', 'perJenisService', 'perBookingWalkin', 'entries',
            'topSparepart', 'topBahan', 'importBatches'
        ));
    }

    private function jenisService(string $desc): string
    {
        $u = strtoupper($desc);

        if (str_contains($u, '1.000 KM')) {
            return 'Checking 1.000 KM';
        }
        if (preg_match('/CHECKING\s*(\d{2,3})\.000/', $u, $m)) {
            return 'Checking ' . $m[1] . '.000 KM';
        }
        if (str_contains($u, 'SERVICE KECIL')) {
            return 'Service Kecil';
        }
        if (str_contains($u, 'GANTI OLI')) {
            return 'Ganti Oli';
        }

        return 'Lainnya';
    }
}