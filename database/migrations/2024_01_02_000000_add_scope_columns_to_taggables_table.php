<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $tenantColumn = config('taxon.tenant.column', 'tenant_id');

        Schema::table($pivotTable, function (Blueprint $table) {
            $table->string('scope_type')->nullable()->after('taggable_id');
            $table->string('scope_id', 36)->nullable()->after('scope_type');

            $table->index(['scope_type', 'scope_id'], 'taggables_scope_index');
        });

        // Drop the old unique constraint and replace with one that includes scope
        // columns. Uses COALESCE to work around NULL != NULL in unique indexes
        // across PostgreSQL, MySQL, and SQLite — without this, duplicate unscoped
        // rows would not be caught.
        Schema::table($pivotTable, function (Blueprint $table) {
            $table->dropUnique('taggables_unique_tag_model_tenant');
        });

        DB::statement(
            "CREATE UNIQUE INDEX taggables_unique_tag_model_scope_tenant ON {$pivotTable} " .
            "(tag_id, taggable_type, taggable_id, COALESCE(scope_type, ''), COALESCE(scope_id, ''), COALESCE({$tenantColumn}, ''))"
        );
    }

    public function down(): void
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $tenantColumn = config('taxon.tenant.column', 'tenant_id');

        Schema::table($pivotTable, function (Blueprint $table) {
            $table->dropIndex('taggables_unique_tag_model_scope_tenant');
        });

        Schema::table($pivotTable, function (Blueprint $table) use ($tenantColumn) {
            $table->unique(
                ['tag_id', 'taggable_type', 'taggable_id', $tenantColumn],
                'taggables_unique_tag_model_tenant'
            );
        });

        Schema::table($pivotTable, function (Blueprint $table) {
            $table->dropIndex('taggables_scope_index');
            $table->dropColumn(['scope_type', 'scope_id']);
        });
    }
};
