# Architecture Rules

## Core Style

The package uses:
- Domain-Driven Design
- Hexagonal / Ports-and-Adapters architecture
- Test-Driven Development
- Optional Laravel integration
- Immutable value-object heavy modeling

## Absolute Rules

### 1. No framework leakage into the domain
`src/Shared`, `src/Search`, `src/Deduplication`, `src/CitationNetwork`, and `src/Dissemination` must not import `Illuminate\*` classes. [Code Review](old-nexus-review/nexus-php-code-review.md)

### 2. Domain code must not instantiate infrastructure directly
No `new GuzzleClient()`, `new Redis()`, `new CacheManager()`, or `new PDO()` inside domain or application services.

Everything external must arrive through ports.

### 3. Search providers must always rate-limit
The original code review found that the rate limiter existed but was never actually used by providers. That made the whole rate-limit configuration effectively a silent no-op. The redesign must make rate limiting impossible to forget by centralizing it in the base provider adapter or request pipeline. [Code Review](old-nexus-review/nexus-php-code-review.md)

### 4. Cache key generation belongs to the query object
The old package omitted dimensions such as language, maxResults, offset, and metadata from the search cache key. The redesign must place cache-key generation inside `SearchQuery` so that callers cannot accidentally construct partial keys. [Code Review](old-nexus-review/nexus-php-code-review.md)

### 5. No mutable singleton provider registry
The old Laravel search flow cleared and re-registered providers on a shared singleton, which is unsafe under concurrent execution. In the redesign, provider registration happens once at boot time, and per-request selection must use immutable views or query-time filtering, never shared mutation. [Code Review](old-nexus-review/nexus-php-code-review.md)

### 6. Raw provider payloads are opt-in only
The old code stored `rawData` on every work, which can lead to extreme memory growth in large multi-provider searches. The redesign must require an explicit query flag before retaining raw payloads. [Code Review](old-nexus-review/nexus-php-code-review.md)[Schema Review](old-nexus-review/nexus-php-database-schema.md)

### 7. No static domain utilities if behavior may vary
Title normalization, provider prioritization, and matching logic should be injectable policies, not static helper methods. The old code had static and hardcoded logic in places that should have been configurable. [Code Review](old-nexus-review/nexus-php-code-review.md)

### 8. Graph algorithms must be scalable
Co-citation and bibliographic coupling must use inverted indexes or similarly scalable approaches, not nested pairwise comparisons across the whole corpus. [Code Review](old-nexus-review/nexus-php-code-review.md)

### 9. SSL verification must use maintained CA resolution
Do not commit CA bundles into the repository. Use maintained runtime resolution such as `composer/ca-bundle`. The old package committed `cacert.pem`, which is stale-prone and bloats installs. [Code Review](old-nexus-review/nexus-php-code-review.md)

### 10. Single source of truth for normalization
DOI normalization must live in one place only. The old code normalized DOI values in multiple classes and methods, creating maintenance risk. [cite:7][Code Review](old-nexus-review/nexus-php-code-review.md)

## Layer Responsibilities

### Domain
Contains business concepts, invariants, and language. No transport logic, no framework logic, no persistence details.

### Application
Coordinates use cases. Calls ports. Orchestrates domain objects. May handle transactions through abstractions. No HTTP parsing, no SQL, no framework-specific event logic.

### Infrastructure
Implements ports:
- provider adapters
- storage
- serializers
- persistence
- rate limiting
- HTTP client wrappers

### Laravel
Bridges host app and package:
- service container bindings
- queue jobs
- Eloquent models and repositories
- Artisan commands
- published config
- event bridges