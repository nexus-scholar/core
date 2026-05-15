# Module Checklists

## Shared Kernel Checklist
- [x] `WorkId` is the only DOI normalization source
- [x] `WorkIdSet` is immutable
- [x] no provider logic in shared kernel
- [x] no framework imports

## Search Checklist
- [x] all query dimensions included in cache identity
- [x] provider calls rate-limited
- [x] raw data off by default
- [x] provider adapters return domain objects, not arrays
- [x] adapter tests use recorded fixtures

## Deduplication Checklist
- [x] exact ID policies run before fuzzy policies
- [x] representative election is explicit
- [x] provider priority is configurable, not hardcoded
- [x] Unicode-safe title normalization/matching
- [x] clustering provenance can be persisted

## Citation Network Checklist
- [ ] graph invariant rejects dangling edges
- [ ] co-citation implementation is not O(n²)
- [ ] bibliographic coupling implementation is not O(n²)
- [ ] snowball returns only new works for each round
- [ ] metrics isolated from persistence concerns

## Dissemination Checklist
- [x] serializers are pure
- [x] storage is abstracted
- [x] PDF retrieval logs attempts per source
- [x] export format handling is explicit
- [ ] graph exports preserve IDs and weights

## Laravel Checklist
- [x] provider registry built once at boot
- [ ] jobs call application services only
- [x] Eloquent models stay in infrastructure
- [x] published config contains no personal email defaults
- [x] migrations reflect provenance and decision history

## P0 Stabilization Notes
- [x] Application services use ports for project-lock checks and transactions instead of importing Laravel facades directly.
- [x] Work persistence uses an internal UUID row ID and external ID rows for provider identifiers.
- [x] Work provider provenance is persisted and round-tripped through `work_providers`.
- [ ] Empty placeholder source/test files still need either implementation or removal from the public surface.
