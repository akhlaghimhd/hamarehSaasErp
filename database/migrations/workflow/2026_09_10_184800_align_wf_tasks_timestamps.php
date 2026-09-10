<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-WF — Align wf_tasks timestamps with other workflow tables (timestampsTz style).
 * Makes updated_at NOT NULL with default and ensures consistency.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ensure created_at / updated_at behave like timestampsTz()
        DB::statement("ALTER TABLE wf_tasks ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP");
        DB::statement("ALTER TABLE wf_tasks ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP");
        DB::statement("ALTER TABLE wf_tasks ALTER COLUMN updated_at SET NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE wf_tasks ALTER COLUMN updated_at DROP NOT NULL");
        DB::statement("ALTER TABLE wf_tasks ALTER COLUMN updated_at DROP DEFAULT");
        DB::statement("ALTER TABLE wf_tasks ALTER COLUMN created_at DROP DEFAULT");
    }
};
