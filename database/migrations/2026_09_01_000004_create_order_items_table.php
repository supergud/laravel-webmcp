<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Kept for reference only. An order line must survive the product
            // being renamed, repriced or removed from the catalogue, so the
            // details below are snapshots rather than lookups.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('sku');

            // Snapshotted in every locale, so order history reads correctly
            // whichever language it is viewed in later.
            $table->json('name');

            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('line_total');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
