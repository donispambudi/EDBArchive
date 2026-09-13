<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_items', function (Blueprint $table) {
            if (! Schema::hasColumn('share_items', 'encryption')) {
                $table->string('encryption')
                    ->default('plaintext')
                    ->after('database_column_id')
                    ->comment('Encryption applied to this shared column.');
                $table->index('encryption');
            }
        });

        if (Schema::hasColumn('share_items', 'protection_type')) {
            DB::table('share_items')
                ->update([
                    'encryption' => DB::raw("case when protection_type = 'fhe' then 'fhe-secure' else 'plaintext' end"),
                ]);

            Schema::table('share_items', function (Blueprint $table) {
                $table->dropIndex(['protection_type']);
                $table->dropColumn('protection_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('share_items', function (Blueprint $table) {
            if (! Schema::hasColumn('share_items', 'protection_type')) {
                $table->string('protection_type')
                    ->default('none')
                    ->after('database_column_id')
                    ->comment('Legacy protection type applied to the share item.');
                $table->index('protection_type');
            }
        });

        if (Schema::hasColumn('share_items', 'encryption')) {
            DB::table('share_items')
                ->update([
                    'protection_type' => DB::raw("case when encryption = 'plaintext' then 'none' else 'fhe' end"),
                ]);

            Schema::table('share_items', function (Blueprint $table) {
                $table->dropIndex(['encryption']);
                $table->dropColumn('encryption');
            });
        }
    }
};
