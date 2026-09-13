<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('share_items', 'protection_type')) {
            return;
        }

        DB::table('share_items')
            ->where('protection_type', '!=', 'fhe')
            ->update(['protection_type' => 'none']);
    }

    public function down(): void
    {
        //
    }
};
