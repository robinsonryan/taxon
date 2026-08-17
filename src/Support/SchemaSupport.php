<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Schema helpers shared by this package's migrations.
 */
final class SchemaSupport
{
    private static ?bool $supportsUuidV7 = null;

    /**
     * Declare a UUID primary key, defaulting it in the database only where the
     * database can actually generate one.
     *
     * `uuidv7()` is a PostgreSQL 18 built-in. In uuid7 mode it is the *only*
     * thing that generates a key here: no model in this package mints one in
     * PHP, and `attach()` writes the pivot row through the query builder rather
     * than through a model, so nothing but the column default stands between an
     * assignment and a not-null violation. Applying it unconditionally would
     * make every migration unrunnable on SQLite and MySQL — and on PostgreSQL
     * below 18 — so it is applied where it is available and skipped where it is
     * not.
     *
     * The key is declared with an explicit primary() call rather than the fluent
     * `->primary()` column modifier. Fluent index modifiers only become commands
     * when the blueprint is compiled, so they land *after* every foreign key the
     * migration declared — and PostgreSQL rejects a self-referencing foreign key
     * whose target table has no key yet ("there is no unique constraint matching
     * given keys for referenced table"). Declaring it here puts the key first,
     * where a foreign key in the same Schema::create() can see it.
     */
    public static function uuidPrimary(Blueprint $table, string $column = 'id'): void
    {
        $definition = $table->uuid($column);
        $table->primary($column);

        if (self::supportsUuidV7()) {
            $definition->default(DB::raw('uuidv7()'));
        }
    }

    /**
     * Whether the active connection exposes a native `uuidv7()` function.
     */
    public static function supportsUuidV7(): bool
    {
        if (self::$supportsUuidV7 !== null) {
            return self::$supportsUuidV7;
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return self::$supportsUuidV7 = false;
        }

        try {
            $found = $connection->select("select 1 from pg_proc where proname = 'uuidv7' limit 1");
        } catch (Throwable) {
            return self::$supportsUuidV7 = false;
        }

        return self::$supportsUuidV7 = $found !== [];
    }

    /**
     * Reset the cached capability probe. Intended for tests that swap connections.
     */
    public static function flushCapabilityCache(): void
    {
        self::$supportsUuidV7 = null;
    }
}
