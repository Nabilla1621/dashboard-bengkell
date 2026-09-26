<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_bahan_items', function (Blueprint $table) {
            $table->id();

            $table->date('tanggal');
            $table->string('cabang');
            $table->string('no_pkb')->nullable()->index();   // relasi ke pkbs.no_pkb (nullable krn nota bahan comsumable bukan per-PKB)
            $table->string('no_dokumen')->nullable();          // NO.DOKUMEN / NO.Billing dari laporan asal

            // 'sparepart' atau 'bahan' — biar 1 tabel bisa nampung ke-4 sumber file
            $table->enum('kategori', ['sparepart', 'bahan']);

            // 'billing' (terjual ke customer) atau 'nota' (bahan/part keluar internal)
            $table->enum('sumber', ['billing', 'nota']);

            $table->string('kode_item')->nullable();
            $table->string('nama_item');
            $table->decimal('qty', 12, 2)->default(0);
            $table->string('satuan')->nullable();
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);       // nilai yang dipakai buat ranking "terlaris/terbanyak"

            $table->timestamps();

            $table->index(['tanggal', 'cabang', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_bahan_items');
    }
};