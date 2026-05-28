# v1.0 Release Status

Audit date: 2026-05-27

This page records the released `nexus-scholar/core` v1.0 surface. It distinguishes shipped package behavior from postponed work and intentionally changed design decisions.

## Evidence

The release status was checked against:

- the `core` `v1.0.0` GitHub release;
- the current `composer.json` package metadata;
- source and test scans across `src` and `tests`;
- Laravel service-provider bindings and package migrations;
- package command registration for `nexus:search` and `nexus:screen`;
- local release validation with Composer, Pest, PHPStan, Pint, and dependency audit checks.

## Released Surface

| Area | v1 status | Notes |
| --- | --- | --- |
| Package release | Shipped | `v1.0.0` is published from `master`. |
| Shared kernel | Shipped | Work IDs, work sets, authors, venues, corpus snapshots, project lock state, and job lifecycle values are in `Nexus\Shared`. |
| Search | Shipped for v1 | Provider adapters, provider selection, cache identity, rate limiting, YAML plan parsing, search execution, persistence, and package `nexus:search` are implemented. |
| Deduplication | Shipped for v1 | Exact-ID and fuzzy policies, representative election, cluster persistence, lock/unlock handlers, and provenance are implemented. |
| Screening | Shipped for v1 | Core owns reusable screening, comparison, human adjudication, LLM/council support, repositories, and package `nexus:screen`. |
| Citation network | Foundation shipped | Direct citation, co-citation, bibliographic coupling builders, graph package mapping, metrics, shortest paths, persistence, graph exports, and snowballing application flow exist. Some host-facing policy surfaces remain post-v1. |
| Full text and dissemination | Foundation shipped | Legal OA full-text sources, strict PDF/XML validation, audit persistence, bibliography export, graph export, and export history exist. |
| Laravel integration | Shipped for v1 | Config, migrations, service provider bindings, package commands, Eloquent repositories, jobs, events, lifecycle listener, and reader ports are present. |
| Host read APIs | Shipped | Export history, job lifecycle, and full-text artifact reader ports exist for application-facing inspection. |

## Not Included In v1

- Package-owned HTTP routes.
- Package-owned browser UI.
- Non-Laravel runtime adapter.
- Live provider network calls in CI.
- Shadow-library full-text sources.
- End-to-end fixture test covering search, screen, lock, adjudicate, compare, full text, graph, and export.
- Graph rebuild/recompute policy.
- Dedicated metric table policy if graph metric JSON grows too large.
- User-facing download endpoint/surface for stored exports and artifacts.
- Host authorization policy for unlock and other corpus lifecycle actions.

## Changed From Earlier Specs

| Earlier claim | v1 position |
| --- | --- |
| Older release-readiness notes described `1.0.0` as unreleased during the local RC smoke. | Superseded: `core v1.0.0` is published. |
| Earlier module checklists marked some graph, Laravel job, and graph export items unchecked. | Partly superseded by live code: package jobs call application services, citation graph exports use graph-core serializers, and graph builders use indexed grouping for co-citation and bibliographic coupling. |
| Legacy package docs described older modules and some behavior that was documented before it existed. | Changed intentionally: `core` is the reusable package, uses ports/adapters, and does not copy the legacy package structure mechanically. |
| UI-oriented specs presented a product surface. | Not shipped in package v1. Product UI remains outside the reusable package contract. |
| Earlier planning treated every Nexus workflow command as package-owned. | Changed boundary: `core` owns package commands `nexus:search` and `nexus:screen`; host applications own product-specific command workflows. |

## Reference Path

The v1 reference is organized in this order:

1. Core architecture and package boundary.
2. Core shared kernel.
3. Core search and providers.
4. Core deduplication and corpus lock.
5. Core screening and adjudication.
6. Core citation network and snowballing.
7. Core full text and dissemination/export.
8. Core Laravel integration, persistence, jobs, and read APIs.