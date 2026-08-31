<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Stable, locale-independent identifier used in URLs and as the
            // value AI agents pass to the search_products tool.
            $table->string('slug')->unique();

            // spatie/laravel-translatable stores {"en": "...", "zh-TW": "..."}
            // in these columns.
            $table->json('name');
            $table->json('description')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
