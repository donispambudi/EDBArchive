<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('database_columns')
            ->where('default_encryption_type', '!=', 'fhe')
            ->update(['default_encryption_type' => 'plaintext']);

        Schema::table('database_columns', function (Blueprint $table) {
            if (Schema::hasColumn('database_columns', 'default_protection_type')) {
                $table->dropIndex(['default_protection_type']);
                $table->dropColumn('default_protection_type');
            }

            if (Schema::hasColumn('database_columns', 'default_export_mode')) {
                $table->dropIndex(['default_export_mode']);
                $table->dropColumn('default_export_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('database_columns', function (Blueprint $table) {
            if (! Schema::hasColumn('database_columns', 'default_protection_type')) {
                $table->string('default_protection_type')
                    ->default('plaintext')
                    ->after('column_name')
                    ->comment('Legacy default protection type for the database column.');
                $table->index('default_protection_type');
            }

            if (! Schema::hasColumn('database_columns', 'default_export_mode')) {
                $table->string('default_export_mode')
                    ->default('plaintext')
                    ->after('default_protection_type')
                    ->comment('Legacy default export mode for the database column.');
                $table->index('default_export_mode');
            }
        });
    }
};
