<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Company UI creates entity addresses without address_type/country catalogs yet.
 * Original migration required address_type_id + country_id (NOT NULL) → 500 on insert.
 * Align DB with CreateEntityAddressRequest (nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE entity_addresses ALTER COLUMN address_type_id DROP NOT NULL');
        DB::statement('ALTER TABLE entity_addresses ALTER COLUMN country_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Best-effort restore — may fail if null rows exist
        DB::statement('ALTER TABLE entity_addresses ALTER COLUMN address_type_id SET NOT NULL');
        DB::statement('ALTER TABLE entity_addresses ALTER COLUMN country_id SET NOT NULL');
    }
};
