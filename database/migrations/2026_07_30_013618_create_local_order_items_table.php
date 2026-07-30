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
        Schema::create('local_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('synced_items')->restrictOnDelete();
            $table->string('item_name');
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('quantity', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_order_items');
    }
};
