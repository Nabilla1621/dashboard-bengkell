<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fakturs', function (Blueprint $table) {
            $table->dropUnique('fakturs_no_faktur_unique');
            $table->unique(['no_faktur', 'cabang']);
        });
    }

    public function down(): void
    {
        Schema::table('fakturs', function (Blueprint $table) {
            $table->dropUnique(['no_faktur', 'cabang']);
            $table->unique('no_faktur');
        });
    }
};