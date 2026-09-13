<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            if (! Schema::hasColumn('shares', 'bundle_ref')) {
                $table->string('bundle_ref')
                    ->nullable()
                    ->after('status')
                    ->comment('Relative storage reference of the generated share bundle.');
            }

            if (! Schema::hasColumn('shares', 'bundle_hash')) {
                $table->string('bundle_hash')
                    ->nullable()
                    ->after('bundle_ref')
                    ->comment('Cryptographic hash used to identify and verify the generated bundle.');
            }

            if (! Schema::hasColumn('shares', 'bundle_status')) {
                $table->string('bundle_status')
                    ->default('pending')
                    ->after('bundle_hash')
                    ->comment('Bundle generation status: pending, processing, or done.');
                $table->index('bundle_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            if (Schema::hasColumn('shares', 'bundle_status')) {
                $table->dropIndex(['bundle_status']);
                $table->dropColumn('bundle_status');
            }

            if (Schema::hasColumn('shares', 'bundle_ref')) {
                $table->dropColumn('bundle_ref');
            }

            if (Schema::hasColumn('shares', 'bundle_hash')) {
                $table->dropColumn('bundle_hash');
            }
        });
    }
};
