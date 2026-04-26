# Agent Operating Rules

## Purpose

This file is written for coding agents and maintainers. It is the short operational contract that should be re-read before major edits.

## Before changing code

Always ask:
1. Which bounded context owns this concept?
2. Is this domain behavior or infrastructure behavior?
3. Is there already a port for this?
4. Will this introduce Laravel into the core?
5. Will this duplicate normalization or matching logic?
6. Can this be proven with a fast test?

## If adding a provider

You must:
- implement `AcademicProviderPort`
- wire rate limiting
- add recorded integration fixtures
- define supported namespaces
- map raw payloads to `ScholarlyWork`
- decide whether it also supports `SnowballingProviderPort`

## If adding a dedup rule

You must:
- define its reason/provenance
- add tests for false positives and false negatives
- clarify whether it changes representative election
- ensure it composes with earlier rules

## If adding a graph feature

You must:
- state algorithmic complexity
- explain how it scales to 10k+ works
- ensure it does not mutate persistence projections directly
- add benchmark-oriented tests or at least complexity-aware fixtures

## If adding persistence

You must:
- justify whether it stores observations or decisions
- preserve provenance
- avoid denormalizing provider ownership into central work rows
- avoid storing raw provider payloads by default

## If unsure

Prefer:
- clearer domain names
- smaller interfaces
- immutable value objects
- explicit configuration
- pure serializers
- deterministic tests