<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fhe_contexts', 'scheme_id')) {
            return;
        }

        $schemes = DB::table('schemes')->pluck('id', 'scheme_name');

        foreach ($schemes as $schemeName => $schemeId) {
            DB::table('fhe_contexts')
                ->whereNull('scheme_id')
                ->where('scheme', $schemeName)
                ->update(['scheme_id' => $schemeId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fhe_contexts', 'scheme_id')) {
            return;
        }

        DB::table('fhe_contexts')->update(['scheme_id' => null]);
    }
};
