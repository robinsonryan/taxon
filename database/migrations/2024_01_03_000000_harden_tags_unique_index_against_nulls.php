<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `tags_unique_slug_parent_tenant` enforce something, and indexes
 * `parent_id`.
 *
 * The create migration builds the uniqueness with Laravel's fluent
 * `$table->unique(['slug', 'parent_id', $tenantColumn])`, which emits a plain
 * composite unique constraint. Both of the trailing columns are nullable and
 * carry "no parent" / "no tenant" as NULL, and under SQL's NULL semantics two
 * rows whose keys contain a NULL are never equal. So the index has never
 * rejected anything for a **root** tag or a **global** tag — which, with tenancy
 * off by default, is every tag in most installations.
 *
 * The fix is the one this package already ships for `taggables` in
 * `2024_01_02_000000_add_scope_columns_to_taggables_table.php`: build the index
 * over `COALESCE(col, '')` so "none" becomes a comparable empty string. The
 * `tags` half never got it.
 *
 * `parent_id` holds keys (bigint or uuid depending on `taxon.id_type`), so it is
 * cast to text before COALESCE — `''` is neither a valid bigint nor a valid
 * uuid. The cast syntax is per-driver; see castParentIdToText().
 *
 * **De-duplication runs first.** A consumer upgrading into this migration may
 * already hold the duplicates it is about to forbid, and a bare
 * CREATE UNIQUE INDEX would fail their deploy with no way forward. Each
 * duplicate group is collapsed onto its **lowest id** — the oldest row under
 * either key type, auto-increment or time-ordered UUIDv7 — after that group's
 * pivot rows and child tags have been moved onto the survivor. Nothing is
 * dropped that carries information: only a pivot row whose survivor already has
 * an identical one, which is a duplicate assignment either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tagTable = $this->tagTable();
        $tenantColumn = $this->tenantColumn();

        // Dropped before the de-duplication rather than after, so the old
        // NULL-distinct constraint cannot reject an intermediate state: merging
        // two parents can push two of their children into a collision that did
        // not exist a moment earlier.
        Schema::table($tagTable, function (Blueprint $table): void {
            $table->dropUnique('tags_unique_slug_parent_tenant');
        });

        $this->collapseDuplicateTags();

        DB::statement(
            "CREATE UNIQUE INDEX tags_unique_slug_parent_tenant ON {$tagTable} " .
            "(slug, ({$this->parentKey()}), (COALESCE({$tenantColumn}, '')))"
        );

        // Every child lookup, ancestor walk and descendant walk filters on
        // parent_id. The composite unique led with `slug`, so none of them were
        // covered; a plain foreign key is not an index on PostgreSQL.
        Schema::table($tagTable, function (Blueprint $table): void {
            $table->index('parent_id', 'tags_parent_id_index');
        });
    }

    /**
     * Restores the fluent composite unique exactly as the create migration built
     * it — which means **rolling this back restores the defect**: the index goes
     * back to NULL-distinct semantics and stops rejecting duplicate root and
     * global tags. Nothing `up()` removed is restored, and the de-duplication
     * cannot be undone.
     */
    public function down(): void
    {
        $tagTable = $this->tagTable();
        $tenantColumn = $this->tenantColumn();

        Schema::table($tagTable, function (Blueprint $table): void {
            $table->dropIndex('tags_parent_id_index');
            $table->dropIndex('tags_unique_slug_parent_tenant');
        });

        Schema::table($tagTable, function (Blueprint $table) use ($tenantColumn): void {
            $table->unique(['slug', 'parent_id', $tenantColumn], 'tags_unique_slug_parent_tenant');
        });
    }

    /**
     * Merge every duplicate (slug, parent, tenant) group onto its lowest-id
     * member.
     *
     * Repeats until a pass finds nothing: re-parenting a loser's children can
     * push two of them into a collision that did not exist before. Every pass
     * deletes at least one row, so it terminates.
     */
    private function collapseDuplicateTags(): void
    {
        $tagTable = $this->tagTable();
        $parentKey = $this->parentKey();
        $tenantKey = "COALESCE({$this->tenantColumn()}, '')";

        while (true) {
            $groups = DB::table($tagTable)
                ->selectRaw("slug, {$parentKey} as parent_key, {$tenantKey} as tenant_key")
                ->groupByRaw("slug, {$parentKey}, {$tenantKey}")
                ->havingRaw('count(*) > 1')
                ->get();

            if ($groups->isEmpty()) {
                return;
            }

            foreach ($groups as $group) {
                $ids = DB::table($tagTable)
                    ->where('slug', $group->slug)
                    ->whereRaw("{$parentKey} = ?", [$group->parent_key])
                    ->whereRaw("{$tenantKey} = ?", [$group->tenant_key])
                    ->orderBy('id')
                    ->pluck('id');

                $keep = $ids->shift();

                foreach ($ids as $loser) {
                    $this->movePivotRows($loser, $keep);

                    DB::table($tagTable)->where('parent_id', $loser)->update(['parent_id' => $keep]);
                    DB::table($tagTable)->where('id', $loser)->delete();
                }
            }
        }
    }

    /**
     * Re-point the loser's pivot rows at the survivor, dropping any the survivor
     * already holds — `taggables_unique_tag_model_scope_tenant` does fire, and a
     * blind UPDATE would collide with it.
     */
    private function movePivotRows(mixed $loser, mixed $keep): void
    {
        $pivotTable = $this->pivotTable();
        $tenantColumn = $this->tenantColumn();

        $rows = DB::table($pivotTable)->where('tag_id', $loser)->get();

        foreach ($rows as $row) {
            $twin = DB::table($pivotTable)
                ->where('tag_id', $keep)
                ->where('taggable_type', $row->taggable_type)
                ->where('taggable_id', $row->taggable_id);

            foreach (['scope_type', 'scope_id', $tenantColumn] as $column) {
                $value = $row->{$column} ?? null;

                $value === null
                    ? $twin->whereNull($column)
                    : $twin->where($column, $value);
            }

            $twin->exists()
                ? DB::table($pivotTable)->where('id', $row->id)->delete()
                : DB::table($pivotTable)->where('id', $row->id)->update(['tag_id' => $keep]);
        }
    }

    /**
     * `parent_id` as a non-null text key. Wrapped in parentheses by the callers,
     * which MySQL requires of a functional index key part and PostgreSQL and
     * SQLite both accept.
     */
    private function parentKey(): string
    {
        return "COALESCE({$this->castParentIdToText()}, '')";
    }

    private function castParentIdToText(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'parent_id::text',
            'mysql', 'mariadb' => 'cast(parent_id as char)',
            default => 'cast(parent_id as text)',
        };
    }

    private function tagTable(): string
    {
        return (string) config('taxon.tables.tags', 'tags');
    }

    private function pivotTable(): string
    {
        return (string) config('taxon.tables.taggables', 'taggables');
    }

    private function tenantColumn(): string
    {
        return (string) config('taxon.tenant.column', 'tenant_id');
    }
};
