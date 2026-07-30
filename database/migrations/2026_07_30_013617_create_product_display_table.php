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
        Schema::create('product_display', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->unique()->constrained('synced_items')->restrictOnDelete();
            $table->boolean('is_published')->default(false)->index();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->string('display_category')->nullable()->index();
            $table->string('brand')->nullable()->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_display');
    }
};
