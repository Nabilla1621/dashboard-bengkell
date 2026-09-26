<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Lepas FK di fakturs KALAU ADA (di database ini ternyata belum pernah kebuat)
        if ($this->foreignKeyExists('fakturs', 'fakturs_no_pkb_foreign')) {
            Schema::table('fakturs', function (Blueprint $table) {
                $table->dropForeign('fakturs_no_pkb_foreign');
            });
        }

        // 2. Drop unique lama KALAU ADA, ganti jadi unique gabungan (no_pkb, cabang)
        //    plus index biasa di no_pkb sendiri, supaya FK bisa nempel ke situ.
        Schema::table('pkbs', function (Blueprint $table) {
            if ($this->indexExists('pkbs', 'pkbs_no_pkb_unique')) {
                $table->dropUnique(['no_pkb']);
            }
            $table->unique(['no_pkb', 'cabang']);
            $table->index('no_pkb');
        });

        // 3. Tambah kolom cabang di fakturs (kalau belum ada)
        if (! Schema::hasColumn('fakturs', 'cabang')) {
            Schema::table('fakturs', function (Blueprint $table) {
                $table->string('cabang')->nullable()->after('no_pkb');
            });
        }

        // 4. Pasang FK-nya, menunjuk ke index biasa no_pkb yang baru dibuat
        if (! $this->foreignKeyExists('fakturs', 'fakturs_no_pkb_foreign')) {
            Schema::table('fakturs', function (Blueprint $table) {
                $table->foreign('no_pkb')->references('no_pkb')->on('pkbs');
            });
        }
    }

    public function down(): void
    {
        if ($this->foreignKeyExists('fakturs', 'fakturs_no_pkb_foreign')) {
            Schema::table('fakturs', function (Blueprint $table) {
                $table->dropForeign(['no_pkb']);
            });
        }

        if (Schema::hasColumn('fakturs', 'cabang')) {
            Schema::table('fakturs', function (Blueprint $table) {
                $table->dropColumn('cabang');
            });
        }

        Schema::table('pkbs', function (Blueprint $table) {
            if ($this->indexExists('pkbs', 'pkbs_no_pkb_index')) {
                $table->dropIndex(['no_pkb']);
            }
            if ($this->indexExists('pkbs', 'pkbs_no_pkb_cabang_unique')) {
                $table->dropUnique(['no_pkb', 'cabang']);
            }
            $table->unique(['no_pkb']);
        });
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $result = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $constraintName]);

        return count($result) > 0;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ", [$table, $indexName]);

        return count($result) > 0;
    }
};
