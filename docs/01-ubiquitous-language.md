# Ubiquitous Language

## Why this file exists

All contributors and agents must use the same words in code, tests, docs, and discussions. Domain language drift creates bad abstractions. The old package used technically generic names such as `Document`, which are understandable but weaker than the language researchers actually use. [cite:6][Code Review](old-nexus-review/nexus-php-code-review.md)

## Canonical Terms

### ScholarlyWork
A published or publishable academic artifact: paper, article, preprint, thesis, proceedings paper, etc.

Use this in the domain instead of `Document`, `Record`, `Item`, or `PaperRecord`.

### WorkId
A typed identifier for a `ScholarlyWork`. It always has:
- a namespace, such as DOI, arXiv, OpenAlex, Semantic Scholar, PubMed, IEEE, or DOAJ
- a normalized value

This replaces the old flat `ExternalIds` bag model with a more precise identity concept. The old package normalized DOI values but duplicated that normalization logic in multiple places, which is a maintenance hazard we must eliminate by design. [cite:7][Code Review](old-nexus-review/nexus-php-code-review.md)

### WorkIdSet
A collection of all known identifiers for a single `ScholarlyWork`. It knows how to:
- return a primary identifier
- check overlap with another set
- find IDs by namespace

### SearchQuery
A structured research question sent to academic providers. It includes search text plus dimensions such as year range, language, paging, result limits, and flags like `includeRawData`.

The old package had a cache bug because not all query dimensions were included in the cache key. In the redesign, `SearchQuery` owns authoritative cache-key generation. [Code Review](old-nexus-review/nexus-php-code-review.md)

### CorpusSlice
A bounded set of `ScholarlyWork` instances. It is the main collection type flowing through search, deduplication, and snowballing.

Examples:
- search result corpus
- screened corpus
- snowball-discovered corpus
- deduplicated corpus

### Duplicate
Two `ScholarlyWork` instances that refer to the same real-world publication.

### DedupCluster
A group of duplicates plus one elected representative. This is not just a list; it is an aggregate with rules and provenance.

### Representative
The canonical `ScholarlyWork` chosen from a `DedupCluster` to stand in for the group.

### CitationLink
A directed relation where one `ScholarlyWork` cites another.

### CitationGraph
A directed graph composed of works and citation links.

### CoCitationGraph
A graph where two works are connected because later works cite them together.

### BibliographicCouplingGraph
A graph where two works are connected because they share references.

### SnowballRound
One recursive expansion step over a corpus using references and/or citations.

### SnowballDepth
The maximum number of recursive snowball expansions allowed.

### FullText
The retrievable PDF or full text for a `ScholarlyWork`.

### Bibliography
A serialized representation of a corpus in a standard citation format such as BibTeX or RIS.

### NetworkExport
A serialized representation of a citation network for visualization tools like Gephi, yEd, NetworkX, or Cytoscape. [cite:4][cite:16]

## Forbidden or Discouraged Terms

Do not use these in the domain layer unless there is a very explicit technical reason:
- Document
- Item
- Data
- Record
- Model
- Object
- Raw document
- Payload

`Model` is allowed in the Laravel/Eloquent layer only, because Eloquent models are infrastructure. [Code Review](old-nexus-review/nexus-php-code-review.md)[Schema Review](old-nexus-review/nexus-php-database-schema.md)