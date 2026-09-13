<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $afterEncryptionColumn = Schema::hasColumn('share_items', 'encryption')
            ? 'encryption'
            : 'protection_type';

        Schema::table('share_items', function (Blueprint $table) use ($afterEncryptionColumn) {
            if (! Schema::hasColumn('share_items', 'fhe_context_id')) {
                $table->foreignId('fhe_context_id')
                    ->nullable()
                    ->after($afterEncryptionColumn)
                    ->comment('FHE context selected for this shared column.')
                    ->constrained('fhe_contexts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('share_items', 'key_registry_id')) {
                $table->foreignId('key_registry_id')
                    ->nullable()
                    ->after('fhe_context_id')
                    ->comment('Recipient keyset selected for this shared column.')
                    ->constrained('fhe_key_registry')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('share_items', function (Blueprint $table) {
            if (Schema::hasColumn('share_items', 'key_registry_id')) {
                $table->dropConstrainedForeignId('key_registry_id');
            }

            if (Schema::hasColumn('share_items', 'fhe_context_id')) {
                $table->dropConstrainedForeignId('fhe_context_id');
            }
        });
    }
};
