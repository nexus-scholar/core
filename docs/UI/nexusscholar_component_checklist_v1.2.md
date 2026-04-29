# NexusScholar — Developer-Ready Component Checklist

**Derived from:** `nexusscholar_v1.1_prd.md`
**Checklist version:** 1.2 (audit corrections applied)  
**Purpose:** Engineering task breakdown for implementation tracking  
**Audience:** Backend, frontend, QA, product, design  
**Status:** Ready for sprint decomposition

---

## How to use this checklist

- Each item is written as an implementation unit, not a design note.
- Use this as the source for tickets, epics, acceptance tests, and QA pass/fail checks.
- If any ticket conflicts with this checklist, defer to `nexusscholar_v1.1_prd.md` as the canonical source. [file:3]

---

## Phase 0 infrastructure

### Data model

- [ ] Create `projects` table with lifecycle fields: `status`, `locked_at`, `archived_at`, `owner_user_id`, `template_type`.
- [ ] Create `search_runs` table with run state fields: `queued`, `running`, `partial`, `completed`, `failed`, `cancelled`.
- [ ] Create `provider_progress` table with per-provider status, result count, latency, error message, rate-limit flags.
- [ ] Create `works` table for normalized representative records.
- [ ] Create `dedup_clusters` table or equivalent structure storing raw source records per Work.
- [ ] Create immutable corpus snapshot model tied to lock event.
- [ ] Create `screening_decisions` table keyed to locked snapshot work IDs.
- [ ] Create `conflict_records` table for AI disagreement routing and later human adjudication.
- [ ] Create `saved_queries` table with `owner_user_id`, `source_query_id`, `project_context`, `yaml`, `tags`, `last_used_at`.
- [ ] Create `export_history` table with format, scope, record count, filename, actor, timestamp.
- [ ] Create `audit_log` table with typed event payloads.
- [ ] Create `notification_events` table for run completion, provider failure, conflict alerts.

### Roles and permissions

- [ ] Implement `Owner`, `Reviewer`, `Observer` role model.
- [ ] Enforce Owner-only permissions for lock, archive, provider severity settings, and team management.
- [ ] Enforce Reviewer visibility to own decisions only in audit log.
- [ ] Enforce Observer no-access rule for audit log.

### Jobs and pipelines

- [ ] Implement background run queue worker.
- [ ] Implement per-provider adapter orchestration for OpenAlex, Semantic Scholar, PubMed, IEEE, arXiv, DOAJ, Crossref.
- [ ] Implement deduplication pipeline with ID-first matching.
- [ ] Implement representative record election.
- [ ] Implement `completenessScore` calculation and the 6-field micro-grid source payload.
- [ ] Implement `WorkIdSet` identity confidence classification: High / Medium / Low.
- [ ] Implement immutable lock snapshot creation.
- [ ] Implement export job pipeline for BibTeX, RIS, CSV, Excel, JSON.

### Phase 0 Definition of Done

- [ ] All database tables exist, migrations pass, and schema matches the data model spec.
- [ ] Auth and role model enforces Owner/Reviewer/Observer permission rules for at least one protected endpoint.
- [ ] Background run queue accepts a job and processes a stub provider adapter to completion.
- [ ] Dedup pipeline accepts two raw records with the same DOI and emits a single deduplicated Work.
- [ ] `completenessScore` calculation returns a correct 6-field payload for a test record.


---

## Cross-cutting rules

### State handling

- [ ] Every data-bearing component implements: loading, error, empty, partial, gated, default states.
- [ ] No component may surface a raw backend error string without mapped UI copy.
- [ ] No gated feature may render as broken; all gated features render as intentional locked state.

### Auditability

- [ ] Lock actions are fully logged with actor, timestamp, run set, corpus counts.
- [ ] AI recommendations are logged even when overridden by a human.
- [ ] Conflict resolution preserves original AI decisions and final human decision.
- [ ] Export actions are logged to `export_history` and `audit_log`.
- [ ] PRISMA override schema exists even if UI is gated to v2.

### Accessibility and interaction

- [ ] Keyboard navigation works on all desktop screening actions.
- [ ] Mobile screening avoids horizontal swipe navigation conflicts.
- [ ] All irreversible actions use typed confirmation.
- [ ] Focus trapping is implemented in modals and drawers.

---

## Global app shell

### Sidebar and navigation

- [ ] Render top-level sidebar items: Dashboard, Projects, Query Library, Notifications, Settings, Help & Docs.
- [ ] Render active project subtree: Overview, Search Runs, Results, Screening, Citation Network, Export.
- [ ] Remove Query Library from project subtree.
- [ ] Add access control and gating for Results and Screening nav items.
- [ ] Add tooltip copy for locked nav items.

### Notifications

- [ ] Build notifications list for run completion, failure, and conflict alerts.
- [ ] Add click-through routing to Run Detail and Conflict Queue.

---

## Project surfaces

### `ProjectCard`

- [ ] Render project name, review question, status badge, work count, last activity.
- [ ] Add hover quick-stats tooltip.
- [ ] Add archived visual state.
- [ ] Add loading skeleton and global empty state for no projects.

### `ProjectWizard`

- [ ] Implement 3-step wizard: Basics → Template → Team invite.
- [ ] Add template presets: PRISMA, Cochrane, Scoping, Rapid, Custom.
- [ ] Pre-populate exclusion reasons and export defaults from template.
- [ ] Add required field validation and submit loading/error states.

### `LifecycleStatusBar`

- [ ] Implement 6 stages: Draft, Active Search, Corpus Locked, Screening, Reporting, Archived.
- [ ] Render completed/current/upcoming/blocked states.
- [ ] Add hover tooltip for future stages.
- [ ] Add persistent chip fallback for post-lock banner replacement: `ℹ Corpus locked — view details`.

### Lifecycle banner system

- [ ] Implement the 8-row lifecycle banner state machine below the `LifecycleStatusBar`.
- [ ] Render banner severity variants: info, success, warning, error.
- [ ] Show amber banner for soft provider failure.
- [ ] Show red banner for hard provider failure or required-provider failure.
- [ ] Hide Lock action while any run is active.
- [ ] Make banner dismissibility match spec by state.

### Project surfaces Definition of Done

- [ ] A user can create a project via the wizard, land on the Overview page, and see the correct lifecycle stage in the `LifecycleStatusBar`.
- [ ] The lifecycle banner renders the correct severity and message for at least: no-runs, run-in-progress, and run-completed-all-ok states.
- [ ] Lock button is absent while a run is in progress.


---

## Query system

### `QueryBuilderForm`

- [ ] Render keyword builder with AND/OR/NOT controls.
- [ ] Render title-only/full-text toggle.
- [ ] Render year range controls.
- [ ] Render max-results-per-provider control.
- [ ] Render provider selector.
- [ ] Add `Load from library` button that opens Saved Query Library drawer.
- [ ] Validate at least one provider selected.
- [ ] Validate year range and result count.

### YAML sync model

- [ ] Implement `synced` state.
- [ ] Implement `manual-edit` state with read-only visual form.
- [ ] Implement `parse-error` state with line/column error surfacing and save disabled.
- [ ] Implement `diverged` state for unsupported advanced flags.
- [ ] Preserve exact YAML submitted per run for reproducibility.

### `SavedQueryLibrary`

- [ ] Implement top-level Query Library page.
- [ ] Implement saved query drawer picker from QueryBuilder.
- [ ] Display query name, last used timestamp, `Used in N projects`, source badge.
- [ ] Ensure `Used in N projects` is derived from distinct `project_context`, not executions.
- [ ] Implement original/forked lineage using `source_query_id`.
- [ ] Prevent automatic sharing across team members.
- [ ] Add search/filter by name and tag.
- [ ] Auto-close library drawer on query selection; form populates and transitions to `synced` state.
- [ ] Show warning dialog when loading a library query while YAML sync state is `manual-edit`: "Loading a saved query will discard your current YAML edits. Continue?"
- [ ] Block library load entirely when YAML sync state is `parse-error`; show inline message: "Resolve the YAML error before loading a saved query."
- [ ] Add empty, loading, and populated states.

### Query system Definition of Done

- [ ] A user can build a query via the form, save it to the library, and reload it into a new form session via the drawer.
- [ ] The YAML editor transitions correctly through synced → manual-edit → parse-error states.
- [ ] Loading a saved query while in `manual-edit` state triggers the discard warning.
- [ ] `Used in N projects` count increments only when a query is loaded into a distinct project.


---

## Search run system

### `RunStatusCard`

- [ ] Render run metadata, status badge, timestamps, raw results, unique works, dedup savings.
- [ ] Render `queued`, `running`, `partial`, `completed`, `failed`, `cancelled` states.
- [ ] Enable `View Results` only for `completed` and valid `partial` states.
- [ ] Enable `Retry failed providers` on failed/partial runs where applicable.

### Provider failure rules

- [ ] Implement soft failure rule: single provider failure does not fail run.
- [ ] Implement hard failure rule: ≥4 of 7 providers fail OR single selected provider fails.
- [ ] Implement lifecycle banner severity thresholds: soft gate ≥1 failure, hard gate ≥3 failures or required-provider failure.
- [ ] Ensure lock flow acknowledgement appears when latest run was partial or failed.

### `ProviderProgressTable`

- [ ] Render one row per provider with logo, status, records, latency, error.
- [ ] Implement pending, success, soft fail, rate-limited, not-configured row states.
- [ ] Implement initial loading with 7 shimmer skeleton rows.
- [ ] Implement row-by-row fade-in as provider results resolve.
- [ ] Show trust timestamp: `Last updated: N seconds ago · Auto-refreshing every 10s` (default 10s; configurable as an infrastructure setting, not a per-user preference).
- [ ] Add `Refresh now` manual action.
- [ ] Freeze timestamp to completion time when run reaches terminal state.
- [ ] Add inline `Retry this provider` action.

### Search run system Definition of Done

- [ ] A submitted run reaches `completed` state; provider progress rows are visible with correct statuses.
- [ ] Poll trust timestamp refreshes every 10s during an active run and freezes on completion.
- [ ] Skeleton rows appear before any provider data arrives and resolve row-by-row.
- [ ] Run YAML is stored immutably and readable post-lock.


---

## Results browser

### `CorpusStatsBar`

- [ ] Render total raw records, unique works, dedup savings %, provider breakdown, year distribution.
- [ ] Add locked badge post-lock.
- [ ] Ensure stats reflect locked snapshot after lock.

### `CorpusFilterSidebar`

- [ ] Implement provider filters.
- [ ] Implement year range filter.
- [ ] Implement open-access filter.
- [ ] Implement has-abstract filter.
- [ ] Implement completeness filter.
- [ ] Surface active filter chips above results list.

### `WorkCard`

- [ ] Render title, authors, venue, year, abstract preview, source count.
- [ ] Replace old signal-strength widget with 6-square field-completeness micro-grid.
- [ ] Use fields: DOI, ABS, AUTH, VEN, CIT, ORCID.
- [ ] Render green for present, amber for missing.
- [ ] Collapse to `[N/6]` pill via container query at width < 260px.
- [ ] Add `WorkIdSet` confidence pill: High / Medium / Low.
- [ ] Add tooltip on `WorkIdSet` confidence pill explaining dedup match basis: "Match basis: [DOI / arXiv ID / Title heuristic]."
- [ ] Add per-square tooltip on each of the 6 micro-grid cells: "[FieldName]: Present" or "[FieldName]: Missing".
- [ ] For DOI and ABS cells when missing, extend tooltip: "[Field]: Missing — impacts screening quality".
- [ ] Render included/excluded/maybe/conflict card variants.
- [ ] Add loading skeleton.

### `WorkDetailDrawer`

- [ ] Render full normalized metadata.
- [ ] Add 4-chip `WorkIdSet` row: DOI, PMID, arXiv ID, S2 ID.
- [ ] Render present chips as active with copy/link actions.
- [ ] Render absent chips as greyed with tooltip.
- [ ] Add abstract section with expand/collapse.
- [ ] Render no-abstract and no-DOI fallbacks.
- [ ] Add screening actions in drawer only when screening is active.

### `SourcesPanel`

- [ ] Keep panel collapsed by default.
- [ ] Add top-row 4-chip identity key row.
- [ ] Show provider/raw title/IDs/completeness table.
- [ ] Highlight representative source row.
- [ ] Show tie-break rule when representative election is tied.

### Results browser Definition of Done

- [ ] Deduplicated Works are visible in the Results browser after a run completes.
- [ ] `WorkCard` renders the 6-square micro-grid with correct amber/green per field; collapses at < 260px.
- [ ] `WorkIdSet` confidence pill renders correctly for High/Medium/Low match basis.
- [ ] `WorkDetailDrawer` 4-chip identity row matches the pill confidence on the same Work.
- [ ] `SourcesPanel` expanded state shows the identity key row before the provider table.


---

## Lock flow

### `LockCorpusModal`

- [ ] Render corpus summary and dedup counts.
- [ ] Render delta-since-last-run block when >1 run exists.
- [ ] Render partial/failed-run warning block when applicable.
- [ ] Require acknowledgement checkbox when latest run had failures.
- [ ] Require exact typed project-name confirmation.
- [ ] Disable confirm until all conditions pass.
- [ ] Log successful lock event to audit log.
- [ ] Expose `corpus_locked` boolean signal via project state API — **required by Export Builder** for pre-lock banner rendering. This is a cross-team dependency: Lock flow workstream owns the signal; Export workstream consumes it.

### Post-lock behavior

- [ ] Disable `New Run` action after lock.
- [ ] Keep Search Runs readable post-lock.
- [ ] Keep Results fully accessible post-lock.
- [ ] Keep SourcesPanel, dedup audit, and run YAML accessible post-lock.
- [ ] Unlock Screening nav after lock.

### Post-lock banner machine

- [ ] Show banner immediately after lock.
- [ ] Make banner non-dismissible for first 1 hour.
- [ ] Add dismiss control after 1 hour.
- [ ] Replace dismissed banner with permanent lifecycle chip.
- [ ] Auto-hide banner after 48 hours and leave chip in place.

### Lock flow Definition of Done

- [ ] Lock action is unavailable while any run is active.
- [ ] `LockCorpusModal` requires typed project-name confirmation before enabling confirm.
- [ ] Acknowledgement checkbox appears and blocks confirm when latest run had failures.
- [ ] Post-lock banner is non-dismissible for 1 hour; dismiss control appears after 1 hour.
- [ ] `corpus_locked` signal is available in project state API after successful lock.


---

## Screening system

### `ScreeningModeSelector`

- [ ] Implement Solo mode.
- [ ] Implement AI-Recommendation mode.
- [ ] Render Collaborative mode as disabled with `Coming in v2` label.
- [ ] Allow configuration of up to 3 AI models.
- [ ] Disable conflict logic when only 1 AI model configured.

### AI recommendation rules

- [ ] Treat AI output as advisory only.
- [ ] Persist recommendation, confidence, and reasoning snippet.
- [ ] Require explicit human decision even when models agree.
- [ ] Preserve original AI recommendation on override.

### `ScreeningWorkspace`

- [ ] Render top bar with project, stage, progress, mode badge, keyboard help.
- [ ] Render content area with title, metadata, abstract, DOI links, `Show full cluster` toggle.
- [ ] Render fixed decision bar with Include / Exclude / Maybe / Undo / Skip.
- [ ] Add keyboard shortcuts: I, E, M, U, S, J, K, 1–8, Esc, ?.
- [ ] Show AI recommendation chip and confidence in AI mode.
- [ ] Show collapsible reasoning block when available.
- [ ] Add completion screen when queue is exhausted.
- [ ] Add warning state if all works excluded.

### `ExclusionReasonPicker`

- [ ] Render on Exclude action only.
- [ ] Enforce mandatory reasons for PRISMA, Cochrane, Scoping templates.
- [ ] Leave optional for Rapid and Custom templates.
- [ ] Support numbered keyboard selection 1–8.
- [ ] Support `Other` free-text reason.

### Mobile screening

- [ ] Implement full-width thumb-zone buttons.
- [ ] Implement Swipe Up from bottom third = Include.
- [ ] Implement Swipe Down from top third = Exclude.
- [ ] Implement Long press = Maybe.
- [ ] Do not implement horizontal swipe gestures.
- [ ] Add first-session bottom sheet explaining gestures.
- [ ] Add one-time fullscreen prompt using `document.requestFullscreen()`.

### `ScreeningProgressBar`

- [ ] Render total, screened, included, excluded, maybe, remaining.
- [ ] Exclude conflict-routed works from primary denominator.
- [ ] Render separate conflict count badge.
- [ ] Add filtering by decision class.
- [ ] Hide conflict count badge entirely in solo mode — do not show 0, do not render the slot.
- [ ] Show conflict count badge only when AI-Recommendation or Collaborative mode is active.

### `ConflictQueue`

- [ ] Build durable conflict queue surface separate from main queue.
- [ ] Render model-by-model recommendation comparison.
- [ ] Support soft conflict (2/3 agree) and hard conflict (all differ).
- [ ] Allow human final decision from conflict screen.
- [ ] Preserve all original model decisions in audit log.
- [ ] Move resolved records to resolved section.
- [ ] Implement Layout B (reasoning absent): render "No reasoning provided" placeholder in muted italic per model column when no chain-of-thought is returned.
- [ ] Show full abstract below vote columns when no reasoning is available.
- [ ] Render "—" in confidence slot when model returns no confidence score; add tooltip: "This model did not return a confidence score."
- [ ] Render empty state when no conflicts exist.

### Screening completion rules

- [ ] Stage is complete only when main queue is 100% decided **and** conflict queue is 100% resolved.
- [ ] Do not mark stage complete when only the main queue is done.
- [ ] Unlock Stage 2 only when Stage 1 has recorded at least one valid decision and corpus is locked.

### Screening system Definition of Done

- [ ] A locked project exposes the Screening nav item; unlocked projects do not.
- [ ] Desktop screening records Include/Exclude/Maybe decisions with keyboard shortcuts I/E/M.
- [ ] Mobile screening: Swipe Up = Include, Swipe Down = Exclude, Long press = Maybe; no horizontal swipes.
- [ ] Conflict queue routes AI-conflicted works and requires human resolution before stage completion.
- [ ] Stage is not marked complete until both main queue and conflict queue reach 100%.
- [ ] Conflict badge is absent from `ScreeningProgressBar` in solo mode.


---

## Export system

### `ExportBuilder`

- [ ] Build Step 1 scope selector: All / Screened-in Stage 1 / Screened-in Stage 2 / Custom.
- [ ] Build Step 2 format card grid.
- [ ] Build Step 3 options panel.
- [ ] Support multi-select formats.
- [ ] Render live record count as scope changes.

### Live formats (v1)

- [ ] BibTeX export.
- [ ] RIS export.
- [ ] CSV export.
- [ ] Excel export.
- [ ] JSON export with AI-oriented normalized fields.

### Gated formats

- [ ] Render Annotated Bibliography as gated card.
- [ ] Render PRISMA Flowchart as gated card.
- [ ] Use plain-language tooltip for standard users: `This export format is in development and will unlock automatically when ready. No action needed.`
- [ ] Use technical tooltip only for Admin / Owner role.
- [ ] Never show gated export in error state.

### Export preview and history

- [ ] Render syntax-highlighted preview for BibTeX/RIS/JSON.
- [ ] Render mini table preview for CSV.
- [ ] Add preview tabs when multiple formats selected.
- [ ] Build Export History list with re-download action.
- [ ] Add `Duplicate settings` action.

### Preliminary export state

- [ ] Support pre-lock preliminary exports.
- [ ] Show persistent `This corpus is not yet locked. This export is not citable.` banner in pre-lock state. **Depends on `corpus_locked` signal from Lock flow workstream** (see Lock section).

### Export system Definition of Done

- [ ] All 5 live formats (BibTeX, RIS, CSV, Excel, JSON) generate valid output for a locked corpus.
- [ ] Pre-lock export shows the non-citable banner; post-lock export does not.
- [ ] Gated format cards render as intentionally locked, not as broken or errored.
- [ ] Export history records a downloadable entry per generated export.


---

## Citation network placeholder

### `CitationNetworkPlaceholder`

- [ ] Render substantive placeholder, not bare `Coming Soon` text.
- [ ] Add ghost wireframe of node-edge graph.
- [ ] Explain that citation relationship data will surface here when ready.
- [ ] Add `Notify me when this is ready` toggle.
- [ ] Show citation data count only when `CitationGraphRepository` returns a non-zero value.
- [ ] Hide the count element entirely when no data is available — do not render zero or a loading spinner in its place.

---

## Settings surfaces

### `ProviderAPIKeyManager`

- [ ] Render provider key status: set / missing / invalid.
- [ ] Support set / rotate / revoke.
- [ ] Support test connection action.
- [ ] Allow Owner to mark provider as `required` for hard-failure lifecycle gating.

### `TeamMemberList`

- [ ] Render member list, role, email, last active.
- [ ] Support invite, role change, removal.
- [ ] Restrict management actions to Owner.

### `BillingPlanCard`

- [ ] Render current plan tier.
- [ ] Render usage against limits.
- [ ] Render upgrade CTA and billing history link.

---

## Audit and records

### Audit log

- [ ] Implement event types: corpus locked, screening decision, AI recommendation, AI override, conflict resolved, PRISMA override, export generated, project archived.
- [ ] Persist required payload fields per event.
- [ ] Enforce visibility rules by role.

### Permanent records

- [ ] Preserve raw provider records post-lock.
- [ ] Preserve normalized Works post-lock.
- [ ] Preserve dedup audit post-lock.
- [ ] Preserve run YAML per run immutably.
- [ ] Preserve AI recommendations even after override.
- [ ] Preserve export history in archived state.

---

## QA acceptance slice

### High-risk regression checks

- [ ] Lock cannot proceed without exact typed confirmation.
- [ ] Lock warning appears when latest run had provider failures.
- [ ] Query Library remains user-scoped, not project-scoped.
- [ ] `Used in N projects` reflects distinct project count.
- [ ] `WorkCard` uses micro-grid, not signal-strength widget.
- [ ] Micro-grid collapses below 260px card width.
- [ ] Mobile screening contains no horizontal swipe actions.
- [ ] Conflict-routed works are excluded from main progress denominator.
- [ ] Stage completion requires both main queue and conflict queue completion.
- [ ] Gated export cards never use broken/error presentation.
- [ ] Post-lock banner becomes dismissible only after 1 hour.
- [ ] Dismissed or expired post-lock banner leaves permanent lifecycle chip.
- [ ] ProviderProgressTable shows initial skeleton rows and poll timestamp.
- [ ] `WorkIdSet` confidence pill and 4-chip identity row stay consistent for same work.

---

## Suggested ticket grouping

### Backend

- [ ] Search run orchestration
- [ ] Provider progress API
- [ ] Dedup + representative election
- [ ] Lock snapshot + immutable corpus
- [ ] Saved query model + lineage
- [ ] Screening decision + conflict model
- [ ] Export pipeline + history
- [ ] Audit log service

### Frontend

- [ ] App shell + sidebar + gating
- [ ] Project dashboard + lifecycle banner system
- [ ] Query builder + YAML state machine + library drawer
- [ ] Run monitor + progress polling UI
- [ ] Results browser + filters + Work surfaces
- [ ] Lock flow + post-lock states
- [ ] Screening desktop workspace
- [ ] Screening mobile gestures + fullscreen prompt
- [ ] Conflict queue
- [ ] Export builder + preview + history
- [ ] Settings + provider config + team surfaces

### QA

- [ ] State-machine coverage for project lifecycle
- [ ] State-machine coverage for run lifecycle
- [ ] Lock-flow irreversible action tests
- [ ] Screening keyboard and touch interactions
- [ ] Export gating and preview correctness
- [ ] Audit log visibility and payload checks

---

## Delivery note

This checklist is optimized for Jira/Linear-style decomposition and can be split directly into epics, stories, and QA tasks. It is intentionally implementation-facing rather than explanatory. [file:3]
