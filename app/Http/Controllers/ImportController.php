<?php

namespace App\Http\Controllers;

use App\Models\Faktur;
use App\Models\ImportBatch;
use App\Models\Pkb;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ImportController extends Controller
{
    public function importPkb(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx,html,htm', 'max:10240'],
            'cabang' => ['nullable', 'string'],
        ]);

        // SEMENTARA masih 1 cabang. Begitu form upload PKB sudah punya <select name="cabang">,
        // tinggal kirim field itu dari form — controller ini otomatis kepakai tanpa diubah lagi.
        $cabang = $request->input('cabang', 'Cabang Utama');

        $path = $request->file('file')->getRealPath();
        $html = file_get_contents($path);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);

        $rows = $dom->getElementsByTagName('tr');
        $header = [];
        $saved = 0;
        $dilewati = [];

        $batch = ImportBatch::create([
            'jenis' => 'pkb',
            'cabang' => $cabang,
            'nama_file' => $request->file('file')->getClientOriginalName(),
        ]);

        foreach ($rows as $row) {
            $ths = $row->getElementsByTagName('th');
            if ($ths->length > 0) {
                foreach ($ths as $th) {
                    $header[] = trim($th->textContent);
                }
                continue;
            }

            $tds = $row->getElementsByTagName('td');
            if ($tds->length === 0) {
                continue;
            }

            $values = [];
            foreach ($tds as $td) {
                $values[] = trim($td->textContent);
            }

            try {
                if (count($header) !== count($values)) {
                    throw new \RuntimeException('Jumlah kolom baris tidak sama dengan header.');
                }

                $data = array_combine($header, $values);

                if (empty($data['PKB No.']) || empty($data['PKB Date'])) {
                    throw new \RuntimeException('PKB No. atau PKB Date kosong.');
                }

                Pkb::updateOrCreate(
                    ['no_pkb' => $data['PKB No.']],
                    [
                        'tanggal' => Carbon::createFromFormat('d.m.Y', $data['PKB Date'])->format('Y-m-d'),
                        'type' => $data['Type'],
                        'status' => $data['PKB Status'],
                        'tipe_kendaraan' => $data['Tipe Kendaraan'],
                        'deskripsi_pekerjaan' => $data['Operation Description'],
                        'service_advisor' => $data['Service Advisor'],
                        'mekanik' => $data['Mechanic'],
                        'cabang' => $cabang,
                        'import_batch_id' => $batch->id,
                    ]
                );
                $saved++;
            } catch (Throwable $e) {
                $dilewati[] = 'Baris "' . ($values[1] ?? '?') . '": ' . $e->getMessage();
            }
        }

        $batch->update(['jumlah_baris' => $saved, 'jumlah_dilewati' => count($dilewati)]);

        return response()->json([
            'jumlah_baris_disimpan' => $saved,
            'jumlah_dilewati' => count($dilewati),
            'dilewati' => $dilewati,
            'import_batch_id' => $batch->id,
        ]);
    }

    public function importFaktur(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:10240'],
        ]);

        $path = $request->file('file')->getRealPath();
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        // Sanity check: pastikan kolom TOTAL jasa/sparepart/bahan masih di index yang
        // sama (17/31/43). Kalau template Crystal Report berubah, gagal jelas di sini
        // — jangan sampai kesimpen dengan angka yang salah tanpa ketahuan.
        $this->pastikanKolomTotalBenar($rows);

        $pkbAda = Pkb::pluck('no_pkb')->map(fn ($v) => trim($v))->flip();

        $batch = ImportBatch::create([
            'jenis' => 'faktur',
            'nama_file' => $request->file('file')->getClientOriginalName(),
        ]);

        $saved = 0;
        $dilewati = [];

        foreach ($rows as $r) {
            if (!is_numeric($r[0] ?? null) || empty($r[1]) || empty($r[2]) || empty($r[3])) {
                continue;
            }

            $noFaktur = trim($r[2]);
            $noPkb    = trim($r[3]);

            if (!isset($pkbAda[$noPkb])) {
                $dilewati[] = $noFaktur . ' (PKB ' . $noPkb . ' tidak ditemukan)';
                continue;
            }

            try {
                $tanggal = is_numeric($r[1])
                    ? ExcelDate::excelToDateTimeObject($r[1])->format('Y-m-d')
                    : Carbon::parse($r[1])->format('Y-m-d');

                Faktur::updateOrCreate(
                    ['no_faktur' => $noFaktur],
                    [
                        'no_pkb'    => $noPkb,
                        'tanggal'   => $tanggal,
                        'jasa'      => (float) $r[17],
                        'sparepart' => (float) $r[31],
                        'bahan'     => (float) $r[43],
                        'import_batch_id' => $batch->id,
                    ]
                );
                $saved++;
            } catch (Throwable $e) {
                $dilewati[] = $noFaktur . ': ' . $e->getMessage();
            }
        }

        $batch->update(['jumlah_baris' => $saved, 'jumlah_dilewati' => count($dilewati)]);

        return response()->json([
            'jumlah_faktur_disimpan' => $saved,
            'jumlah_dilewati'        => count($dilewati),
            'dilewati'               => $dilewati,
            'import_batch_id'        => $batch->id,
        ]);
    }

    private function pastikanKolomTotalBenar(array $rows): void
    {
        foreach ($rows as $r) {
            if (($r[17] ?? null) === 'TOTAL' && ($r[31] ?? null) === 'TOTAL' && ($r[43] ?? null) === 'TOTAL') {
                return;
            }
        }

        throw new \RuntimeException(
            'Struktur kolom TOTAL (jasa/sparepart/bahan) di file tidak sesuai yang diharapkan (index 17/31/43). '
            . 'Kemungkinan template laporan RptServicePenjualan berubah — cek manual sebelum lanjut import.'
        );
    }
}