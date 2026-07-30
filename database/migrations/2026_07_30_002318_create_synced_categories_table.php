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
        Schema::create('synced_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accurate_id')->unique();
            $table->string('name')->nullable();
            // References the parent category's accurate_id (not synced_categories.id) —
            // a flat listing from Accurate may not guarantee parents are inserted
            // before children, so this avoids a two-pass insert just to satisfy an FK.
            $table->unsignedBigInteger('parent_id')->nullable()->index();
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
        Schema::dropIfExists('synced_categories');
    }
};
