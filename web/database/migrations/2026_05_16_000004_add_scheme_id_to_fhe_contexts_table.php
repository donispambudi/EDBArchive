<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fhe_contexts', 'scheme_id')) {
            return;
        }

        Schema::table('fhe_contexts', function (Blueprint $table) {
            $table->foreignId('scheme_id')
                ->nullable()
                ->after('name')
                ->comment('FHE scheme used by this context.')
                ->constrained('schemes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fhe_contexts', 'scheme_id')) {
            return;
        }

        Schema::table('fhe_contexts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheme_id');
        });
    }
};
