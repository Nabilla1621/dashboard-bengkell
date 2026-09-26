<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_bahan_items', function (Blueprint $table) {
            $table->unsignedInteger('no_urut')->nullable()->after('no_dokumen');
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('part_bahan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
            $table->dropColumn('no_urut');
        });
    }
};