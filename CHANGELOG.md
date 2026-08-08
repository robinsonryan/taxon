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
