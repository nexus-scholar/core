# Laravel Integration

## Purpose

Laravel integration is a glue layer, not a business-logic layer.

The original package’s Laravel directory included service provider, config, jobs, events/listeners, commands, and search wrappers. The review highlighted that Laravel isolation was one of the package’s best design choices and should be preserved. [Code Review](old-nexus-review/nexus-php-code-review.md)[cite:17]

## Responsibilities

Laravel owns:
- service container bindings
- config publishing
- job dispatch
- Eloquent persistence adapters
- Artisan commands
- host-app specific wiring
- agent/tool wrappers

Laravel does **not** own:
- provider mapping logic
- dedup rules
- graph invariants
- full-text strategy rules
- domain normalization rules

## Service Provider Rules

- register all providers once
- expose application services through container bindings
- never mutate a shared provider registry per request

This directly prevents the old concurrency bug caused by clearing and re-registering providers on a singleton. [Code Review](old-nexus-review/nexus-php-code-review.md)

## Jobs

Potential jobs:
- search job
- dedup job
- snowball job
- graph build job
- PDF retrieval job
- export job

Jobs should call application services, not re-implement business logic.

## Events

Use Laravel events/listeners to persist projections or trigger asynchronous work from domain/application outputs.

Do not use Laravel events as a replacement for domain events. They solve different problems.

## Eloquent Models

Eloquent models are infrastructure projections:
- project
- search query
- provider progress row
- scholarly work row
- external IDs row
- provider sightings row
- authors and work-authors
- dedup clusters and members
- screening decisions
- PDF fetch attempts
- citation graphs and edges

The domain may never import these models.

## AI Tools and Agents

The original package advertised Laravel-ready AI agents and tools. Those remain optional adapters. They should wrap use cases such as:
- literature search
- citation analysis
- snowballing
- PDF retrieval

They must not contain domain logic themselves. [cite:4]