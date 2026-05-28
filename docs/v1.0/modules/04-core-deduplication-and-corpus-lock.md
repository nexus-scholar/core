# Core Deduplication And Corpus Lock

## Purpose

The Deduplication context turns a raw corpus into representative work clusters and coordinates corpus lock state. It is the package boundary between exploratory corpus growth and citable, immutable corpus membership.

Deduplication answers two questions:

- Which work records refer to the same scholarly work?
- Which representative work should be used for downstream review, graph, retrieval, and export workflows?

Corpus locking answers a separate question:

- Is the project corpus still mutable, or has it been frozen as a snapshot for final workflows?

## v1 Status

Status: shipped in v1.0.0.

The v1 package includes:

- exact identifier duplicate policies,
- fuzzy title duplicate policy,
- duplicate evidence records,
- transitive cluster assembly with union-find,
- representative election by completeness and provider priority,
- work fusion,
- cluster persistence ports,
- lock and unlock handlers,
- corpus snapshot persistence,
- lock-policy enforcement across corpus-mutating workflows.

## What Shipped

### Duplicate Model

Duplicate evidence is represented by:

- `DuplicateReason`,
- `Duplicate`,
- `DedupClusterId`,
- `DedupCluster`,
- `DedupClusterCollection`.

`Duplicate` stores the involved work ids, reason, confidence, and metadata. High-confidence duplicates can be distinguished from lower-confidence matches, but cluster construction still preserves the evidence that caused each merge.

### Matching Policies

The default deduplication handler uses ordered policies:

- `DoiMatchPolicy` for exact DOI matches,
- `NamespaceMatchPolicy` for arXiv ids,
- `NamespaceMatchPolicy` for OpenAlex ids,
- `NamespaceMatchPolicy` for Semantic Scholar ids,
- `NamespaceMatchPolicy` for PubMed ids,
- `TitleFuzzyPolicy` for normalized title similarity.

`FingerprintPolicy` also exists as a policy implementation, but the default Laravel binding currently uses the explicit identifier and title policies above.

### Cluster Assembly

`DeduplicateCorpusHandler` initializes a union-find set over the corpus, applies each policy once, records evidence for duplicate pairs, and builds final clusters from connected components.

Important v1 behavior:

- empty corpus input returns an empty result,
- policies are resolved by alias when requested,
- direct evidence is preserved for pairs even when a cluster is formed transitively,
- evidence graph traversal lets absorbed works carry relevant evidence into the final cluster,
- representative election runs after cluster assembly,
- output includes the original corpus, cluster collection, representative corpus, duplicate evidence, and statistics.

### Representative Election

`CompletenessElectionPolicy` chooses the representative by work completeness and provider priority.

The default provider priority is:

| Provider | Priority |
| --- | ---: |
| `openalex` | 5 |
| `crossref` | 4 |
| `semantic_scholar` | 3 |
| `arxiv` | 2 |
| `pubmed` | 2 |
| `ieee` | 1 |
| `doaj` | 1 |

Tie breakers prefer DOI-backed records and earlier retrieved records.

### Corpus Locking

The shared `CorpusLockPolicy` is enforced by the deduplication handler and other corpus workflows. The Deduplication context owns lock and unlock application handlers:

- `LockCorpusHandler`
- `UnlockCorpusHandler`

The lock handler creates or updates project lock state, records corpus snapshot membership through the snapshot repository, and marks persisted clusters as locked for the project. Unlock reverses the project lock state and cluster lock flag through a transaction.

## Public API / Commands

The main application entry points are:

- `DeduplicateCorpusHandler`
- `LockCorpusHandler`
- `UnlockCorpusHandler`
- `DeduplicationPort`

The main policy and persistence ports are:

- `DeduplicationPolicyPort`
- `RepresentativeElectionPort`
- `ClusterRepositoryPort`
- `ProjectLockPort`
- `ProjectLockLifecyclePort`
- `ProjectWorkMembershipPort`
- `ProjectCorpusWorksPort`
- `CorpusSnapshotRepositoryPort`
- `TransactionPort`

The reusable API is handler and port based. Package consumers should not depend on internal Eloquent model details for clusters, membership, or snapshots.

## Data Model And Persistence

Deduplication and corpus locking persist through:

- `dedup_clusters`,
- `cluster_members`,
- `corpus_snapshots`,
- `corpus_snapshot_works`,
- project lock lifecycle columns on projects,
- locked flags on persisted clusters.

The live persistence path stores canonical cluster identity separately from member works. The snapshot repository stores immutable project membership at lock time so later export and read workflows can distinguish final/citable membership from a mutable working corpus.

## Main Workflows

### Deduplicate A Mutable Corpus

1. The handler checks that the project corpus can run the deduplication operation.
2. Policies produce duplicate evidence.
3. Union-find groups transitive duplicate components.
4. Clusters are built from grouped works.
5. Representatives are elected.
6. The result returns clusters, representatives, evidence, and statistics.

### Lock A Corpus

1. The lock handler runs in a transaction.
2. Project lock state is recorded through the lock lifecycle port.
3. Snapshot membership is persisted.
4. Existing project clusters are marked locked.
5. Downstream final workflows can now use locked membership guarantees.

### Unlock A Corpus

1. The unlock handler runs in a transaction.
2. Project lock state is cleared through the lock lifecycle port.
3. Project clusters are marked unlocked.
4. Corpus-mutating workflows can run again.

## Validation And Tests

Deduplication and lock behavior is covered by:

- exact DOI duplicate tests,
- namespace duplicate tests,
- fuzzy title duplicate tests,
- title normalizer tests,
- union-find tests,
- representative election tests,
- transitive evidence preservation tests,
- policy selection tests,
- lock and unlock handler tests,
- persistence tests for clusters, snapshots, and project lock state,
- job feature tests for queued deduplication.

Relevant test paths:

- `tests/Unit/Deduplication`
- `tests/Unit/Shared/CorpusLockPolicyTest.php`
- `tests/Feature/Persistence/DedupClusterRepositoryTest.php`
- `tests/Feature/Persistence/CorpusSnapshotRepositoryTest.php`
- `tests/Feature/Persistence/EloquentProjectLockTest.php`
- `tests/Feature/Laravel/DeduplicateCorpusJobTest.php`

## What Did Not Ship In v1

The Deduplication context does not ship:

- a product-facing manual merge editor,
- manual split and override persistence,
- a stable public contract for internal cluster Eloquent models,
- external search-index integration for duplicate review,
- a user-configurable policy registry persisted in the database.

The current extension point is code-level policy injection and port binding.

## Changed From Earlier Specs

- Cluster assembly is implemented with union-find and preserves transitive duplicate evidence.
- `DedupCluster` now carries project association for persisted project-level clusters.
- `DedupClusterCollection` exposes representative corpus conversion through `toCorpusSlice()`.
- Corpus locking and snapshot persistence are shipped, not just planned.
- The default Laravel binding sets title fuzzy threshold to 95.
- Title fuzzy matching runs after title normalization and uses the live normalizer plus string similarity implementation; earlier planned Unicode-specific algorithm details are not the public contract.

## Implementation References

- Code references:
  - `src/Deduplication`
  - `src/Shared/ValueObject/CorpusOperation.php`
  - `src/Shared/ValueObject/CorpusLockPolicy.php`
  - `src/Laravel/Persistence/Repository/EloquentDedupClusterRepository.php`
  - `src/Laravel/Persistence/EloquentCorpusSnapshotRepository.php`
  - `tests/Unit/Deduplication`
  - `tests/Feature/Persistence`