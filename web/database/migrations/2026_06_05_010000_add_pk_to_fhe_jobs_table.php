<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fhe_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('pk')
                ->nullable()
                ->after('job_type')
                ->comment('Primary key of the domain record targeted by this job, interpreted according to job_type.');

            $table->index(['job_type', 'pk', 'status'], 'idx_fhe_jobs_type_pk_status');
        });

        DB::statement("
            UPDATE fhe_jobs
            SET pk = CAST(JSON_UNQUOTE(JSON_EXTRACT(input_payload, '$.fhe_context_id')) AS UNSIGNED)
            WHERE job_type = 'create-context'
              AND input_payload IS NOT NULL
              AND JSON_EXTRACT(input_payload, '$.fhe_context_id') IS NOT NULL
        ");

        DB::statement("
            UPDATE fhe_jobs
            SET pk = CAST(JSON_UNQUOTE(JSON_EXTRACT(input_payload, '$.key_registry_id')) AS UNSIGNED)
            WHERE job_type = 'create-keypair'
              AND input_payload IS NOT NULL
              AND JSON_EXTRACT(input_payload, '$.key_registry_id') IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('fhe_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_fhe_jobs_type_pk_status');
            $table->dropColumn('pk');
        });
    }
};
