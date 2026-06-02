# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release
- Added repository-level maintainer guidance in `AGENTS.md`.

### Changed
- Expanded PHPDoc coverage across core `Time`, `Period`, and chart types to
  document invariants, notation rules, boundary semantics, and exception
  behavior to the package's higher-detail documentation standard.
- Resynced `Cline\Temporal\Time` with the latest upstream `bakame/tokei`
  changes, including the `DurationFormat` and `IntervalFormat` APIs and the
  new `Time::fromFormat`, `Time::format`, and `Time::shift` workflows.

### Removed
- Removed the deprecated Period API surface:
  `InitialDatePresence`, `Period::fromDateRange`,
  `Period::dateRangeForward`, and `Period::dateRangeBackwards`.
- Removed the older Time notation API names in favor of the upstream format
  API:
  `DurationNotation`, `IntervalNotation`, `Duration::fromNotation`,
  `Duration::toNotation`, and `Interval::fromNotation` /
  `Interval::toNotation`.
