# Core Full Text And Dissemination

## Purpose

The Dissemination context produces review outputs and retrieves legal open-access full-text artifacts. It owns bibliography serialization, network serialization, citation graph export, export history, full-text source resolution, full-text artifact validation, file storage, and fetch audit records.

This context is the bridge from internal review state to durable artifacts such as `.bib`, `.ris`, `.csv`, `.json`, `.jsonl`, `.gexf`, `.graphml`, `.cyjs`, PDFs, XML, and extracted text sidecars.

## v1 Status

Status: shipped in v1.0.0.

The v1 package includes:

- bibliography serializers,
- network serializers,
- citation graph serializers,
- file storage ports,
- export history recording and reading,
- full-text source candidates,
- legal open-access full-text sources,
- PDF download and streaming ports,
- PDF/XML/text validation,
- deterministic safe storage paths,
- fetch audit records,
- fetch readers,
- cooldown for repeated source failures,
- locked corpus membership checks for final workflows.

## What Shipped

### Export Formats

Bibliography export supports:

- `bibtex`,
- `ris`,
- `csv`,
- `json`,
- `jsonl`.

Network export supports:

- `gexf`,
- `graphml`,
- `cytoscape`.

Citation graph export uses citation graph serializers and the same storage/history pattern as bibliography and network export.

### Export History

`ExportHistoryRecord` captures:

- export id,
- export type,
- project id,
- optional corpus slice id,
- optional citation graph id,
- requested format,
- file path,
- requested by,
- metadata.

When export runs against a locked corpus, lock metadata can be included so downstream consumers can distinguish draft outputs from citable snapshot outputs.

### Full-Text Retrieval

`RetrieveFullTextHandler` retrieves legal full-text artifacts from configured sources. It supports:

- direct PDF URLs,
- Unpaywall,
- PubMed Central,
- Europe PMC,
- arXiv,
- OpenAlex full-text locations,
- Semantic Scholar full-text locations.

The package explicitly leaves shadow-library sources disabled.

### Artifact Handling

Full-text retrieval supports:

- primary identifier extraction,
- stored success cache reuse,
- source candidate resolution,
- recent-failure cooldown,
- streamed PDF download when supported,
- non-streaming fallback download,
- retry behavior,
- max-size validation,
- PDF validation,
- XML validation,
- extracted text sidecar storage for XML,
- deterministic storage paths,
- success, failure, and skipped audit records.

## Public API / Commands

The main application entry points are:

- `ExportBibliographyHandler`
- `ExportNetworkHandler`
- `ExportCitationGraphHandler`
- `RetrieveFullTextHandler`

The main ports are:

- `BibliographySerializerPort`
- `NetworkSerializerPort`
- `CitationGraphSerializerPort`
- `FileStoragePort`
- `StreamingFileStoragePort`
- `ExportHistoryPort`
- `ExportHistoryReaderPort`
- `FullTextSourcePort`
- `FullTextCandidateSourcePort`
- `PdfDownloaderPort`
- `StreamingPdfDownloaderPort`
- `PdfFetchRepositoryPort`
- `FullTextFetchReaderPort`

The public extension points are serializers, sources, downloaders, storage, history, and fetch audit ports.

## Data Model And Persistence

Dissemination persistence uses:

- `pdf_fetches`,
- `export_histories`.

Export history records output files and metadata. Full-text fetch records preserve source, status, artifact type, stored path, metadata, failure reason, timestamps, and work identity.

File bytes are stored through storage ports. Database records store artifact metadata and references, not the artifact payload itself.

## Main Workflows

### Export A Bibliography

1. Handler validates requested format and filename extension.
2. If a project is locked, membership is checked.
3. Serializer converts works to the target format.
4. Storage writes the artifact.
5. Export history records the output.

### Export A Network

1. Handler validates network format and extension.
2. Serializer converts corpus network data.
3. Storage writes the artifact.
4. Export history records the output.

### Export A Citation Graph

1. Handler validates requested citation graph export format.
2. Citation graph serializer converts the persisted graph.
3. Storage writes the artifact.
4. Export history records the output with graph metadata.

### Retrieve Full Text

1. Handler extracts a primary identifier.
2. Locked project membership is checked when a project id is present.
3. Existing successful fetches can be reused when stored paths still exist.
4. Sources resolve candidates for the work.
5. Candidates are downloaded, streamed, validated, and stored.
6. Every success or failure is audited.
7. The handler returns success, failure, or skipped status.

## Validation And Tests

Full-text and dissemination behavior is covered by:

- bibliography serializer tests,
- network serializer tests,
- citation graph serializer tests,
- export handler tests,
- export history feature tests,
- full-text source tests,
- PDF/XML validation tests,
- deterministic storage path tests,
- cache reuse tests,
- retry and cooldown tests,
- oversized artifact tests,
- streaming tests,
- locked membership tests,
- full-text fetch reader tests,
- queued retrieval job tests.

Relevant test paths:

- `tests/Unit/Dissemination`
- `tests/Feature/Dissemination`
- `tests/Feature/Persistence/PdfFetchRepositoryTest.php`
- `tests/Feature/Persistence/HostReadApiTest.php`
- `tests/Feature/Laravel/RetrieveFullTextJobTest.php`

## What Did Not Ship In v1

The Dissemination context does not ship:

- browser visualization,
- proprietary full-text retrieval,
- shadow-library retrieval,
- a product-facing download manager,
- guaranteed successful full-text retrieval for every work,
- file storage details as a stable public API.

The stable public contract is the handlers, ports, export formats, fetch records, and reader ports.

## Changed From Earlier Specs

- Earlier dissemination plans described a smaller phase-one export scope; v1 ships export history, full-text retrieval, fetch auditing, and citation graph serialization.
- Export history is now a first-class read-side contract.
- Full-text retrieval supports multiple source adapters and streaming paths.
- XML artifacts and text sidecars are supported where source data provides XML.
- Legal source posture is explicit: shadow-library configuration remains disabled.
- Locked corpus metadata is included in final export workflows where available.

## Implementation References

- Code references:
  - `src/Dissemination`
  - `src/Laravel/Persistence/EloquentPdfFetchRepository.php`
  - `src/Laravel/Persistence/EloquentExportHistoryReader.php`
  - `src/Laravel/Persistence/EloquentFullTextFetchReader.php`
  - `tests/Unit/Dissemination`
  - `tests/Feature/Dissemination`
  - `tests/Feature/Persistence`