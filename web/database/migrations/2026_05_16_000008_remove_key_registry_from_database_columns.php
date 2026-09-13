<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('database_columns', function (Blueprint $table) {
            if (Schema::hasColumn('database_columns', 'key_registry_id')) {
                $table->dropConstrainedForeignId('key_registry_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('database_columns', function (Blueprint $table) {
            if (! Schema::hasColumn('database_columns', 'key_registry_id')) {
                $table->foreignId('key_registry_id')
                    ->nullable()
                    ->after('fhe_context_id')
                    ->comment('Legacy default keyset assigned to the configured database column.')
                    ->constrained('fhe_key_registry')
                    ->nullOnDelete();
            }
        });
    }
};
