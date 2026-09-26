<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fakturs', function (Blueprint $table) {
            $table->id();
            $table->string('no_faktur')->unique();
            $table->string('no_pkb');
            $table->date('tanggal');
            $table->decimal('jasa', 15, 2)->default(0);
            $table->decimal('sparepart', 15, 2)->default(0);
            $table->decimal('bahan', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('no_pkb')->references('no_pkb')->on('pkbs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fakturs');
    }
};