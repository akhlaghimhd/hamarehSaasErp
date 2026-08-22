<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_tags', function (Blueprint $table) {
            $table->uuid('entity_tag_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->uuid('tag_id');
            $table->foreign('tag_id', 'fk_entity_tags_tag')
                  ->references('tag_id')
                  ->on('tags')
                  ->onDelete('restrict');
            
            $table->string('target_entity_type', 100);
            $table->uuid('target_entity_id');
            
            $table->timestampTz('assigned_at')->useCurrent();
            $table->uuid('assigned_by')->nullable();

            $table->unique(['tenant_id', 'tag_id', 'target_entity_type', 'target_entity_id'], 'uq_entity_tags_mapping');
            $table->index(['tenant_id', 'target_entity_type', 'target_entity_id'], 'idx_entity_tags_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_tags');
    }
};