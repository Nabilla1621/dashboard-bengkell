<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = [
        'jenis', 'cabang', 'nama_file', 'jumlah_baris', 'jumlah_dilewati',
    ];

    public const JENIS_LABEL = [
        'pkb' => 'Unit Entry (Maintain PKB)',
        'faktur' => 'Revenue (Service Penjualan)',
        'comsumable' => 'Nota Bahan — Consumable',
        'nota_pkb' => 'Nota Bahan — PKB',
        'billing_part' => 'Penjualan Part — Billing Part',
        'billing_service' => 'Penjualan Part — Billing Service',
    ];

    public function labelJenis(): string
    {
        return self::JENIS_LABEL[$this->jenis] ?? $this->jenis;
    }
}