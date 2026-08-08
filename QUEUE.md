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

### Bug: `taggables.taggable_id` is a `uuid` column even when `id_type` is `incrementing`
- **Added**: 2026-08-08 · Laravel-11-constraint-drop session — found while reading the source to rebuild `CLAUDE.md`
- **Tier**: LIGHT — one migration branch plus the first Postgres-backed test this package has ever had
- **Why deferred**: out of scope for a constraints/docs pass, and it is a schema change that wants its own test alongside it
- **Context**: `database/migrations/2024_01_01_000000_create_tags_tables.php` calls
  `$table->uuidMorphs('taggable')` **unconditionally**, while the rest of that same
  migration carefully branches on `config('taxon.id_type')` for every other key column.
  Laravel's `uuidMorphs()` emits `$table->uuid("{$name}_id")`, and `typeUuid()` compiles
  to `uuid` on Postgres but to `varchar` on SQLite.

  **The failure**: shipped default config (`'id_type' => 'incrementing'`) on a Postgres
  host gives you a real `uuid`-typed `taggable_id`. Tagging any integer-keyed model then
  fails on insert — the pivot cannot hold `taggable_id = 1`. It is the *default* setting
  that is broken, which is the worst place for this to live.

  **Not affected**: a consumer that sets `id_type` to `uuid7`. Verified against ccstake,
  which does exactly that, and whose `taggable_id` is a genuine `uuid` — correct there.
  So the exposure is precisely "took the defaults, ran Postgres".

  **Why no test catches it**: the suite is SQLite `:memory:` only
  (`tests/TestCase.php::getEnvironmentSetUp`). On SQLite `uuidMorphs` degrades to
  `varchar` and SQLite's loose typing swallows an integer, so the mismatch is invisible.
  DDEV already runs a Postgres 18 `db` service that the suite never touches.

  **Proposed direction**: branch the morph columns on `id_type` the way the rest of the
  migration already does — `uuidMorphs('taggable')` for `uuid7`, plain `morphs('taggable')`
  for `incrementing`. Then close the coverage hole, because a SQLite-only suite structurally
  cannot prove this: add a Postgres-backed test (or a driver matrix) asserting the emitted
  column type for both `id_type` values. Note the existing `IdTypeCoherence`-style test in
  the sibling annotation package as a shape to copy.

  **Open question worth settling while in there**: `taxon.id_type` governs *Taxon's own*
  primary keys, but `taggable_id` holds the **host app's** model keys, which need not match.
  An app with UUID tags and integer-keyed posts has no correct single answer today. A
  separate `taxon.taggable_id_type` key may be the honest fix; decide before writing the
  migration branch.

### Dead statement in `HasTags::deleteScopedPivotRecord()`
- **Added**: 2026-08-08 · Laravel-11-constraint-drop session — found while reading the source to rebuild `CLAUDE.md`
- **Tier**: SOLO — delete one line
- **Why deferred**: trivial, but it is a source edit and the session it surfaced in was constraints + docs only
- **Context**: `src/HasTags.php` line 184, the first statement in the method body:
  `config('taxon.tables.taggables', 'taggables');` — called and the result thrown away.
  Every sibling method assigns it (`$pivotTable = config(...)`) and uses it; this one does
  not, and does not need it, because it goes through `$this->tags()->newPivotStatement()`
  which already resolves the table itself. Almost certainly a leftover from an edit that
  moved off a raw `DB::table($pivotTable)` query.

  Harmless at runtime — one wasted config lookup per scoped detach — but it reads as though
  the table name matters here, which misleads anyone editing the scope logic.

  **Why it survived**: Rector does not flag it. `SetList::DEAD_CODE` is enabled in
  `rector.php`, but a bare function call is a potentially side-effecting expression
  statement, so Rector will not remove it; PHPStan level 8 does not complain either.
  Nothing in the gate is going to find this — it has to be deleted by hand.

  **Proposed direction**: delete the line. No test change needed; the existing scoped-tagging
  tests already cover the method's behaviour.

## Blocked

## Archive
