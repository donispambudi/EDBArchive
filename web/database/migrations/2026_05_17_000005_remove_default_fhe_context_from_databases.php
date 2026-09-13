<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            if (Schema::hasColumn('databases', 'default_fhe_context_id')) {
                $table->dropConstrainedForeignId('default_fhe_context_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            if (! Schema::hasColumn('databases', 'default_fhe_context_id')) {
                $table->foreignId('default_fhe_context_id')
                    ->nullable()
                    ->after('description')
                    ->comment('Legacy default FHE context assigned to the database.')
                    ->constrained('fhe_contexts')
                    ->nullOnDelete();
            }
        });
    }
};
