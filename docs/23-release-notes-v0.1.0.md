# Release Notes: v0.1.0

Release date: 2026-05-22

`v0.1.0` is the first installable Composer release of `nexus-scholar/core`. It is suitable for pre-1.0 Laravel host integration and real workflow testing. It is not a `1.0` API-freeze release.

## Highlights

- Multi-provider scholarly search with arXiv, Crossref, DOAJ, IEEE, OpenAlex, PubMed, and Semantic Scholar adapters.
- Persistent Laravel repositories for projects, works, external IDs, provider observations, queries, query-work links, dedup clusters, screening runs, full-text fetch audits, citation graphs, job lifecycle records, corpus snapshots, and export history.
- Corpus locking with immutable snapshots used as the membership authority for final/citable exports.
- Deduplication policies for DOI, external identifier, namespace, title fuzziness, and completeness-based representative selection.
- Scientific screening use cases for deterministic, LLM, council, and human adjudication workflows, including run comparison.
- Legal open-access full-text retrieval through direct URLs, Unpaywall, PMC OAI XML, Europe PMC, arXiv, OpenAlex metadata PDF URLs, and Semantic Scholar metadata PDF URLs.
- Citation, co-citation, and bibliographic-coupling graph builders with graph-package-backed metrics and graph exports.
- Queueable Laravel jobs and package-owned Artisan commands for reusable workflow entry points.

## Install

```bash
composer require nexus-scholar/core:^0.1
```

For Laravel hosts:

```bash
php artisan vendor:publish --tag=nexus-config
php artisan vendor:publish --tag=nexus-migrations
php artisan migrate
```

Minimum useful environment:

```dotenv
NEXUS_MAIL_TO=you@example.com
NEXUS_UNPAYWALL_EMAIL=you@example.com
```

## Runtime Dependencies

- PHP `^8.3`
- `nexus-scholar/graph-core ^1.0`
- `mbsoft31/graph-algorithms ^1.1`
- Guzzle, Composer CA bundle, and Symfony YAML

## Validation Evidence

- `composer validate --strict`
- `composer test`: 420 tests, 1514 assertions
- `composer analyse`
- `composer format:check`
- `git diff --check`
- `composer archive --format=zip --file=tmp/nexus-scholar-core`
- GitHub Actions matrix passed for PHP 8.3/Laravel 12, PHP 8.4/Laravel 12, and PHP 8.4/Laravel 13.
- Clean Composer consumer smoke installed `nexus-scholar/core:dev-master` at commit `94eb6b4`, `mbsoft31/graph-algorithms v1.1.0`, and `nexus-scholar/graph-core v1.2.0` from public package archives before tagging.

## Known Limits

- This is a pre-1.0 API. Host applications should pin to `^0.1` and review release notes before upgrading to later `0.x` releases.
- HTTP/API host surfaces are not included in this package yet.
- Live provider behavior depends on provider availability, credentials, and rate limits.
- Non-Laravel host adapters remain future work.

