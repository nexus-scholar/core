# Versioning Policy

Last updated: 2026-05-22

`nexus-scholar/core` follows semantic versioning after `1.0.0`. Before `1.0.0`, the package uses stable Composer tags for installability while keeping the public API in a pre-1.0 stabilization phase.

## Pre-1.0 Rules

- `0.x.0` releases may add, rename, or reshape public APIs when the change is needed to stabilize the package architecture.
- `0.x.y` patch releases should be limited to bug fixes, documentation fixes, packaging fixes, and low-risk compatibility adjustments.
- Breaking changes in `0.x.0` releases must be documented in the release notes.
- Host applications should pin with a compatible minor constraint, for example `^0.1`, until the package reaches `1.0.0`.
- Laravel host commands should remain thin wrappers around core use cases; workflow state and business rules belong in `core`.

## 1.0 Readiness Bar

Do not tag `1.0.0` until these are true:

- Search, persistence, screening, adjudication, comparison, full-text retrieval, citation-network, snowballing, and export use cases have stable command/DTO contracts.
- Database migrations needed by normal Laravel consumers are considered production-safe.
- Package-owned Artisan commands have stable options and output expectations.
- Provider configuration and credential behavior are documented for all built-in providers.
- Release notes cover upgrade paths from the latest `0.x` version.

## Dependency Policy

- Runtime dependencies should stay minimal and framework-light.
- Laravel-specific dependencies must stay in Laravel integration code or dev/test tooling.
- Graph functionality is provided through `nexus-scholar/graph-core` and `mbsoft31/graph-algorithms`.
- Live provider integrations must not be required for CI success unless backed by fixtures or explicit integration-test configuration.

