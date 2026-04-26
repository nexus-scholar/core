# Dissemination Module

## Purpose

This module handles all outputs and retrievable artifacts:
- bibliography serialization
- graph export serialization
- PDF/full-text retrieval
- storage abstraction

The old code separated `Export`, `Retrieval`, and `Visualization` into distinct folders. The redesign unifies them under the broader idea of dissemination while keeping submodules internally separated. [cite:14][cite:15][cite:16]

## Bibliography Export

Supported formats:
- BibTeX
- RIS
- CSV
- JSON
- JSONL [cite:4][cite:15]

Rules:
- serializers should depend on domain objects, not raw arrays
- serialization format choice must be explicit
- exports should be deterministic so snapshot tests are possible

## Graph Export

Supported formats:
- GEXF
- GraphML
- Cytoscape JSON [cite:4][cite:16]

Rules:
- graph serialization should optionally include metrics
- graph exports must preserve node IDs and edge semantics
- file naming and path storage belong to storage/application layers, not the pure serializer

## Full-Text Retrieval

The old package supported multiple full-text discovery sources such as arXiv, OpenAlex, Semantic Scholar, and direct links. That multi-source approach should be retained. [cite:4][cite:14]

Use a chain/composite strategy:
1. check if source supports the work
2. attempt fetch
3. if success, stop
4. if not found or failed, continue to next source

Every attempt must be recordable so the host app can audit which sources succeeded or failed. The database design’s `pdf_fetches` table is aligned with this. [Schema Review](old-nexus-review/nexus-php-database-schema.md)

## Storage

Use a `FileStoragePort` abstraction so the package can store files locally or in cloud/object storage. The domain should never know whether the file is on disk or S3.

## Artifact Persistence Philosophy

Exported files themselves do not need to be modeled heavily in the domain. The database review correctly recommends keeping exports mostly stateless and leaving actual file storage to disk/S3 instead of bloating relational tables. [Schema Review](old-nexus-review/nexus-php-database-schema.md)