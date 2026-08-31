<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->string('sku')->unique();
            $table->string('slug')->unique();

            // Translatable: {"en": "...", "zh-TW": "..."}
            $table->json('name');
            $table->json('description')->nullable();

            // Money is stored as a whole-TWD integer. The demo prices in New
            // Taiwan dollars, which is not subdivided in practice, and integers
            // keep every cart and order total exact.
            $table->unsignedInteger('price');

            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category_id']);
            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
