# Documentation Scope

This page defines the v1.0 documentation boundary for `nexus-scholar/core`.

The published docs focus on package consumers integrating Core into Laravel applications. They describe reusable contracts, handlers, ports, persistence behavior, package commands, and known limitations.

## Package Coverage

The v1 docs are organized around one reader path: a Laravel application developer who wants to use Core as the scholarly workflow engine for a review product, internal tool, or command-line application.

The package pages stand on their own. They do not require a specific host application.

## Reference Order

| Order | Page | Package area |
| ---: | --- | --- |
| 01 | Core architecture and package boundary | `core` |
| 02 | Core shared kernel | `core` |
| 03 | Core search and providers | `core` |
| 04 | Core deduplication and corpus lock | `core` |
| 05 | Core screening and adjudication | `core` |
| 06 | Core citation network and snowballing | `core` |
| 07 | Core full text and dissemination/export | `core` |
| 08 | Core Laravel integration, persistence, jobs, and read APIs | `core` |

## Documentation Standard

Each reference page is written from current code, migrations, tests, package commands, and release behavior. Older design notes are treated as historical context, not as authority.

A complete module page should make these points explicit:

- purpose and package boundary;
- shipped v1 behavior;
- public APIs, commands, or ports;
- data model and persistence behavior;
- main workflows;
- validation and test coverage;
- behavior that did not ship in v1;
- changes from earlier specs.