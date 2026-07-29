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
        Schema::create('accurate_connections', function (Blueprint $table) {
            $table->id();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('id_db')->nullable();
            $table->string('db_alias')->nullable();
            $table->text('session_id')->nullable();
            $table->string('session_host')->nullable();
            $table->timestamp('session_created_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('last_refresh_attempted_at')->nullable();
            $table->text('last_refresh_error')->nullable();
            $table->string('granted_scopes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accurate_connections');
    }
};
