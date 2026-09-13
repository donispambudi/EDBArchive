<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id()->comment('Primary key of the Laravel queue job.');
            $table->string('queue')->index()->comment('Queue name assigned to the job.');
            $table->longText('payload')->comment('Serialized Laravel queue job payload.');
            $table->unsignedTinyInteger('attempts')->comment('Number of processing attempts made for the job.');
            $table->unsignedInteger('reserved_at')->nullable()->comment('Unix timestamp when a worker reserved the job.');
            $table->unsignedInteger('available_at')->comment('Unix timestamp when the job becomes available.');
            $table->unsignedInteger('created_at')->comment('Unix timestamp when the job was created.');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Primary identifier of the Laravel job batch.');
            $table->string('name')->comment('Display name of the job batch.');
            $table->integer('total_jobs')->comment('Total number of jobs in the batch.');
            $table->integer('pending_jobs')->comment('Number of jobs still pending in the batch.');
            $table->integer('failed_jobs')->comment('Number of failed jobs in the batch.');
            $table->longText('failed_job_ids')->comment('Serialized identifiers of failed jobs in the batch.');
            $table->mediumText('options')->nullable()->comment('Serialized options and callbacks for the batch.');
            $table->integer('cancelled_at')->nullable()->comment('Unix timestamp when the batch was cancelled.');
            $table->integer('created_at')->comment('Unix timestamp when the batch was created.');
            $table->integer('finished_at')->nullable()->comment('Unix timestamp when the batch finished.');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id()->comment('Primary key of the failed Laravel queue job.');
            $table->string('uuid')->unique()->comment('Unique identifier of the failed job.');
            $table->text('connection')->comment('Queue connection used by the failed job.');
            $table->text('queue')->comment('Queue name used by the failed job.');
            $table->longText('payload')->comment('Serialized payload of the failed job.');
            $table->longText('exception')->comment('Exception stack trace captured when the job failed.');
            $table->timestamp('failed_at')->useCurrent()->comment('Timestamp when the job failed.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
