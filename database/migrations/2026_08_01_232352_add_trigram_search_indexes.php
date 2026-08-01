<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX product_display_display_name_trgm_idx ON product_display USING gin (display_name gin_trgm_ops)');
        DB::statement('CREATE INDEX synced_items_name_trgm_idx ON synced_items USING gin (name gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS synced_items_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS product_display_display_name_trgm_idx');
    }
};
