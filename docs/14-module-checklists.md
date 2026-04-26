# Module Checklists

## Shared Kernel Checklist
- [ ] `WorkId` is the only DOI normalization source
- [ ] `WorkIdSet` is immutable
- [ ] no provider logic in shared kernel
- [ ] no framework imports

## Search Checklist
- [ ] all query dimensions included in cache identity
- [ ] provider calls rate-limited
- [ ] raw data off by default
- [ ] provider adapters return domain objects, not arrays
- [ ] adapter tests use recorded fixtures

## Deduplication Checklist
- [ ] exact ID policies run before fuzzy policies
- [ ] representative election is explicit
- [ ] provider priority is configurable, not hardcoded
- [ ] Unicode-safe title normalization/matching
- [ ] clustering provenance can be persisted

## Citation Network Checklist
- [ ] graph invariant rejects dangling edges
- [ ] co-citation implementation is not O(n²)
- [ ] bibliographic coupling implementation is not O(n²)
- [ ] snowball returns only new works for each round
- [ ] metrics isolated from persistence concerns

## Dissemination Checklist
- [ ] serializers are pure
- [ ] storage is abstracted
- [ ] PDF retrieval logs attempts per source
- [ ] export format handling is explicit
- [ ] graph exports preserve IDs and weights

## Laravel Checklist
- [ ] provider registry built once at boot
- [ ] jobs call application services only
- [ ] Eloquent models stay in infrastructure
- [ ] published config contains no personal email defaults
- [ ] migrations reflect provenance and decision history