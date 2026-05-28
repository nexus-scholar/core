# Nexus Scholar Core

Nexus Scholar Core is a PHP and Laravel package for systematic literature review workflows. It provides reusable building blocks for academic search, corpus deduplication, locked review snapshots, screening, citation graphs, legal open-access full-text retrieval, and export audit trails.

The package is designed for Laravel applications that need scholarly workflow infrastructure without coupling their product code to provider clients, persistence details, or review-policy internals.

## Start Here

If you are new to the package, begin with the tutorial:

- [Build a Laravel Review CLI With Nexus Scholar Core](tutorials/build-a-laravel-review-cli-with-core.md)

The tutorial walks through the package the way a Laravel application usually adopts it: install the package, publish config and migrations, create host-owned Artisan commands, search into a project, lock the corpus, screen works, export a bibliography, and inspect audit history.

## What The Package Provides

`nexus-scholar/core` includes:

- scholarly provider search and YAML search plans,
- normalized scholarly work and corpus value objects,
- deduplication policies and representative selection,
- project corpus locking and immutable snapshots,
- LLM-assisted screening, human adjudication, and run comparison,
- citation graph construction, graph metrics, shortest paths, and snowballing,
- bibliography, network, and citation graph export,
- legal open-access full-text retrieval with fetch audit records,
- Laravel migrations, repositories, jobs, commands, events, listeners, and read ports.

The domain and application layers remain framework-light. Laravel-specific bindings live under the package integration layer.

## Documentation Map

Use the docs in this order:

1. [Tutorials](tutorials/README.md): practical host-application guides.
2. [Module Reference](modules/README.md): package behavior by bounded context.
3. [Release Status](release-state-audit.md): what shipped, what changed, and what remains outside v1.
4. [Publishing The Docs](publishing.md): how this documentation site is built and published.

## Package Boundary

Core owns reusable scholarly workflow behavior. A host application owns presentation, routes, console command names, local files, authentication, review team workflow, and product-specific policy.

When integrating the package, prefer:

- handlers for use cases,
- ports for host-facing contracts,
- domain value objects for typed input/output,
- read ports for audit and status inspection.

Avoid coupling host code to internal Eloquent models or implementation details unless the reference docs explicitly mark them as stable.