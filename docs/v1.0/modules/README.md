# Module Reference

The module reference documents the released v1.0 behavior by bounded context. Use these pages when you need the exact package boundary, shipped APIs, persistence model, validation coverage, and known limitations.

## Core Package

- [Architecture and package boundary](01-core-architecture-and-package-boundary.md)
- [Shared kernel](02-core-shared-kernel.md)
- [Search and providers](03-core-search-and-providers.md)
- [Deduplication and corpus lock](04-core-deduplication-and-corpus-lock.md)
- [Screening and adjudication](05-core-screening-and-adjudication.md)
- [Citation network and snowballing](06-core-citation-network-and-snowballing.md)
- [Full text and dissemination](07-core-full-text-and-dissemination.md)
- [Laravel integration, persistence, jobs, and read APIs](08-core-laravel-integration-persistence-jobs-read-apis.md)

## Reading Guidance

Start with the architecture page if you are integrating the package for the first time. Read the individual module pages when you need implementation details for a specific workflow.

These pages describe the reusable package without depending on any specific host application. Host applications should compose core handlers, ports, and read APIs behind their own presentation layer.