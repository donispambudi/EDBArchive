<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('schemes', 'configuration_json')) {
            return;
        }

        Schema::table('schemes', function (Blueprint $table) {
            $table->json('configuration_json')
                ->nullable()
                ->after('scheme_name')
                ->comment('JSON definitions for parameters accepted by the scheme.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('schemes', 'configuration_json')) {
            return;
        }

        Schema::table('schemes', function (Blueprint $table) {
            $table->dropColumn('configuration_json');
        });
    }
};
