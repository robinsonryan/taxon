# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Versioning note (2026-08-08).** This package was renumbered down to `0.x` to
> signal that its API is still settling. Tags were re-cut at the same commits:
> old `vN.m.p` became `v0.N.<ordinal-within-N>`. Under Composer, `^0.4.0` resolves
> to `>=0.4.0 <0.5.0`, so **every minor release may break** — which is the point.
> It will go to `1.0.0` when the consuming apps ship publicly.

## [Unreleased]

> Proposed as **0.4.0**. It breaks compatibility in four places, all listed under
> *Breaking* below; under the 0.x contract that is what a minor is for. Ryan cuts
> the tag.

### Added
- **The transition contract is real API on `TagDefinition`.** `transitions()`,
  `default()`, `canTransition()` and `availableTransitions()` used to exist only as
  a convention copied out of a test fixture, with `HasTags::transitionTo()`
  duck-typing for `canTransition` via `method_exists()`. They are now inherited
  methods:
  - `transitions(): ?array` — the state machine, keyed by source state *value*,
    with enum cases or strings as targets. `null` means "no state machine declared".
  - `default(): string|BackedEnum|null` — the state a model may enter first, used
    when it holds no value yet. `null` lets any valid value be the first.
  - `canTransition(Model $model, string|BackedEnum|null $from, string|BackedEnum $to, mixed $user = null): bool`
    — answers from the map by default. Override it and call `parent::` first for
    rules the map cannot express (permissions, model state).
  - `availableTransitions(Model $model, mixed $user = null): array` — the map's
    successors of the current state, filtered through `canTransition()`.
  - `guardsTransitions(): bool` and `normalizeState(string|BackedEnum): string`
    are the two supporting statics: the first is how `transitionTo()` knows a
    guard exists, the second is how an enum case, its backing value and a
    human-typed label are treated as one state.
- **`UnguardedTransitionException`.** `transitionTo()` throws it when the
  definition declares no guard at all, rather than silently writing the value.
- **Tag trees at arbitrary depth**, on the `Tag` model:
  - `path(): string` — the `/`-joined slugs from the root down.
  - `Tag::resolvePath(string $path, ?string $tenantId = null): ?static` — the tag
    at a slug path, walked from a root down, tenant-scoped (a null tenant matches
    tags with no tenant; it is not a wildcard). One query per segment.
  - `ancestors(): Collection` (root first) and `descendants(): Collection`
    (nearest level first) — a recursive CTE for the ids plus one query to hydrate,
    so two queries whatever the depth or subtree size.
  - `moveTo(?Tag $newParent): static` — re-parent, or promote to a root with null.
  - `CircularTagHierarchyException` (the move would put a tag inside its own
    subtree — valid rows the database will happily store, and every later ancestor
    walk hangs on them) and `DuplicateTagSlugException` (the destination already
    holds that slug for that tenant; raised from a pre-check so the caller gets a
    domain error instead of a `QueryException` that also aborts their PostgreSQL
    transaction).

  The category API in `HasTags` is untouched and stays two levels deep — a
  category and its values. Trees are for working with `Tag` directly. The boundary
  is documented in `docs/trees.md`.

### Fixed
- **`tags_unique_slug_parent_tenant` now enforces something.** It was a fluent
  composite unique over `(slug, parent_id, tenant_id)`; both trailing columns carry
  "none" as NULL, and NULLs are distinct in a unique index on every driver, so it
  rejected nothing for a **root** tag (`parent_id IS NULL`) or a **global** tag
  (`tenant_id IS NULL`) — with tenancy off by default, that is every tag in most
  installations. New migration
  `2024_01_03_000000_harden_tags_unique_index_against_nulls.php` rebuilds it over
  `COALESCE` expressions, mirroring what this package already shipped for
  `taggables`. `parent_id` holds keys, so it is cast to text first, per driver.
- **`tags.parent_id` is indexed.** Every child, ancestor and descendant lookup
  filters on it; the old composite led with `slug`, and a foreign key is not an
  index on PostgreSQL. Same migration.
- `tests/Feature/TenantScopingTest.php::"it global child tags are unique within
  parent"` now asserts the guarantee its name always claimed. Its body previously
  only checked two columns on one child — it would have failed on the old schema.
- **A definition's *first* state can no longer be one its map never mentions.**
  With a `transitions()` map and no `default()`, the guard deferred to
  `isValidValue()`; a database-backed definition has no value tags until
  something is written, so `values()` was empty, empty means "everything is
  valid", and the first `transitionTo()` could write a typo'd state the machine
  had no rules for — permanently wedging the model, since nothing transitions
  out of an undeclared state. The initial state is now checked against the map's
  own vocabulary. `TagDefinition::declaredStates()` exposes that vocabulary —
  every state the map mentions, as a key or as a target — and
  `declaresState(string|BackedEnum)` tests membership however the state is spelled.
- **A `transitions()` map's own keys are read through `normalizeState()`**, the
  way its targets always were. `'In Progress' => ['done']` used to declare a state
  nothing could ever leave: the lookup key was normalized, the map's keys were
  not, so every declared move out of it was refused. Keys are matched on their
  stored value first and their normalized form second, so a backing value that
  slugging would change (`'in_progress'`) still finds its own row.
- **A map-declared state is a valid value, tag or no tag.** A database-backed
  definition's `values()` now returns the category's children *plus* the states
  its map declares. Without that, such a definition could not make its second
  move — the first write creates the only child there is, and `isValidValue()`
  rejected every other state in the map. Definitions carrying
  `values() = array_keys(transitions())` as a workaround can drop it; keeping it
  is harmless but also makes the definition's values immutable.
- **`moveTo()` no longer grafts a tag into another tenant's tree**, raising the
  new `CrossTenantTagMoveException` instead. A subtree belongs to one tenant and
  every other write path propagates the parent's tenant down; `moveTo()` was the
  one API that could break it, and the result was not recoverable — the subtree
  walks filter on `parent_id` alone, so the destination tenant's `descendants()`
  returned the foreign tag, and `resolvePath()`, which requires every segment to
  share one tenant, could address it from neither side. Tenants are compared the
  way the unique index compares them: NULL and `''` are both "no tenant", and "no
  tenant" is its own space, so a tenant-less tag may not be grafted under a
  tenant's parent either. Promoting a tag to a root (`moveTo(null)`) is
  unaffected.

### Breaking
- **`transitionTo()` throws `UnguardedTransitionException`** where it used to fall
  through to an unguarded `setTagAs()`. Any definition without a `transitions()`
  map and without a `canTransition()` override must be written with `setTagAs()`.
  This is the point of the change: a renamed or misspelled guard used to disable
  every check with no error.
- **`transitionTo()`'s `$to` widened** from `BackedEnum` to `string|BackedEnum`.
  Existing enum call sites are unaffected.
- **`InvalidTransitionException::$from` and `::$to` widened** to
  `string|BackedEnum|null` and `string|BackedEnum`. A string source state used to
  be discarded to `null` before the exception was constructed, so a database-backed
  definition could not report what it had refused.
- **Definitions that already declared the convention methods must widen their
  signatures** to the base ones — PHP rejects a narrower parameter type
  (`?StatusEnum $from`, `TestModel $model`). Narrow inside the body instead; see
  `docs/tag-definitions.md`.
- **Consumers must re-publish migrations** (`--tag=taxon-migrations`) to pick up
  the uniqueness fix. It de-duplicates before creating the index, so a database
  already holding duplicates migrates rather than failing: each group collapses
  onto its lowest id — the oldest row under either key type — after that group's
  pivot rows and child tags are moved onto the survivor. Only a pivot row whose
  survivor already holds an identical one is dropped. Writes that used to produce
  duplicate root or global tags now raise a `QueryException`.

### Changed
- **The test suite runs on real PostgreSQL**, not SQLite `:memory:`. It uses the DDEV
  `db` service in a database of its own (`testing`), created by a `post-start` hook,
  with every connection value overridable via `TAXON_TEST_DB_*`. SQLite collapses
  `uuid`, `bigint` and `varchar` into one loose affinity, so it could not see the
  column-type bug fixed in 0.3.0 — and could not have caught the next one either.
  Contributor-facing only; no packaged code changed, and all 125 tests pass unmodified.
  The suite now uses `RefreshDatabase` (migrations once, a transaction per test) and
  the fixture consumer tables moved from inline `Schema::create()` calls in `TestCase`
  into `tests/Fixtures/database/migrations/`.

## [0.3.0] - 2026-08-08

### Added
- **`taxon.taggable_id_type` config key** (default `null`). `id_type` governs Taxon's
  own primary keys; `taggables.taggable_id` holds the *host application's* keys, and
  the two need not match. `null` means "follow `id_type`", so no existing consumer
  sees a change — set it only for a mixed app (UUID7 tags over integer-keyed models,
  or the reverse), which previously had no correct configuration at all.
- **First PostgreSQL-backed test** (`tests/Feature/PostgresTaggableIdTypeTest.php`).
  Column *types* are unfalsifiable on SQLite — `uuid`, `bigint` and `varchar` all
  collapse to the same loose affinity — which is how the bug below shipped. This
  file runs the published migrations against the DDEV Postgres service and reads
  `information_schema` directly. It skips, loudly, where no Postgres is reachable.

### Fixed
- **`taggables.taggable_id` is no longer a `uuid` column when `id_type` is
  `incrementing`.** The migration branched on `id_type` for every key column except
  the polymorphic one, where it called `uuidMorphs('taggable')` unconditionally. With
  the shipped default on PostgreSQL that produced a real `uuid` column, so tagging any
  integer-keyed model failed on insert (`invalid input syntax for type uuid`). It now
  emits `morphs()` or `uuidMorphs()` per `taggable_id_type`.

  A consumer running `id_type => 'uuid7'` is unaffected — the emitted schema is byte
  for byte what it was.

  **Existing consumers on the default `incrementing`:** migrations are published, so
  your copy in `database/migrations/` is untouched by this release. Re-publish it
  (`--tag=taxon-migrations --force`) for a fresh install, or, if the broken table is
  already live on PostgreSQL, write a migration altering `taggable_id` to `bigint`.
  On MySQL or SQLite nothing is required: `uuidMorphs` degraded to a string column
  there and integer keys were being stored without complaint.
- **The `uuid7` migration branch could not run on PostgreSQL at all.**
  `$table->uuid('id')->primary()` compiles the primary key into a command emitted
  *after* the self-referencing `parent_id` foreign key, and PostgreSQL rejects a
  foreign key whose target carries no unique constraint yet ("there is no unique
  constraint matching given keys for referenced table"). The `tags` migration now
  declares `$table->primary('id')` as its own statement, ahead of the foreign key.
  SQLite hid this by folding foreign keys into the create statement.

### Changed
- **Dropped Laravel 11 support — BREAKING for any consumer pinned to Laravel 11.**
  `illuminate/contracts`, `illuminate/database` and `illuminate/support` narrow from
  `^11.0|^12.0|^13.0` to `^12.0|^13.0`, and `orchestra/testbench` from
  `^9.0|^10.0|^11.0` to `^10.0|^11.0` (Testbench 9 *is* the Laravel 11 harness, so
  leaving it declared a test matrix that can no longer resolve).

  Laravel 11 was advertised but structurally untestable, and had never been verified
  against a single test run. The package requires `pestphp/pest ^4.0`, Pest 4 requires
  PHPUnit 12, and Testbench 9 caps at PHPUnit 11 — so Composer could never assemble a
  Laravel 11 install here. The `^11.0` was a compatibility promise nobody could keep;
  removing it makes the declared support match what the suite actually exercises.

  No runtime code changed. A consumer already on Laravel 12 or 13 is unaffected.

## [0.2.1] - 2026-08-08

Tooling and type-annotation release. **No runtime behaviour changed** — the only
`src/` edit since 0.2.0 is a docblock, so this is a safe drop-in for any 0.2.0
consumer.

### Added
- `composer quality` now gates Rector (`@refactor:check`) alongside Pint, PHPStan and Pest.

### Changed
- **Lowered the PHP floor from `^8.3` to `^8.2`.** This widens the supported range —
  no consumer that worked before stops working. The code was already 8.2-clean
  (verified by PHPStan `phpVersion: 80200`).
- PHPStan now analyses `tests/Fixtures` in addition to `src`, at level 8 with **no
  `ignoreErrors` entries at all** (the previous blanket `missingType.iterableValue`
  suppression was removed and the eight underlying findings fixed).

### Fixed
- `Tag::addChildren()` now documents its `array<int, string>` parameter type.
  Docblock only — no signature change.
