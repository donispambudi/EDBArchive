<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libraries', function (Blueprint $table) {
            $table->id()->comment('Primary key of the supported FHE library.');
            $table->string('name')->unique()->comment('Unique technical name of the FHE library.');
            $table->boolean('is_active')->default(true)->comment('Whether the library is available for use.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('libraries');
    }
};
