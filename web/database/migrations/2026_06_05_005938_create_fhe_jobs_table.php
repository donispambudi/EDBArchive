<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fhe_jobs', function (Blueprint $table) {
            $table->comment('Domain-level FHE operation records for EDBArchive. This table is separate from Laravel queue jobs.');

            $table->id()
                ->comment('Primary key of the FHE job record.');

            $table->string('job_type', 64)
                ->comment('Type of FHE operation, such as create-context, create-keypair, backup, restore, verify, or trace-leak.');

            $table->string('status', 32)
                ->default('pending')
                ->comment('Current job status: pending, running, completed, failed, or cancelled.');

            $table->longText('input_payload')
                ->nullable()
                ->comment('JSON payload containing structured input parameters for the FHE operation.');

            $table->longText('result_payload')
                ->nullable()
                ->comment('JSON payload containing structured output, metrics, generated file paths, warnings, or result details.');

            $table->unsignedTinyInteger('progress')
                ->default(0)
                ->comment('Job progress percentage from 0 to 100.');

            $table->integer('exit_code')
                ->nullable()
                ->comment('Exit code returned by the FHE executable. Zero means success; non-zero means failure.');

            $table->string('error_type', 64)
                ->nullable()
                ->comment('High-level error category, such as validation_error, file_io_error, database_error, fhe_error, or internal_error.');

            $table->string('error_code', 128)
                ->nullable()
                ->comment('Machine-readable error code returned by the FHE backend.');

            $table->text('error_message')
                ->nullable()
                ->comment('Human-readable error message suitable for UI display or debugging.');

            $table->longText('stdout_log')
                ->nullable()
                ->comment('Standard output captured from the FHE executable.');

            $table->longText('stderr_log')
                ->nullable()
                ->comment('Standard error captured from the FHE executable.');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who requested the FHE job. Null when created by the system.');

            $table->timestamp('claimed_at')
                ->nullable()
                ->comment('Timestamp when the worker claimed the job for execution.');

            $table->timestamp('started_at')
                ->nullable()
                ->comment('Timestamp when actual FHE execution started.');

            $table->timestamp('finished_at')
                ->nullable()
                ->comment('Timestamp when the job completed, failed, or was cancelled.');

            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_fhe_jobs_status_created');
            $table->index(['job_type', 'status'], 'idx_fhe_jobs_type_status');
        });

        DB::statement("
            ALTER TABLE fhe_jobs
            ADD CONSTRAINT chk_fhe_jobs_input_payload_json
            CHECK (input_payload IS NULL OR JSON_VALID(input_payload))
        ");

        DB::statement("
            ALTER TABLE fhe_jobs
            ADD CONSTRAINT chk_fhe_jobs_result_payload_json
            CHECK (result_payload IS NULL OR JSON_VALID(result_payload))
        ");

        DB::statement("
            ALTER TABLE fhe_jobs
            ADD CONSTRAINT chk_fhe_jobs_progress_range
            CHECK (progress BETWEEN 0 AND 100)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fhe_jobs');
    }
};
