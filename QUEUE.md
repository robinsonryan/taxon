# Implementation Queue

> Deferred work, captured mid-session, picked up deliberately. Managed by `/queue`;
> convention: `$CLAUDE_HARNESS_DIR/notes/implementation-queue.md`. Hand-editing is fine.

## Queued

### Support Pest 5 / PHPUnit 13 / PHP 8.4+ in the constraint matrix
- **Added**: 2026-08-07 · harness health & efficiency session — apps are queued to upgrade to Pest 5 for Tia; consuming apps can't move until this package allows it
- **Tier**: SOLO
- **Why deferred**: harness-wide decision made first; per-package constraint widening is independent work
- **Context**: current: php `^8.2` (floor lowered from `^8.3` by the package-quality-baseline
  pass, 2026-08-07 — this item is about the *upper* end), pest ^4.0. Widen composer constraints to include pest ^5 / phpunit ^13 / php 8.4+ and run the suite on the new matrix. Research + decisions: $CLAUDE_HARNESS_DIR/notes/harness-health-research-2026-08.md. ddev already runs php 8.5 against the ^8.3 constraint — align while here. Consumed by ccstake

## Blocked

## Archive

### `tags_unique_slug_parent_tenant` does not fire for root or global tags
- **Done**: 2026-08-10 · v0.4.0 — `database/migrations/2024_01_03_000000_harden_tags_unique_index_against_nulls.php`
  drops the fluent composite unique, collapses any duplicate groups a consumer is
  already holding (pivot rows and children move onto the oldest member first), and
  recreates the index over `COALESCE` expressions. It also adds the missing
  `tags_parent_id_index`. `TenantScopingTest::"it global child tags are unique within
  parent"` now asserts the guarantee its name always claimed.
- **Added**: 2026-08-08 · SQLite→PostgreSQL test-suite migration
- **Tier**: LIGHT (schema migration + tests; breaking for consumers holding duplicates)
- **Why deferred**: needs a new migration that may fail on consumer data that already
  contains duplicates, plus a de-duplication story. Out of scope for a test-harness
  change, and a schema decision to make deliberately rather than in passing.
- **Context**: the index is plain `btree (slug, parent_id, tenant_id)`. NULLs are
  distinct in a unique index on every driver, so it enforces nothing whenever either
  nullable column is NULL — i.e. for **every root tag** (`parent_id IS NULL`) and
  **every global tag** (`tenant_id IS NULL`). Two identical root tags, or two
  identical global children of one parent, insert cleanly. Verified against
  PostgreSQL 18: both duplicate pairs accepted.

  The sibling `taggables` index already solves exactly this, in
  `2024_01_02_000000_add_scope_columns_to_taggables_table.php`, by building the
  unique over `COALESCE(scope_type,''), COALESCE(scope_id,''), COALESCE(tenant_id,'')`.
  The `tags` table never got the same treatment. Proposed fix: mirror it — drop
  `tags_unique_slug_parent_tenant` and recreate as a raw
  `CREATE UNIQUE INDEX ... (slug, COALESCE(parent_id::text,''), COALESCE(tenant_id,''))`.

  Note `tests/Feature/TenantScopingTest.php::"it global child tags are unique within
  parent"` is named for a guarantee it does not assert — its body only checks
  `parent_id` and `tenant_id` on a single child. Give it real teeth when the index
  is fixed; it will fail today.

