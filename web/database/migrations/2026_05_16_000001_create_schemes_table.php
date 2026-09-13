<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schemes', function (Blueprint $table) {
            $table->id()->comment('Primary key of the supported FHE scheme.');
            $table->foreignId('library_id')
                ->comment('FHE library that implements this scheme.')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('scheme_name')->unique()->comment('Unique technical name of the FHE scheme.');
            $table->json('configuration_json')->nullable()->comment('JSON definitions for parameters accepted by the scheme.');
            $table->boolean('is_active')->default(true)->comment('Whether the scheme is available for use.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schemes');
    }
};
