# Nexus Scholar Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)
[![Tests](https://github.com/nexus-scholar/core/actions/workflows/test.yml/badge.svg)](https://github.com/nexus-scholar/core/actions/workflows/test.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)
[![License](https://img.shields.io/packagist/l/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)

A systematic literature review toolkit for PHP 8.3+. Nexus Scholar Core provides reusable search, deduplication, screening, full-text retrieval, citation-network, export, and Laravel persistence services behind framework-light application ports.

## Features

- **Multi-provider search:** arXiv, Crossref, DOAJ, IEEE, OpenAlex, PubMed, and Semantic Scholar adapters with provider selection, retries, rate limits, and cache-aware orchestration.
- **Persistence and provenance:** Laravel repositories for projects, works, external IDs, provider observations, search queries, query-work links, dedup clusters, screening runs, fetch audits, graph snapshots, jobs, and exports.
- **Screening:** deterministic, LLM, council, and human adjudication use cases with comparison between runs.
- **Corpus policy:** project lock checks prevent corpus mutation while allowing downstream review and reporting workflows.
- **Citation networks:** citation, co-citation, and bibliographic-coupling graph builders with graph-package-backed metrics and exports.
- **Legal open-access retrieval:** direct URLs, Unpaywall, PMC OAI XML, Europe PMC, arXiv, OpenAlex metadata PDF URLs, and Semantic Scholar metadata PDF URLs through one validation/audit pipeline.
- **Framework-light domain:** domain and application layers depend on ports; Laravel-specific code lives under `src/Laravel`.

## Installation

You can install the package via composer:

```bash
composer require nexus-scholar/core
```

For Laravel usage, publish the configuration and migrations:

```bash
php artisan vendor:publish --tag="nexus-config"
php artisan vendor:publish --tag="nexus-migrations"
php artisan migrate
```

Set the minimum operational environment values in your host application:

```dotenv
NEXUS_MAIL_TO=you@example.com
NEXUS_UNPAYWALL_EMAIL=you@example.com
```

Provider keys are optional unless the provider requires them. Do not commit real keys.

## Laravel Commands

The package registers only the package-owned commands that delegate to reusable application services:

```powershell
php artisan nexus:search --file=queries.yml --all --project=my-project
php artisan nexus:screen --project=my-project --include="tomato segmentation" --exclude="medical imaging"
```

Host applications such as `nexus-scholar/nexus-cli` may add additional Artisan commands for run files, local wiki workflows, adjudication file parsing, and presentation of results. Keep business rules in `core`; host commands should parse input and format output.

## Core Use Cases

Application handlers are available for:

- search orchestration and persistent search runs,
- deduplication,
- screening single works or corpora,
- human adjudication,
- screening run comparison,
- full-text retrieval,
- citation graph build/analyze/shortest-path workflows,
- snowballing,
- bibliography and graph exports.

Laravel binds these handlers through `Nexus\Laravel\NexusServiceProvider`.

## Documentation

Start here:

- [Product Vision](docs/00-product-vision.md)
- [Architecture Rules](docs/03-architecture-rules.md)
- [Prioritized Implementation Plan](docs/17-prioritized-implementation-plan.md)
- [Test Strategy Plan](docs/18-test-strategy-plan.md)
- [Scientific Screening Architecture](docs/20-scientific-screening-architecture.md)
- [Release Readiness](docs/21-release-readiness.md)
- [Versioning Policy](docs/22-versioning-policy.md)
- [Release Notes v0.1.0](docs/23-release-notes-v0.1.0.md)
- [Release Notes v0.1.1](docs/24-release-notes-v0.1.1.md)
- [Host API Examples](docs/25-host-api-examples.md)

## Testing

To run the Pest suite included with the core, use the Composer scripts. Provider integration tests replay fixture cassettes through a test-only HTTP port and do not reach live provider networks in CI.

```bash
composer test
composer test:unit
composer test:feature
```

Release checks:

```bash
composer validate --strict
composer analyse
composer format:check
composer archive --format=zip --file=tmp/nexus-scholar-core
```

`composer archive` is an archive-smoke command, not a build artifact requirement. The archive should exclude local agent files, IDE files, tests, storage, temporary files, and old/UI planning material through `.gitattributes`.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
