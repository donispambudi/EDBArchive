<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schemes', 'script_handler')) {
            return;
        }

        Schema::table('schemes', function (Blueprint $table) {
            $table->dropColumn('script_handler');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('schemes', 'script_handler')) {
            return;
        }

        Schema::table('schemes', function (Blueprint $table) {
            $table->string('script_handler')
                ->nullable()
                ->after('scheme_name')
                ->comment('Legacy executable or script handler assigned to the scheme.');
        });
    }
};
