<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkbs', function (Blueprint $table) {
            $table->id();
            $table->string('no_pkb')->unique();
            $table->date('tanggal');
            $table->string('type');
            $table->string('status');
            $table->string('tipe_kendaraan')->nullable();
            $table->text('deskripsi_pekerjaan')->nullable();
            $table->string('service_advisor')->nullable();
            $table->string('mekanik')->nullable();
            $table->string('cabang')->default('Makassar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkbs');
    }
};