<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            if (! Schema::hasColumn('shares', 'bundle_hash')) {
                $table->string('bundle_hash')
                    ->nullable()
                    ->after('bundle_ref')
                    ->comment('Cryptographic hash used to identify and verify the generated bundle.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            if (Schema::hasColumn('shares', 'bundle_hash')) {
                $table->dropColumn('bundle_hash');
            }
        });
    }
};
