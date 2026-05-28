# Nexus Scholar Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)
[![Tests](https://github.com/nexus-scholar/core/actions/workflows/test.yml/badge.svg)](https://github.com/nexus-scholar/core/actions/workflows/test.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)
[![License](https://img.shields.io/packagist/l/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)

Nexus Scholar Core is a PHP 8.3+ package for building systematic literature review workflows in Laravel applications. It provides reusable services for academic search, corpus normalization, deduplication, screening, citation-network analysis, legal open-access full-text retrieval, and export auditing.

The package is designed as a reusable engine. Domain and application code depend on ports and value objects; Laravel-specific infrastructure lives behind service-provider bindings, migrations, jobs, events, and Eloquent-backed repositories.

## What It Provides

- Search orchestration across arXiv, Crossref, DOAJ, IEEE, OpenAlex, PubMed, and Semantic Scholar.
- YAML search plans, provider selection, rate limits, cache identity, and persisted search provenance.
- Scholarly work normalization with stable internal identity and provider/external identifier tracking.
- Deduplication policies, representative selection, cluster persistence, and corpus locking.
- Deterministic, LLM-assisted, council, and human adjudication screening workflows.
- Screening run comparison for reviewer, criteria, and model-output analysis.
- Citation, co-citation, and bibliographic-coupling graph builders with metrics and exports.
- Legal open-access full-text retrieval through direct URLs, Unpaywall, PubMed Central, Europe PMC, arXiv, OpenAlex metadata, and Semantic Scholar metadata sources.
- Bibliography, graph, and network export history with host-facing read ports.
- Laravel package integration through config, migrations, repositories, commands, jobs, events, listeners, and reader APIs.

## Installation

Install the package with Composer:

```bash
composer require nexus-scholar/core
```

Publish the Laravel configuration and migrations:

```bash
php artisan vendor:publish --tag="nexus-config"
php artisan vendor:publish --tag="nexus-migrations"
php artisan migrate
```

Set the operational contact values used by retrieval and provider workflows:

```dotenv
NEXUS_MAIL_TO=you@example.com
NEXUS_UNPAYWALL_EMAIL=you@example.com
```

Provider API keys are optional unless a provider requires them. Keep real credentials in the host application's environment, not in source control.

## Package Commands

The package registers only reusable package-owned commands:

```bash
php artisan nexus:search --file=queries.yml --all --project=my-project
php artisan nexus:screen --project=my-project --include="tomato segmentation" --exclude="medical imaging"
```

Applications that need richer command-line workflows should wrap the package handlers and ports in their own console commands. Keep review policy and scholarly workflow behavior in `core`; let host applications own input parsing, presentation, local file layout, and project-specific conventions.

## Application Surface

Laravel applications consume the package through handlers, ports, value objects, published migrations, configuration, and reader APIs. The service provider binds the shipped repositories, provider clients, jobs, events, lifecycle listeners, and application services.

Common integration points include:

- search execution and search-plan services;
- deduplication and corpus lock/unlock handlers;
- screening, adjudication, and screening comparison handlers;
- full-text retrieval handlers;
- citation graph build, analysis, shortest path, snowballing, and export handlers;
- bibliography and network export handlers;
- export history, job lifecycle, and full-text fetch reader ports.

## Documentation

The v1 documentation lives in `docs/v1.0` and is published with MkDocs through GitHub Pages.

- [Documentation Home](docs/v1.0/README.md)
- [Tutorials](docs/v1.0/tutorials/README.md)
- [Module Reference](docs/v1.0/modules/README.md)
- [v1.0 Release Status](docs/v1.0/release-state-audit.md)
- [Publishing The Documentation](docs/v1.0/publishing.md)

## Quality Gates

Run the package checks from the repository root:

```bash
composer validate --strict
composer audit --format=plain --abandoned=ignore
composer test
composer analyse
composer format:check
```

The provider test lane is fixture-backed. CI must not depend on live provider network calls.

Build the documentation locally with:

```bash
python -m pip install -r requirements-docs.txt
mkdocs build --strict
```

## License

Nexus Scholar Core is open-sourced software licensed under the [MIT license](LICENSE.md).
