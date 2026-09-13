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
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary()->comment('Unique identifier of the cached value.');
            $table->mediumText('value')->comment('Serialized cached value.');
            $table->integer('expiration')->index()->comment('Unix timestamp when the cached value expires.');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary()->comment('Unique identifier of the cache lock.');
            $table->string('owner')->comment('Token identifying the owner of the cache lock.');
            $table->integer('expiration')->index()->comment('Unix timestamp when the cache lock expires.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
