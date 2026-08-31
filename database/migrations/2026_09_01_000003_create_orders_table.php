<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Every order belongs to exactly one account. There is no guest
            // checkout, which is what lets every read be scoped by user_id.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('number')->unique();
            $table->string('status')->index();
            $table->unsignedInteger('total');
            $table->string('currency', 3);

            $table->string('shipping_name');
            $table->string('shipping_email');
            $table->text('shipping_address');

            // A draft is created by prepare_checkout and can only become a real
            // order when a person confirms it in the UI. The token is the
            // handle for that confirmation and is cleared once it is used, so
            // it cannot be replayed.
            $table->string('confirmation_token', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
