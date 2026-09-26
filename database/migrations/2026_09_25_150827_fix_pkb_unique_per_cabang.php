<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->foreignKeyExists('fakturs', 'fakturs_no_pkb_foreign')) {
            Schema::table('fakturs', function (Blueprint $table) {
                $table->dropForeign('fakturs_no_pkb_foreign');
            });
        }

        if ($this->indexExists('pkbs', 'pkbs_no_pkb_unique')) {
            Schema::table('pkbs', function (Blueprint $table) {
                $table->dropUnique(['no_pkb']);
            });
        }

        if (! $this->indexExists('pkbs', 'pkbs_no_pkb_cabang_unique')) {
            Schema::table('pkbs', function (Blueprint $table) {
                $table->unique(['no_pkb', 'cabang']);
            });
        }

        if (! $this->indexExists('pkbs', 'pkbs_no_pkb_index')) {
            Schema::table('pkbs', function (Blueprint $table) {
                $table->index('no_pkb');
            });
        }

        if (! Schema::hasColumn('fakturs', 'cabang')) {
            Schema::table('fakturs', function (Blueprint $table) {
                $table->string('cabang')->nullable()->after('no_pkb');
            });
        }

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

        if ($this->indexExists('pkbs', 'pkbs_no_pkb_index')) {
            Schema::table('pkbs', function (Blueprint $table) {
                $table->dropIndex(['no_pkb']);
            });
        }

        if ($this->indexExists('pkbs', 'pkbs_no_pkb_cabang_unique')) {
            Schema::table('pkbs', function (Blueprint $table) {
                $table->dropUnique(['no_pkb', 'cabang']);
            });
        }

        if (! $this->indexExists('pkbs', 'pkbs_no_pkb_unique')) {
            Schema::table('pkbs', function (Blueprint $table) {
                $table->unique(['no_pkb']);
            });
        }
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
