<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faktur extends Model
{
    protected $fillable = [
        'no_faktur', 'no_pkb', 'cabang', 'tanggal',
        'jasa', 'sparepart', 'bahan',
        'import_batch_id',
    ];

    public function pkb()
    {
        return $this->belongsTo(Pkb::class, 'no_pkb', 'no_pkb');
    }
}