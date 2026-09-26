<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pkb extends Model
{
    protected $fillable = [
        'no_pkb', 'tanggal', 'type', 'status',
        'tipe_kendaraan', 'deskripsi_pekerjaan',
        'service_advisor', 'mekanik', 'cabang',
        'import_batch_id',
    ];

    public function faktur()
    {
        return $this->hasMany(Faktur::class, 'no_pkb', 'no_pkb')
            ->whereColumn('fakturs.cabang', 'pkbs.cabang');
    }

    public function partBahanItems()
    {
        return $this->hasMany(PartBahanItem::class, 'no_pkb', 'no_pkb');
    }
}