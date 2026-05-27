# Release Readiness

Last updated: 2026-05-27

This document defines the release gate for `nexus-scholar/core`. It is intentionally separate from feature roadmaps: a release can ship only when the package can be installed, validated, and reasoned about without local workspace assumptions.

## Current Status

Ready for the 1.0 stabilization branch:

- Composer metadata validates strictly.
- Composer scripts exist for tests, static analysis, and formatting checks.
- GitHub Actions runs the supported PHP/Laravel matrix.
- Laravel config and migrations are publishable through `nexus-config` and `nexus-migrations`.
- `.gitattributes` excludes local IDE, agent, storage, temporary, test, and legacy planning artifacts from package archives.
- Domain and application layers are guarded against framework leakage by architecture tests.
- Legal OA full-text retrieval avoids shadow-library adapters.
- Immutable locked corpus snapshots back final/citable corpus membership after lock.
- Host read APIs expose export history, job lifecycle, and full-text fetch audit records without direct SQL reads.
- Provider integration tests are fixture-backed through a test-only HTTP port and are guarded against live-capable clients in CI.
- Shared work/corpus models are canonical under `Nexus\Shared\Domain`; old Search-domain work/corpus classes are removed instead of retained as aliases.
- A clean Laravel consumer smoke has passed for the public package line.
- `v0.1.0`, `v0.1.1`, host API examples, and the 1.0 public API contract are documented.

1.0 boundary decision:

- `core` does not own HTTP routes or browser UI. Stable Laravel package handlers, ports, read APIs, migrations, config, and package-owned Artisan commands are the 1.0 surface.
- Host applications own HTTP/UI/CLI adapters and must keep them thin around `core`.

## Release Gate

Run these commands before tagging or opening a release PR:

```powershell
composer validate --strict
composer audit --format=plain
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

Before a release, test installation from a clean Laravel application:

```powershell
composer require nexus-scholar/core:^1.0
php artisan vendor:publish --tag=nexus-config
php artisan vendor:publish --tag=nexus-migrations
php artisan migrate
php artisan list nexus
```

Expected package-owned commands:

- `nexus:search`
- `nexus:screen`

Host applications may expose additional commands, but they must remain thin wrappers around core use cases.

### Last Public Consumer Smoke

Date: 2026-05-22

Clean app:

- `composer create-project laravel/laravel smoke-app`
- Laravel application skeleton `v13.7.0`
- Laravel framework `v13.11.2`

Stable install status:

```powershell
composer require nexus-scholar/core:^0.1
php artisan vendor:publish --tag=nexus-config --force
php artisan vendor:publish --tag=nexus-migrations --force
php artisan migrate
php artisan list nexus
```

Result: passed. Composer installed:

- `nexus-scholar/core` at `v0.1.0`
- `nexus-scholar/graph-core` at `v1.2.0`
- `nexus-scholar/graph-algorithms` at `v1.2.0`

Published Laravel resources:

- `nexus-config`
- `nexus-migrations`

Migration result: passed through `2026_04_28_000010_create_corpus_snapshots_table`.

Package-owned commands discovered:

- `nexus:search`
- `nexus:screen`

External Composer package smoke after graph package releases:

- `nexus-scholar/graph-core` resolves from Packagist at `v1.2.0`.
- `nexus-scholar/graph-algorithms` resolves from Packagist at `v1.2.0` and replaces `mbsoft31/graph-algorithms`.
- `nexus-scholar/core:dev-master` resolves from Packagist with the Nexus graph package names.
- Clean Composer resolution installed `nexus-scholar/graph-algorithms v1.2.0` and `nexus-scholar/graph-core v1.2.0` from public package archives.

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

### 1.0 Release Candidate Consumer Smoke

Run this from a clean Laravel host before tagging `1.0.0`:

```powershell
composer require nexus-scholar/core:^1.0
php artisan vendor:publish --tag=nexus-config --force
php artisan vendor:publish --tag=nexus-migrations --force
php artisan migrate
php artisan list nexus
composer audit --format=plain
```

Expected package-owned commands:

- `nexus:search`
- `nexus:screen`

`nexus-scholar/nexus-cli` should separately validate host-owned production commands for corpus lock, export, export history, job lifecycle, full-text artifacts, screening adjudication/comparison, and local workflow smoke tests.

#### Local Path-Repository RC Smoke

Date: 2026-05-27

Because the `1.0.0` tag does not exist yet, this smoke mapped the local package checkout to Composer version `1.0.0` with a path repository, then required it through the same `^1.0` constraint a real consumer will use.

Smoke app:

- Source skeleton: prior clean Laravel smoke app copied into `%TEMP%`.
- Laravel framework: `v13.11.2`.
- Core package: local `nexus-scholar/core` path repository advertised as `1.0.0`.
- Graph packages: local `nexus-scholar/graph-core` and `nexus-scholar/graph-algorithms` path repositories advertised as `1.2.0`.

Commands run:

```powershell
composer require nexus-scholar/core:^1.0 --with-dependencies --no-interaction --no-progress
php artisan vendor:publish --tag=nexus-config --force
php artisan vendor:publish --tag=nexus-migrations --force
php artisan migrate:fresh --force
php artisan list nexus
composer audit --format=plain
```

Result: passed.

Composer installed:

- `nexus-scholar/core` at `1.0.0`.
- `nexus-scholar/graph-core` at `1.2.0`.
- `nexus-scholar/graph-algorithms` at `1.2.0`.

Security-sensitive transitive versions in the smoke lock:

- `symfony/http-foundation` at `8.0.13`.
- `symfony/routing` at `8.0.13`.
- `symfony/polyfill-intl-idn` at `1.38.1`.

Migration result: `migrate:fresh` passed through `2026_04_28_000010_create_corpus_snapshots_table`.

Package-owned commands discovered:

- `nexus:search`
- `nexus:screen`

Audit result: passed with no security vulnerability advisories.

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

## 0.2.x Stabilization Scope

The `0.2.x` line is the host-read and CI-hardening release family:

- additive Laravel package read APIs for exports, job progress, and full-text fetch artifacts,
- host-facing usage examples for resolving handlers/readers,
- provider integration tests isolated from live networks,
- boundary hardening by moving shared work/corpus models to `Nexus\Shared\Domain`,
- optional package-owned concurrent provider search execution, with Guzzle promises confined to infrastructure.

Tagging/publishing remains a separate release task after a clean consumer install smoke.

## Stability Rules

- Keep domain and application code independent from Laravel, Eloquent, queues, storage, and facades.
- Add ports before infrastructure-specific dependencies.
- Keep package commands delegated to application services.
- Keep live network calls out of CI; use fakes or cassette-backed `HttpClientPort` fixtures.
- Add a regression test for every bug found through `nexus-cli` command output when the behavior belongs to `core`.
- Do not add shadow-library full-text sources.

## Next Release Hardening Items

Priority order:

1. Run a clean Laravel `^1.0` release-candidate consumer smoke once the tag candidate exists.
2. Run `nexus-scholar/nexus-cli` against the tag candidate and capture the 1.0 command smoke output.
3. Repeat Packagist/package archive review before the tag.
