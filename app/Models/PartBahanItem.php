<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartBahanItem extends Model
{
    protected $fillable = [
        'tanggal',
        'cabang',
        'no_pkb',
        'no_dokumen',
        'no_urut',
        'kategori',
        'sumber',
        'kode_item',
        'nama_item',
        'qty',
        'satuan',
        'harga_satuan',
        'total',
        'import_batch_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'qty' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relasi ke PKB (kalau no_pkb ada isinya)
    public function pkb()
    {
        return $this->belongsTo(Pkb::class, 'no_pkb', 'no_pkb');
    }
}