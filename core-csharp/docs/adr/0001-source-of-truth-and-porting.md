# ADR 0001: Source of truth

Status: Accepted

## Decision

Use this order when sources disagree:

1. Versioned Nexus specifications.
2. Accepted architecture decisions.
3. Golden cross-language fixtures.
4. Observable behavior at the pinned PHP commit.
5. Current C# behavior.

The PHP repository is read-only during ordinary C# work. A porting task describes behavior and fixtures before implementation. Framework-specific Laravel and Eloquent details do not enter the C# domain.

An intentional incompatibility requires a separate ADR covering old behavior, new behavior, migration effect, and conformance expectations.
