<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('database_columns')
            ->where('default_encryption_type', 'fhe')
            ->update(['default_encryption_type' => 'fhe-secure']);
    }

    public function down(): void
    {
        DB::table('database_columns')
            ->whereIn('default_encryption_type', ['fhe-secure'])
            ->update(['default_encryption_type' => 'fhe']);
    }
};
