<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RobinsonRyan\Taxon\Support\SchemaSupport;

return new class extends Migration
{
    public function up(): void
    {
        $tagTable = config('taxon.tables.tags', 'tags');
        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $tenantColumn = config('taxon.tenant.column', 'tenant_id');
        $useUuid = config('taxon.id_type') === 'uuid7';

        // `id_type` describes Taxon's OWN keys; `taggable_id` holds the host
        // application's keys, which need not be the same type. Null means "follow
        // id_type", which is what every existing consumer gets.
        $taggableUsesUuid = (config('taxon.taggable_id_type') ?? config('taxon.id_type')) === 'uuid7';

        Schema::create($tagTable, function (Blueprint $table) use ($tagTable, $tenantColumn, $useUuid) {
            if ($useUuid) {
                SchemaSupport::uuidPrimary($table);
            } else {
                $table->id();
            }

            $table->string('name');
            $table->string('slug');

            if ($useUuid) {
                $table->uuid('parent_id')->nullable();
                $table->foreign('parent_id')
                    ->references('id')
                    ->on($tagTable)
                    ->cascadeOnDelete();
            } else {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained($tagTable)
                    ->cascadeOnDelete();
            }

            $table->string($tenantColumn)->nullable()->index();
            $table->boolean('assignable')->default(true);
            $table->boolean('single_select')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'parent_id', $tenantColumn], 'tags_unique_slug_parent_tenant');
        });

        Schema::create($pivotTable, function (Blueprint $table) use ($tagTable, $tenantColumn, $useUuid, $taggableUsesUuid) {
            if ($useUuid) {
                SchemaSupport::uuidPrimary($table);
                $table->uuid('tag_id');
                $table->foreign('tag_id')
                    ->references('id')
                    ->on($tagTable)
                    ->cascadeOnDelete();
            } else {
                $table->id();
                $table->foreignId('tag_id')
                    ->constrained($tagTable)
                    ->cascadeOnDelete();
            }

            if ($taggableUsesUuid) {
                $table->uuidMorphs('taggable');
            } else {
                $table->morphs('taggable');
            }

            $table->string($tenantColumn)->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['tag_id', 'taggable_type', 'taggable_id', $tenantColumn],
                'taggables_unique_tag_model_tenant'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('taxon.tables.taggables', 'taggables'));
        Schema::dropIfExists(config('taxon.tables.tags', 'tags'));
    }
};
