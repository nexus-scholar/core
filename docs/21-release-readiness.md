# Release Readiness

Last updated: 2026-05-22

This document defines the release gate for `nexus-scholar/core`. It is intentionally separate from feature roadmaps: a release can ship only when the package can be installed, validated, and reasoned about without local workspace assumptions.

## Current Status

Ready for pre-release consumers:

- Composer metadata validates strictly.
- Composer scripts exist for tests, static analysis, and formatting checks.
- GitHub Actions runs the supported PHP/Laravel matrix.
- Laravel config and migrations are publishable through `nexus-config` and `nexus-migrations`.
- `.gitattributes` excludes local IDE, agent, storage, temporary, test, and legacy planning artifacts from package archives.
- Domain and application layers are guarded against framework leakage by architecture tests.
- Legal OA full-text retrieval avoids shadow-library adapters.
- Immutable locked corpus snapshots back final/citable corpus membership after lock.
- A clean Laravel consumer smoke passes when installing `nexus-scholar/core:dev-master`.
- `v0.1.0` release notes and a pre-1.0 versioning policy are documented.

Not ready for a stable `1.0` tag yet:

- `nexus-scholar/core` has no stable Packagist tag yet, so `composer require nexus-scholar/core` fails under default Laravel `minimum-stability=stable`.
- Host-facing HTTP/API surfaces are not part of this package yet.

## Release Gate

Run these commands before tagging or opening a release PR:

```powershell
composer validate --strict
composer test
composer analyse
composer format:check
git diff --check
composer archive --format=zip --file=tmp/nexus-scholar-core
```

The archive smoke check should succeed and produce a package zip that excludes:

- `.github`, `.idea`, `.cursor`, local agent files, and IDE files,
- `tests`, `vendor`, `storage`, `tmp`, and generated caches,
- old package review files and UI planning material,
- local query examples and lock files.

## Consumer Install Check

Before a stable release, test installation from a clean Laravel application:

```powershell
composer require nexus-scholar/core
php artisan vendor:publish --tag=nexus-config
php artisan vendor:publish --tag=nexus-migrations
php artisan migrate
php artisan list nexus
```

Expected package-owned commands:

- `nexus:search`
- `nexus:screen`

Host applications may expose additional commands, but they must remain thin wrappers around core use cases.

### Latest Consumer Smoke

Date: 2026-05-22

Clean app:

- `composer create-project laravel/laravel smoke-app`
- Laravel application skeleton `v13.7.0`
- Laravel framework `v13.11.2`

Stable install status:

```powershell
composer require nexus-scholar/core
```

Result: blocked until a stable core tag exists. Composer reports that no version matches the host app's default `minimum-stability=stable`.

Pre-release install status:

```powershell
composer require nexus-scholar/core:dev-master -W
php artisan vendor:publish --tag=nexus-config --force
php artisan vendor:publish --tag=nexus-migrations --force
php artisan migrate
php artisan list nexus
```

Result: passed. Composer installed:

- `nexus-scholar/core` at `dev-master` commit `77988a7`
- `nexus-scholar/graph-core` through the local path repository
- `mbsoft31/graph-algorithms` at `v1.0.0`

External Composer package smoke after graph package releases:

- `nexus-scholar/graph-core` resolves from Packagist at `v1.2.0`.
- `mbsoft31/graph-algorithms` resolves from Packagist at `v1.1.0` and now requires `nexus-scholar/graph-core`.
- `nexus-scholar/core:dev-master` resolves from Packagist at commit `94eb6b4`.
- Clean Composer resolution installed `mbsoft31/graph-algorithms v1.1.0` and `nexus-scholar/graph-core v1.2.0` from public package archives.

Runtime smoke:

```powershell
php artisan nexus:search "tomato instance segmentation" --providers=openalex --max=1 --project=smoke_install
```

Result: passed. OpenAlex returned and persisted one result.

Graph dependency smoke:

```powershell
php -r 'require "vendor/autoload.php"; /* build a two-node citation graph and compute metrics */'
```

Result: passed with `2:1`, confirming the installed graph packages can satisfy the core citation-network metrics path.

## Required Environment

Minimum useful Laravel values:

```dotenv
NEXUS_MAIL_TO=you@example.com
NEXUS_UNPAYWALL_EMAIL=you@example.com
```

Optional provider credentials:

```dotenv
NEXUS_IEEE_API_KEY=
NEXUS_S2_API_KEY=
NEXUS_PUBMED_API_KEY=
NEXUS_LLM_OPENROUTER_API_KEY=
```

Never commit real credentials. Provider availability should be controlled through config/env, not code edits.

## Stability Rules

- Keep domain and application code independent from Laravel, Eloquent, queues, storage, and facades.
- Add ports before infrastructure-specific dependencies.
- Keep package commands delegated to application services.
- Keep live network calls out of CI; use fakes or VCR fixtures.
- Add a regression test for every bug found through `nexus-cli` command output when the behavior belongs to `core`.
- Do not add shadow-library full-text sources.

## Next Release Hardening Items

Priority order:

1. Tag `v0.1.0`, then rerun `composer require nexus-scholar/core:^0.1` without `dev-master`.
2. Add host API examples for search, screening, adjudication, comparison, full-text, graph, and export flows.
3. Repeat Packagist/package archive review after the core tag is published.
