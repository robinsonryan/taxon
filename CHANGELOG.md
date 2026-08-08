# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.1] - 2026-08-08

Tooling and type-annotation release. **No runtime behaviour changed** — the only
`src/` edit since 2.0.0 is a docblock, so this is a safe drop-in for any 2.0.0
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
