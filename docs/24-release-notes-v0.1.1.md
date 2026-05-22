# Release Notes: v0.1.1

Release date: 2026-05-22

`v0.1.1` is a dependency metadata patch release for `nexus-scholar/core`.

## Changes

- Require `nexus-scholar/graph-algorithms ^1.2` instead of `mbsoft31/graph-algorithms ^1.1`.
- Keep runtime graph implementation behavior unchanged.
- Continue relying on `nexus-scholar/graph-core ^1.0`.

## Compatibility

`nexus-scholar/graph-algorithms v1.2.0` replaces `mbsoft31/graph-algorithms`, so this release removes the old Composer vendor name from `core` while preserving Composer compatibility for downstream projects during migration.

PHP namespaces in the graph packages remain `Mbsoft\Graph\*` for source compatibility.

## Validation

- `composer validate --strict`
- `composer test`
- `composer analyse`
- `composer format:check`
- `git diff --check`
- Clean Composer and Laravel consumer smoke after tagging.

