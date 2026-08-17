<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RobinsonRyan\Taxon\Support\SchemaSupport;

/**
 * Gives `tags.id` and `taggables.id` the `uuidv7()` column default on installs
 * built before 0.5.0, where the create migration declared the columns without
 * one.
 *
 * Up to 0.5.0 the package minted its own keys in PHP, so a uuid7 install ran
 * happily with no default: `Tag::create()` supplied the key, and only the pivot
 * depended on anything else. (`attach()` does save a `Taggable` model — `tags()`
 * sets `->using()` — but that model never minted a key either.) 0.5.0 stops
 * generating keys in PHP entirely, which makes the column default the only thing
 * left. Without this migration the next insert on either table fails with a
 * not-null violation.
 *
 * Only touches uuid-typed columns on a connection that actually has `uuidv7()`.
 * A bigint install is left on its sequence, and a driver without the function is
 * left alone rather than handed an expression it cannot compile.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('taxon.id_type') !== 'uuid7' || ! SchemaSupport::supportsUuidV7()) {
            return;
        }

        foreach ($this->taxonTables() as $table) {
            if (! Schema::hasTable($table) || $this->columnType($table, 'id') !== 'uuid') {
                continue;
            }

            // SET DEFAULT replaces whatever is there, so running this twice is
            // the same as running it once.
            DB::statement("alter table {$table} alter column id set default uuidv7()");
        }
    }

    /**
     * Deliberately empty.
     *
     * Dropping the default would leave every subsequent insert on these tables
     * with nothing to generate a key — the exact breakage this migration exists
     * to prevent — and a rollback that breaks the schema it restores is worse
     * than one that leaves a harmless default in place. The create migration's
     * own `down()` drops the tables outright, so nothing is orphaned.
     */
    public function down(): void {}

    /** @return list<string> */
    private function taxonTables(): array
    {
        return [
            (string) config('taxon.tables.tags', 'tags'),
            (string) config('taxon.tables.taggables', 'taggables'),
        ];
    }

    private function columnType(string $table, string $column): string
    {
        $type = DB::table('information_schema.columns')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->value('data_type');

        return is_string($type) ? $type : 'missing';
    }
};
