<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fhe_contexts', function (Blueprint $table) {
            $table->string('context_status', 32)
                ->default('pending')
                ->after('context_file_ref')
                ->comment('Generation workflow status: pending, queued, processing, generated, or failed.');

            $table->index('context_status', 'idx_fhe_contexts_context_status');
        });

        Schema::table('fhe_key_registry', function (Blueprint $table) {
            $table->string('generation_status', 32)
                ->default('pending')
                ->after('keyset_ref')
                ->comment('Generation workflow status: pending, queued, processing, generated, or failed.');

            $table->index('generation_status', 'idx_fhe_key_registry_generation_status');
        });

        DB::table('fhe_contexts')
            ->whereNotNull('context_file_ref')
            ->where('context_file_ref', '<>', '')
            ->update(['context_status' => 'generated']);

        DB::table('fhe_key_registry')
            ->whereNotNull('keyset_ref')
            ->where('keyset_ref', '<>', '')
            ->update(['generation_status' => 'generated']);
    }

    public function down(): void
    {
        Schema::table('fhe_key_registry', function (Blueprint $table) {
            $table->dropIndex('idx_fhe_key_registry_generation_status');
            $table->dropColumn('generation_status');
        });

        Schema::table('fhe_contexts', function (Blueprint $table) {
            $table->dropIndex('idx_fhe_contexts_context_status');
            $table->dropColumn('context_status');
        });
    }
};
