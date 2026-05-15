# P0 Stabilization Status

Last updated: 2026-05-15

## Completed In This Pass
- Removed Laravel facade dependencies from Search and Deduplication application handlers by introducing `ProjectLockPort` and `TransactionPort`.
- Added an architecture regression test that fails when `Illuminate\*` imports appear in non-Laravel bounded contexts.
- Moved Laravel cache/storage implementations behind the Laravel integration namespace while preserving deprecated compatibility aliases.
- Fixed work persistence so `scholarly_works.id` is an internal UUID and provider IDs live in `work_external_ids`.
- Preserved source provider provenance through `work_providers` and restored it when loading domain works.
- Updated persistence repositories that reference works so clusters, citation edges, and query links store internal work IDs.
- Removed empty PHP placeholder files from source and test directories, then added a regression test to prevent new empty placeholders.
- Hardened provider configuration so Laravel config controls `enabled`, `rate_limit`, retry count, timeout, API keys, and immutable per-request provider selection.
- Added SQL-level persistence regressions for PDF fetch audit rows and citation graph edge weight round-trips.

## Still P0
- Continue pruning stale docs that describe planned classes as completed behavior.
