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
        Schema::create('synced_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accurate_id')->unique();
            $table->string('sku')->nullable()->index();
            $table->string('name')->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('stock', 15, 2)->nullable();
            $table->string('item_type')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->boolean('suspended')->default(false)->index();
            $table->json('raw_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synced_items');
    }
};
