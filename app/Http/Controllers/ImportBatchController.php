<?php

namespace App\Http\Controllers;

use App\Models\Faktur;
use App\Models\ImportBatch;
use App\Models\PartBahanItem;
use App\Models\Pkb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ImportBatchController extends Controller
{
    // Masih dipertahankan untuk akses langsung/lama, tapi dashboard sekarang
    // sudah nampilin riwayat lewat modal, jadi route ini gak dipakai dari UI utama lagi.
    public function index(): View
    {
        $batches = ImportBatch::query()->orderByDesc('created_at')->paginate(20);

        return view('import-riwayat', compact('batches'));
    }

    public function destroy(ImportBatch $batch): RedirectResponse
    {
        $label = $batch->labelJenis();
        $namaFile = $batch->nama_file ?: '-';

        DB::transaction(function () use ($batch) {
            match ($batch->jenis) {
                'pkb' => Pkb::where('import_batch_id', $batch->id)->delete(),
                'faktur' => Faktur::where('import_batch_id', $batch->id)->delete(),
                'comsumable', 'nota_pkb', 'billing_part', 'billing_service' =>
                    PartBahanItem::where('import_batch_id', $batch->id)->delete(),
                default => null,
            };

            $batch->delete();
        });

        return back()
            ->with('status', "Import \"{$label}\" ({$namaFile}) berhasil dihapus beserta datanya.")
            ->with('riwayat_terbuka', true);
    }
}