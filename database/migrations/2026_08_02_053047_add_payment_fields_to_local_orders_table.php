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
        Schema::table('local_orders', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->index();
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('snap_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_method', 'paid_at', 'snap_token']);
        });
    }
};
