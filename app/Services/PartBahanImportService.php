<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\PartBahanItem;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PartBahanImportService
{
    /**
     * $files = ['comsumable' => '/path/...', 'nota_pkb' => '/path/...', ...]
     * $originalNames = ['comsumable' => 'RptNotaBahan_comsumable_....xls', ...] (opsional, buat riwayat import)
     */
    public function importFromUploads(array $files, string $cabang, array $originalNames = []): array
    {
        $hasil = [];

        foreach ($files as $jenis => $path) {
            $batch = ImportBatch::create([
                'jenis' => $jenis,
                'cabang' => $cabang,
                'nama_file' => $originalNames[$jenis] ?? basename($path),
            ]);

            $jumlah = match ($jenis) {
                'comsumable', 'nota_pkb' => $this->importNotaBahan($path, $cabang, $batch->id),
                'billing_part' => $this->parseBilling($path, $cabang, $batch->id, hasReferensi: false),
                'billing_service' => $this->parseBilling($path, $cabang, $batch->id, hasReferensi: true),
                default => 0,
            };

            $batch->update(['jumlah_baris' => $jumlah]);
            $hasil[$jenis] = $jumlah;
        }

        return $hasil;
    }

    protected function importNotaBahan(string $path, string $cabang, ?int $batchId): int
    {
        $rows = $this->readRows($path);
        ksort($rows);

        $currentTanggal = null;
        $currentNoDokumen = null;
        $count = 0;

        foreach ($rows as $cells) {
            if (isset($cells[1], $cells[5]) && $cells[1] instanceof \DateTimeInterface) {
                $currentTanggal = $cells[1];
                $currentNoDokumen = (string) $cells[5];
                continue;
            }

            if (isset($cells[9]) && is_string($cells[9]) && str_starts_with($cells[9], '** JUMLAH')) {
                continue;
            }

            if (isset($cells[1], $cells[3], $cells[6]) && is_numeric($cells[1]) && $currentNoDokumen) {
                PartBahanItem::updateOrCreate(
                    [
                        'cabang' => $cabang,
                        'no_dokumen' => $currentNoDokumen,
                        'no_urut' => (int) $cells[1],
                        'kode_item' => (string) $cells[3],
                        'kategori' => 'bahan',
                        'sumber' => 'nota',
                    ],
                    [
                        'tanggal' => $currentTanggal,
                        'no_pkb' => null,
                        'nama_item' => trim((string) $cells[6]),
                        'qty' => (float) ($cells[9] ?? 0),
                        'satuan' => $cells[11] ?? null,
                        'harga_satuan' => (float) ($cells[16] ?? 0),
                        'total' => (float) ($cells[23] ?? $cells[19] ?? 0),
                        'import_batch_id' => $batchId,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    protected function parseBilling(string $path, string $cabang, ?int $batchId, bool $hasReferensi): int
    {
        $rows = $this->readRows($path);
        ksort($rows);

        $currentNoBilling = null;
        $currentNoPkb = null;
        $currentTanggal = null;
        $count = 0;

        foreach ($rows as $cells) {
            if (isset($cells[1], $cells[3]) && is_string($cells[1]) && ! is_numeric($cells[1])) {
                $currentNoBilling = $cells[1];
                $currentNoPkb = $hasReferensi ? ($cells[7] ?? null) : null;
                $currentTanggal = $this->parseTanggalTitik($cells[3]);
                continue;
            }

            if (isset($cells[1], $cells[2], $cells[3]) && is_numeric($cells[1]) && $currentNoBilling) {
                PartBahanItem::updateOrCreate(
                    [
                        'cabang' => $cabang,
                        'no_dokumen' => $currentNoBilling,
                        'no_urut' => (int) $cells[1],
                        'kode_item' => (string) $cells[2],
                        'kategori' => 'sparepart',
                        'sumber' => 'billing',
                    ],
                    [
                        'tanggal' => $currentTanggal,
                        'no_pkb' => $currentNoPkb,
                        'nama_item' => trim((string) $cells[3]),
                        'qty' => (float) ($cells[4] ?? 0),
                        'satuan' => null,
                        'harga_satuan' => (float) ($cells[10] ?? 0),
                        'total' => (float) ($cells[20] ?? 0),
                        'import_batch_id' => $batchId,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    protected function readRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex = $row->getRowIndex() - 1;
            $cells = [];

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);

            foreach ($cellIterator as $cell) {
                $value = $cell->getValue();
                if ($value === null || $value === '') {
                    continue;
                }

                if (ExcelDate::isDateTime($cell)) {
                    $value = ExcelDate::excelToDateTimeObject($value);
                }

                $colIndex = Coordinate::columnIndexFromString($cell->getColumn()) - 1;
                $cells[$colIndex] = $value;
            }

            if (! empty($cells)) {
                $rows[$rowIndex] = $cells;
            }
        }

        return $rows;
    }

    protected function parseTanggalTitik(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d.m.Y', trim($value));
        } catch (\Throwable $e) {
            return null;
        }
    }
}