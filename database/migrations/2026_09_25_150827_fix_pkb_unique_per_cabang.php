<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Lepas dulu FK di fakturs yang mengunci index unique pkbs.no_pkb
        Schema::table('fakturs', function (Blueprint $table) {
            $table->dropForeign('fakturs_no_pkb_foreign');
        });

        // 2. Sekarang aman drop unique lama, ganti jadi unique gabungan (no_pkb, cabang)
        //    plus index biasa di no_pkb sendiri, supaya FK masih bisa nempel ke situ.
        Schema::table('pkbs', function (Blueprint $table) {
            $table->dropUnique(['no_pkb']);
            $table->unique(['no_pkb', 'cabang']);
            $table->index('no_pkb');
        });

        // 3. Tambah kolom cabang di fakturs
        Schema::table('fakturs', function (Blueprint $table) {
            $table->string('cabang')->nullable()->after('no_pkb');
        });

        // 4. Pasang lagi FK-nya, sekarang menunjuk ke index biasa no_pkb yang baru dibuat
        Schema::table('fakturs', function (Blueprint $table) {
            $table->foreign('no_pkb')->references('no_pkb')->on('pkbs');
        });
    }

    public function down(): void
    {
        Schema::table('fakturs', function (Blueprint $table) {
            $table->dropForeign(['no_pkb']);
            $table->dropColumn('cabang');
        });

        Schema::table('pkbs', function (Blueprint $table) {
            $table->dropIndex(['no_pkb']);
            $table->dropUnique(['no_pkb', 'cabang']);
            $table->unique(['no_pkb']);
        });

        Schema::table('fakturs', function (Blueprint $table) {
            $table->foreign('no_pkb')->references('no_pkb')->on('pkbs');
        });
    }
};