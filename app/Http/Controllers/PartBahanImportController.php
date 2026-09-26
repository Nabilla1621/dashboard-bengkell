<?php

namespace App\Http\Controllers;

use App\Models\Faktur;
use App\Models\ImportBatch;
use App\Models\Pkb;
use App\Services\PartBahanImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class PartBahanImportController extends Controller
{
    public function form(): View
    {
        $cabangList = ['Kharisma Sentosa Makassar', 'Kharisma Sentosa Kendari', 'Kharisma Sentosa Ambon'];

        return view('import-part-bahan', compact('cabangList'));
    }

    public function store(Request $request, PartBahanImportService $service): RedirectResponse
    {
        if ($request->hasFile('maintain_pkb_file')) {
            $file = $request->file('maintain_pkb_file');
    
            if ($file->getError() !== UPLOAD_ERR_OK) {
                dd([
                    'error_code' => $file->getError(),
                    'error_message' => $file->getErrorMessage(),
                    'size' => $file->getSize(),
                    'name' => $file->getClientOriginalName(),
                ]);
            }
        }
    
        $data = $request->validate([
        $data = $request->validate([
            'cabang' => ['required', 'string'],
            'maintain_pkb_file' => ['nullable', 'file', 'mimes:xls,xlsx,html,htm', 'max:10240'],
            'service_penjualan_file' => ['nullable', 'file', 'mimes:xls,xlsx', 'max:10240'],
            'comsumable' => ['nullable', 'file', 'mimes:xls,xlsx', 'max:10240'],
            'nota_pkb' => ['nullable', 'file', 'mimes:xls,xlsx', 'max:10240'],
            'billing_part' => ['nullable', 'file', 'mimes:xls,xlsx', 'max:10240'],
            'billing_service' => ['nullable', 'file', 'mimes:xls,xlsx', 'max:10240'],
        ]);

        $semuaKeyFile = ['maintain_pkb_file', 'service_penjualan_file', 'comsumable', 'nota_pkb', 'billing_part', 'billing_service'];
        $adaFileDiupload = collect($semuaKeyFile)->contains(fn ($key) => $request->hasFile($key));

        if (! $adaFileDiupload) {
            return back()->withErrors(['file' => 'Pilih minimal 1 file untuk diimport.']);
        }

        $cabang = $data['cabang'];
        $ringkasan = [];

        try {
            // Urutan wajib: Maintain PKB dulu baru Service Penjualan,
            // karena faktur di-match ke no_pkb yang harus udah tersimpan.
            if ($request->hasFile('maintain_pkb_file')) {
                $hasil = $this->prosesMaintainPkb($request->file('maintain_pkb_file'), $cabang);
                $ringkasan[] = "Maintain PKB: {$hasil['saved']} baris" . ($hasil['dilewati'] > 0 ? ", {$hasil['dilewati']} dilewati" : '');
            }

            if ($request->hasFile('service_penjualan_file')) {
                $hasil = $this->prosesServicePenjualan($request->file('service_penjualan_file'), $cabang);
                $ringkasan[] = "Revenue: {$hasil['saved']} faktur" . ($hasil['dilewati'] > 0 ? ", {$hasil['dilewati']} dilewati" : '');
            }

            $keyPartBahan = ['comsumable', 'nota_pkb', 'billing_part', 'billing_service'];
            if (collect($keyPartBahan)->contains(fn ($key) => $request->hasFile($key))) {
                $paths = [];
                $originalNames = [];
                foreach ($keyPartBahan as $key) {
                    if ($request->hasFile($key)) {
                        $file = $request->file($key);
                        $paths[$key] = $file->store('imports/part-bahan');
                        $originalNames[$key] = $file->getClientOriginalName();
                    }
                }

                try {
                    $hasilPartBahan = $service->importFromUploads(
                        array_map(fn ($relativePath) => Storage::path($relativePath), $paths),
                        $cabang,
                        $originalNames
                    );
                    foreach ($hasilPartBahan as $jenis => $jumlah) {
                        $ringkasan[] = "{$jenis}: {$jumlah} item";
                    }
                } finally {
                    foreach ($paths as $relativePath) {
                        Storage::delete($relativePath);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error('Import gagal', ['cabang' => $cabang, 'error' => $e->getMessage()]);

            return back()->withErrors([
                'file' => 'Import gagal diproses: ' . $e->getMessage() . '. Pastikan format file sesuai template yang diminta.',
            ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Import selesai — ' . implode(', ', $ringkasan) . '. Salah upload? Cek halaman Riwayat Import untuk menghapusnya.');
    }

    private function prosesMaintainPkb(UploadedFile $file, string $cabang): array
    {
        $html = file_get_contents($file->getRealPath());

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);

        $rows = $dom->getElementsByTagName('tr');
        $header = [];
        $saved = 0;
        $dilewati = 0;

        $batch = ImportBatch::create([
            'jenis' => 'pkb',
            'cabang' => $cabang,
            'nama_file' => $file->getClientOriginalName(),
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
                    ['no_pkb' => $data['PKB No.'], 'cabang' => $cabang],
                    [
                        'tanggal' => Carbon::createFromFormat('d.m.Y', $data['PKB Date'])->format('Y-m-d'),
                        'type' => $data['Type'],
                        'status' => $data['PKB Status'],
                        'tipe_kendaraan' => $data['Tipe Kendaraan'],
                        'deskripsi_pekerjaan' => $data['Operation Description'],
                        'service_advisor' => $data['Service Advisor'],
                        'mekanik' => $data['Mechanic'],
                        'import_batch_id' => $batch->id,
                    ]
                );
                $saved++;
            } catch (Throwable $e) {
                $dilewati++;
            }
        }

        $batch->update(['jumlah_baris' => $saved, 'jumlah_dilewati' => $dilewati]);

        return ['saved' => $saved, 'dilewati' => $dilewati];
    }

    private function prosesServicePenjualan(UploadedFile $file, string $cabang): array
    {
        $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        $this->pastikanKolomTotalBenar($rows);

        $pkbAda = Pkb::where('cabang', $cabang)->pluck('no_pkb')->map(fn ($v) => trim($v))->flip();

        $batch = ImportBatch::create([
            'jenis' => 'faktur',
            'cabang' => $cabang,
            'nama_file' => $file->getClientOriginalName(),
        ]);

        $saved = 0;
        $dilewati = 0;

        foreach ($rows as $r) {
            if (!is_numeric($r[0] ?? null) || empty($r[1]) || empty($r[2]) || empty($r[3])) {
                continue;
            }

            $noFaktur = trim($r[2]);
            $noPkb    = trim($r[3]);

            if (!isset($pkbAda[$noPkb])) {
                $dilewati++;
                continue;
            }

            try {
                $tanggal = is_numeric($r[1])
                    ? ExcelDate::excelToDateTimeObject($r[1])->format('Y-m-d')
                    : Carbon::parse($r[1])->format('Y-m-d');

                Faktur::updateOrCreate(
                    ['no_faktur' => $noFaktur, 'cabang' => $cabang],
                    [
                        'no_pkb'    => $noPkb,
                        'cabang'    => $cabang,
                        'tanggal'   => $tanggal,
                        'jasa'      => (float) $r[17],
                        'sparepart' => (float) $r[31],
                        'bahan'     => (float) $r[43],
                        'import_batch_id' => $batch->id,
                    ]
                );
                $saved++;
            } catch (Throwable $e) {
                $dilewati++;
            }
        }

        $batch->update(['jumlah_baris' => $saved, 'jumlah_dilewati' => $dilewati]);

        return ['saved' => $saved, 'dilewati' => $dilewati];
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
