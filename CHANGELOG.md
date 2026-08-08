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
