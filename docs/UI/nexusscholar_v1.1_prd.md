# NexusScholar — Canonical v1 Product Specification

**Version:** 1.1  
**Status:** Build-Ready (v1.1 — audit corrections applied)  
**Date:** April 2026  
**Audience:** Product, Engineering, Design

---

## Executive Summary

NexusScholar is a cloud-based systematic literature review (SLR) platform that owns the full pipeline from multi-provider search through deduplication, corpus management, screening, and export. The platform is built on top of the `nexus-scholar/core` package, whose four primary domains — Search, Deduplication, CitationNetwork, and Dissemination — map directly to the product's feature surface. This document is the single authoritative source for v1 scope, IA, component contracts, lifecycle logic, screening rules, export gating, and build order. All prior design passes, critiques, and resolutions are consolidated here. Competing or superseded statements in prior conversation artifacts should be ignored in favour of this document.

---

## 1. v1 Scope

### 1.1 What Ships in v1

| Surface | Status | Notes |
|---------|--------|-------|
| Project creation wizard | ✅ Live | Templates: PRISMA, Cochrane, Scoping, Rapid, Custom |
| Search Runs (query builder + YAML) | ✅ Live | Visual form + YAML escape hatch |
| Saved Query Library | ✅ Live | User-scoped; source_query_id linkage for reuse |
| Provider progress monitoring | ✅ Live | Polling-based; 7 providers |
| Results browser (corpus explorer) | ✅ Live | Requires ≥1 completed run |
| Corpus lock flow | ✅ Live | Full checkpoint modal; typed confirmation |
| Screening Stage 1 — Title & Abstract | ✅ Live | Solo and AI-recommendation modes |
| Screening Stage 2 — Full-text | ✅ Live | Unlockable after Stage 1 completes |
| Conflict queue | ✅ Live | AI disagreements; extensible for human adjudication |
| Export: BibTeX, RIS, CSV/Excel, JSON | ✅ Live | Dissemination-independent formats |
| Export history | ✅ Live | Re-download; duplicate settings |
| PRISMA flowchart (auto-populated) | ⏸ Gated | Requires Dissemination module completion |
| Annotated bibliography export | ⏸ Gated | Requires Dissemination module completion |
| Citation Network visualization | ⏸ Gated | CitationGraphRepository deferred to v2 |
| Collaborative screening (multi-reviewer) | ⏸ Deferred | v2 — after team conflict model is defined |
| Cross-project corpus search | ⏸ Deferred | Requires data access policy decision |
| Corpus amendment (locked v2) | ⏸ Deferred | v2 — after amendment workflow is designed |

### 1.2 Explicit Deferrals and Rationale

- **PRISMA auto-population**: `src/Dissemination/` is in-progress. The UI format exists (step 3 of Export Builder) but the export action is disabled with an honest gate message. Numbers cannot be auto-computed until `ProviderProgress` and `DedupClusterRepository` pipelines are wired into the Dissemination adapter.
- **Citation Network**: `src/CitationNetwork/` exists in the package but is untested. The surface is present in the IA as a placeholder with a substantive ghost wireframe — not a bare "Coming Soon" message.
- **Collaborative screening**: Multi-reviewer conflict detection logic is structurally supported by the ConflictQueue architecture, but the invite-reviewer, role-assignment, and human-human conflict adjudication UX is not designed for v1.
- **Corpus amendment**: The lock is architecturally immutable in v1. The amendment workflow (locked v2 with diff view) is a meaningful differentiator and must be scoped separately.

---

## 2. Information Architecture

### 2.1 Sidebar Navigation

```
NexusScholar
├── Dashboard              (cross-project overview)
├── Projects               (project list + create new)
│    └── [Active Project]
│         ├── Overview
│         ├── Search Runs
│         │    └── [Run Detail]
│         │         ├── Query Builder
│         │         ├── Provider Config
│         │         ├── Run Status
│         │         └── Provider Progress Table
│         ├── Results           (gated: requires ≥1 completed run)
│         ├── Screening         (gated: requires locked corpus)
│         │    ├── Stage 1 — Title & Abstract
│         │    ├── Stage 2 — Full-text
│         │    └── Conflict Queue
│         ├── Citation Network  (gated: v2 placeholder visible)
│         └── Export
│              ├── Export Builder
│              └── Export History
├── Notifications          (run completions, failures, conflicts)
├── Settings
│    ├── Profile
│    ├── Provider API Keys
│    ├── Team / Members
│    └── Billing / Plan
└── Help & Docs
```

### 2.2 Navigation Gating Rules

| Surface | Gate condition | Gate behaviour |
|---------|---------------|----------------|
| Results | ≥1 run completed for this project | Nav item disabled + tooltip: "Complete a search run to unlock results" |
| Screening | Corpus locked | Nav item disabled + tooltip: "Lock your corpus to begin screening" |
| Stage 2 (Full-text) | Stage 1 ≥ 1 decision recorded | Tab disabled with tooltip |
| Conflict Queue | ≥1 conflict exists | Tab visible but shows empty state if count = 0 |
| Citation Network | Always visible | Shows ghost wireframe placeholder with data-collection status message |
| Export | Always visible | v1 formats always available; gated formats show locked state inline |

### 2.3 Modal and Drawer Inventory

| Trigger | Type | Width / Behaviour |
|---------|------|-------------------|
| Create Project | Full-page wizard (stepped modal) | Multi-step; blocks sidebar |
| New Search Run | Right drawer | 480px; overlays content; back-stackable |
| Work card click | Right drawer | 480px; slides in from right |
| Lock Corpus | Confirmation modal | Centred overlay; typed confirmation required |
| Start Screening | Inline transition (no modal) | Stage + mode selector before first card |
| Export Builder | Stepped modal (3 steps) | Full-page modal on mobile; 800px panel on desktop |
| Add Team Member | Modal | 480px centred |
| PRISMA flowchart | Full-page preview | Opens export preview in full modal |
| Provider key management | Modal | 560px; from Settings |

### 2.4 Mobile vs. Desktop Surface Availability

| Surface | Mobile | Desktop |
|---------|--------|---------|
| Screening workspace | ✅ Full (swipe + tap) | ✅ Full (keyboard shortcuts) |
| Conflict Queue | ✅ Read + decide | ✅ Full |
| Notifications | ✅ Full | ✅ Full |
| Query Builder | ✅ View only | ✅ Full edit |
| Run Monitor | ✅ Read only | ✅ Full |
| Results browser | ✅ Card view only | ✅ Full (card + table + filter sidebar) |
| Export Builder | ✅ Stepped (simplified) | ✅ Full with live preview panel |
| Citation Network | ✅ Placeholder only | ✅ Placeholder with ghost wireframe |
| Settings | ✅ Full | ✅ Full |

---

## 3. Lifecycle Logic

### 3.1 Project Lifecycle States

```
Draft
  └──▶ Active Search       (first run queued or completed)
         └──▶ Corpus Locked    (explicit lock action by owner)
                └──▶ Screening         (screening started)
                       └──▶ Reporting        (all screening complete)
                              └──▶ Archived       (manual action)
```

**State transition rules:**

- `Draft → Active Search`: Automatic on first run submission.
- `Active Search → Corpus Locked`: Manual action only. Owner must initiate `LockCorpusModal`. Cannot be automated or bypassed. Requires typed confirmation (project name).
- `Corpus Locked → Screening`: Manual. User initiates via "Start Screening" CTA. Mode selector shown before first card.
- `Screening → Reporting`: Automatic when all non-conflicted works in both active stages have a final decision.
- Any state → `Archived`: Manual action by owner. Archived projects are read-only but fully accessible.

**State regression:** Not permitted in v1. No rollback from Locked. Corpus amendment (locked v2) is a v2 feature.

### 3.2 Run Lifecycle States

| State | Description | Transition trigger |
|-------|-------------|-------------------|
| `queued` | Job accepted, awaiting worker | Run submitted |
| `running` | Providers being queried | Worker picks up job |
| `partial` | ≥1 provider completed, ≥1 still pending | Mid-run state |
| `completed` | All providers resolved (success or soft-fail) | All provider slots filled |
| `failed` | Hard failure threshold met | See §3.3 |
| `cancelled` | User cancelled before completion | Manual cancel action |

**Post-run transitions:**  
- From `completed` or `partial`: "View Results" CTA enabled.  
- From `failed`: "Retry failed providers" CTA + "View partial results" if any data exists.

### 3.3 Provider Failure Thresholds

**Soft failure (provider-level):** A single provider returns an error, timeout, or zero results. The run continues. The provider's row in `ProviderProgressTable` shows error state. Run can still reach `completed` status. User sees a warning banner: "Results may be incomplete — [Provider] did not respond."

**Hard failure (run-level):** Defined as any of:
- ≥4 of 7 providers fail in a single run, OR
- The only provider selected for a run fails.

Hard failure sets run state to `failed`. Partial results (if any) are preserved and accessible via "View partial results." The run cannot be promoted to corpus-lock-eligible status unless the user acknowledges the gap.

**Lock eligibility after partial run:** If the most recent run ended in `partial` or `failed`, the `LockCorpusModal` shows a warning block: "Your most recent run had provider failures. Locking now may result in an incomplete corpus." The user must explicitly check an acknowledgement box before the typed confirmation is accepted.

### 3.4 Corpus Lock Prerequisites and Flow

**Prerequisites (all must pass before lock is offered):**
1. At least one run in `completed` or `partial` state exists.
2. No run is currently in `running` or `queued` state.
3. User is project Owner (not Reviewer or Observer).

**Lock flow:**

1. User clicks "Lock Corpus" button (visible in Search Runs and Overview).
2. `LockCorpusModal` opens with:
   - Corpus summary: total raw records, unique works after dedup, dedup savings %, run count.
   - Delta view: works added since last run (if multiple runs).
   - Partial/failed run warning block (if applicable, with acknowledgement checkbox).
   - Irreversibility statement: "Locking is permanent. No new search runs can be added to this corpus after locking."
   - Typed confirmation input: "Type the project name to confirm."
3. Confirm button enabled only when typed name matches exactly (case-sensitive).
4. On confirm: corpus snapshot created against `DedupClusterRepository`. `WorkRepository` freezes. Project state → `Corpus Locked`.
5. Screening nav item unlocks. A post-lock banner appears on the Overview page: "Corpus locked on [date]. [N] unique works. [Runs: N]." Banner follows a three-state dismissal machine:
   - **0–1 hour after lock:** Non-dismissible. No ✕ control shown.
   - **After 1 hour:** Dismissible. ✕ control appears. On dismiss: banner is hidden and replaced permanently by a `LifecycleStatusBar` chip: "ℹ Corpus locked — view details."
   - **After 48 hours:** Auto-hides regardless of user action. The `LifecycleStatusBar` chip remains permanently.
   The chip (not the banner) is the permanent record surface. The chip survives reload and re-login indefinitely.

### 3.5 Post-Lock Behaviour

- **Search Runs tab**: Remains accessible. All past runs are readable. "New Run" button is disabled with tooltip: "Corpus is locked. Start a new project to run additional searches."
- **Query Library**: Still accessible; queries can be reused in new projects.
- **Results tab**: Remains fully accessible. Corpus browser reflects the locked snapshot.
- **Corpus stats**: Show locked-snapshot values. A "Locked" badge appears next to the stats header.
- **Work Detail Drawer**: Fully functional post-lock. Dedup audit, sources panel, and all metadata remain accessible.
- **Screening decisions**: Written against the locked snapshot work IDs. If (hypothetically) a work is not found in the locked snapshot, the decision is rejected with an error — this should never occur in normal operation.

### 3.6 Lifecycle Banner States

A persistent banner strip appears below the `LifecycleStatusBar` on the Overview page. Its content and severity change based on the current project state. It is not a toast — it remains visible until the condition resolves or the user dismisses it (where permitted).

| Banner state | Severity | Message | Dismissible | Gates lock? |
|---|---|---|---|---|
| No runs yet | Info (blue) | "Run your first search to begin building your corpus." | No | N/A |
| Run in progress | Info (blue) | "Run #[N] is in progress — results will appear when complete." | No | Yes — Lock button hidden while any run is in `running` or `queued` state |
| Run completed — all providers OK | Success (green) | "Run #[N] completed — [N] new works added. Ready to lock when you're satisfied." | Yes (once) | No |
| Run completed — soft failure (1–2 providers failed) | Warning (amber) | "Run #[N] finished with issues — [N] provider(s) failed. Review before locking." | Yes (once) | No — lock available, but partial-run warning block shown in `LockCorpusModal` |
| Run completed — hard failure (≥3 providers or a required provider failed) | Error (red) | "Run #[N] failed. Your corpus may be significantly incomplete. Retry before locking." | No | Soft-gate — lock available only after user checks acknowledgement checkbox in `LockCorpusModal` |
| Corpus locked | N/A — replaced by chip | LifecycleStatusBar chip: "ℹ Corpus locked — view details" | No (chip is permanent) | N/A |
| Screening in progress | Info (blue) | "Stage [N] screening in progress — [N] works remaining." | Yes | N/A |
| All screening complete | Success (green) | "Screening complete. Proceed to Export." | Yes | N/A |

**Configurable provider severity threshold (Owner-only, in Settings → Provider Config):**
- **Soft gate trigger:** ≥1 provider failure → amber banner. Lock allowed; warning shown in modal.
- **Hard gate trigger:** ≥3 provider failures, OR any provider explicitly marked `required` fails → red banner. Lock requires acknowledgement checkbox.
- Default: all 7 providers are non-required. Owner can mark specific providers as `required` (e.g., PubMed for a medical SLR). Marking a provider `required` means its failure triggers the hard gate regardless of total failure count.


---

## 4. Component Specifications

Each component below is specified with: purpose, inputs, output/behaviour, and all required states.

---

### 4.1 `ProjectCard`

**Purpose:** Thumbnail for a project on the global Projects list.

**Inputs:** Project name, review question (truncated to 120 chars), lifecycle state, unique works count, last activity timestamp, owner avatar.

**States:**

| State | Behaviour |
|-------|-----------|
| Default | Name, question, status badge, work count, last activity |
| Hover | Subtle elevation; quick-stats tooltip (runs, stage, provider count) |
| Loading | Shimmer skeleton matching card layout |
| Empty (no projects) | Empty state with CTA: "Create your first project" + animated illustration |
| Archived | Muted opacity; "Archived" badge; click still navigates |

---

### 4.2 `ProjectWizard`

**Purpose:** Multi-step project creation flow.

**Steps:** (1) Name + research question → (2) Domain template picker → (3) Team invite (optional, skippable)

**Template options:** PRISMA, Cochrane Intervention, Scoping Review, Rapid Review, Custom (blank).

**Template effect:** Pre-populates exclusion reason code vocabulary and export format defaults. Does not lock the user to a methodology.

**States:**

| State | Behaviour |
|-------|-----------|
| Step incomplete | "Next" disabled until required fields filled |
| Step valid | "Next" enabled; inline validation messages clear |
| Template selected | Description panel shows what the template pre-populates |
| Submit loading | Spinner on "Create Project" button; inputs disabled |
| Submit error | Inline error message; inputs re-enabled |

---

### 4.3 `LifecycleStatusBar`

**Purpose:** Horizontal 6-stage stepper showing current project position.

**Stages:** Draft → Active Search → Corpus Locked → Screening → Reporting → Archived

**States per stage node:**

| State | Visual |
|-------|--------|
| Completed | Filled circle + checkmark; clickable (navigates to stage) |
| Current | Filled circle + pulsing ring; not clickable |
| Upcoming | Empty circle; hover shows tooltip: "What's needed to unlock this" |
| Blocked | Empty circle + warning icon; hover explains blocker |

---

### 4.4 `QueryBuilderForm`

**Purpose:** Visual construction of a search query. Primary interface for non-technical users. Contains a **"Load from library"** button (top-right of form) that opens the user's `SavedQueryLibrary` in a drawer — this is the project-level entry point to the library, since `Query Library` is a global nav item, not a per-project sub-page.

**Inputs produced:** Keywords with AND/OR/NOT toggles, title-only vs. full-text toggle, year range (1900–current year), max results per provider, provider checkbox selection.

**YAML sync states:**

| State | Behaviour |
|-------|-----------|
| `synced` | Form and YAML match. Both editable. YAML updates live as form changes. |
| `manual-edit` | User edited YAML directly. Form shows banner: "You're in expert mode — visual form is read-only." YAML is authoritative. |
| `parse-error` | YAML is invalid. Error banner with line/column reference. Form read-only. YAML highlighted. Save disabled. |
| `diverged` | YAML contains flags the form cannot represent (e.g., custom CLI flags). Banner: "This query uses advanced flags. Visual form is disabled." |

**Validation:** Year range: from ≤ to. Max results: 1–10,000 (warn above 2,000: "Large queries may be slow"). At least one provider must be selected.

---

### 4.5 `SavedQueryLibrary` (Query Library)

**Purpose:** User-scoped collection of saved queries for reuse across projects.

**Key data model:**
- `query_id`: UUID for this saved query.
- `source_query_id`: ID of the query this was forked/copied from (nullable; null = original).
- `owner_user_id`: The user who saved this query.
- `project_context`: Array of project IDs this query has been used in (read-only, populated on use).
- `name`, `description`, `tags`, `created_at`, `last_used_at`.

**Ownership rule:** Queries belong to users, not projects. Saving a query from Project A makes it available to the same user in Project B. Queries are never automatically shared between team members.

**States:**

| State | Behaviour |
|-------|-----------|
| Empty library | Empty state: "No saved queries yet. Build a query and save it to reuse across projects." |
| Populated | Sortable list: name, last used, **"Used in N projects"** (derived from `len(project_context)` — counts distinct projects, not executions), source badge (forked/original) |
| Forked query | "Forked from [original name]" pill; clicking navigates to source if accessible |
| Loading | Shimmer skeleton list |
| Search/filter | Live filter by name/tag |

---

### 4.6 `RunStatusCard`

**Purpose:** Async status monitor for a background run. Primary card on Run Detail page.

**Data:** Status badge, submitted time, completion time, total raw results, total unique works after dedup, dedup savings %.

**States:**

| State | Behaviour |
|-------|-----------|
| `queued` | "Queued" badge + spinner. "Waiting for worker." |
| `running` | Live progress bar across 4 pipeline stages. ProviderProgressTable rows animate in. |
| `partial` | Yellow warning badge. Completed providers shown. Failed providers highlighted red. |
| `completed` | Green badge. All stats visible. "View Results" CTA enabled. |
| `failed` | Red badge. Error summary. "Retry failed providers" + "View partial results" (if data exists). |
| `cancelled` | Muted badge. "Run cancelled." Re-run option available. |

---

### 4.7 `ProviderProgressTable`

**Purpose:** Per-provider breakdown of a single run.

**Columns:** Provider name (with logo), Status (icon), Records returned, Latency (ms), Error (if any).

**States:**

| Row state | Visual |
|-----------|--------|
| Pending | Spinner in status column |
| Success | Green checkmark |
| Soft fail | Red ✗ + expandable error message inline |
| Rate-limited | Amber warning + "Rate limit reached. [N] results before limit." |
| Not configured | Muted row + "API key required" link to Settings |

**Loading (initial) state:** 7 shimmer skeleton rows matching the full table layout. Each row shows a grey provider logo placeholder, grey status icon placeholder, and grey metric cells. Skeleton resolves row-by-row with a 150ms fade-in per row as provider results arrive.

**Poll trust timestamp:** Displayed below the table while the run is active: "Last updated: [N] seconds ago · Auto-refreshing every 5s" with a manual "Refresh now" link. On run completion (any terminal state): timestamp freezes to "Completed at [HH:MM:SS on date]." No further polling occurs after any terminal state.

**Interactions:** Row hover reveals error tooltip. Provider name is a link to its Settings config. "Retry this provider" action on failed rows (available during `running` or `partial` run states).

---

### 4.8 `WorkCard`

**Purpose:** Primary display unit for a deduplicated Work.

**Data:** Title (2 lines max, truncated), first 3 authors + "et al.", venue + year, abstract preview (3 lines), source count badge ("Found in N sources"), completeness indicator, `WorkIdSet` identity confidence pill.

**`WorkIdSet` identity confidence pill:** Coloured pill showing dedup match quality:
- 🟢 **High** — DOI present (deterministic cryptographic match)
- 🟡 **Medium** — arXiv ID or S2 ID only (strong but not DOI-level)
- 🔴 **Low** — title/author heuristics only (fuzzy match; manual review recommended)

Tooltip on hover: "Match basis: [DOI / arXiv ID / Title heuristic]." Clicking the pill opens `WorkDetailDrawer` at the SourcesPanel.

**`completenessScore` indicator:** 6-square field-completeness micro-grid showing presence of: `DOI` / `ABS` (abstract) / `AUTH` (full author list) / `VEN` (venue) / `CIT` (citation count) / `ORCID`. Each square is green (present) or amber (missing). Tooltip on hover: "Score: [N]/10 — [N] of 6 key fields present." Responsive collapse via container query: at card width < 260px, grid collapses to a single `[N/6]` count pill.

**States:**

| State | Behaviour |
|-------|-----------|
| Default | Full card as described |
| Screened-Include | Green left accent line + "✓ Included" chip |
| Screened-Exclude | Muted card + "✗ Excluded [reason]" chip |
| Screened-Maybe | Amber left accent line + "◎ Maybe" chip |
| In-conflict | Amber border + "⚠ Conflict" chip |
| Loading | Shimmer skeleton |
| Corpus locked | No visual change — locking does not affect card display |

---

### 4.9 `WorkDetailDrawer`

**Purpose:** Full normalized metadata for a Work. Authoritative record view.

**Sections (in order):**
1. Header: Title, authors, venue, year, open-access badge.
2. Identifiers: `WorkIdSet` 4-chip identity key row — `DOI` / `PMID` / `arXiv ID` / `S2 ID`. Present = filled chip with copy button + external link icon. Absent = greyed chip with "Not available" tooltip. The presence pattern here is the dedup identity basis and is the same data driving the confidence pill on `WorkCard`.
3. Abstract: Full text. Expand/collapse if > 600 chars.
4. Citation count + open-access status.
5. SourcesPanel (collapsed by default): See §4.10.
6. Screening action row (if screening is active): Include / Exclude / Maybe buttons.

**States:**

| State | Behaviour |
|-------|-----------|
| Loading | Skeleton for each section |
| No abstract | "Abstract not available from any source" in muted text |
| No DOI | DOI field replaced with "No DOI recorded" |
| Post-lock | Fully readable; no edit controls; "Locked corpus" badge |
| Screening active | Decision buttons shown in drawer footer |
| Screening inactive | Decision buttons hidden |

---

### 4.10 `SourcesPanel`

**Purpose:** Dedup audit — shows every raw record merged into this Work.

**Collapsed state:** "Found in [N] sources — click to inspect." Shows provider logos as small badges.

**Expanded state:** At the top: `WorkIdSet` 4-chip identity key row (DOI / PMID / arXiv ID / S2 ID) showing which IDs confirmed dedup identity across all sources. Below: table with columns: Provider / Raw title / DOI present / IDs present / completenessScore. The row that was elected as representative is highlighted with "✓ Representative" chip and a tooltip explaining why (score comparison).

**Conflict cases:** If two providers have equal completenessScore, the elected representative row shows "Elected by tie-break rule: [rule name]."

---

### 4.11 `LockCorpusModal`

**Purpose:** Irreversible checkpoint. Freezes WorkRepository snapshot.

**Sections:**
1. Corpus summary card: total raw records, unique works, dedup savings %, run count.
2. Delta block: "Since your last run: +[N] new works, [N] updated records." (Shown only if >1 run exists.)
3. Partial/failed run warning block (conditional): "Your most recent run had provider failures. Locking now may produce an incomplete corpus." + acknowledgement checkbox.
4. Irreversibility statement: "This action cannot be undone. No new runs can be added after locking."
5. Typed confirmation input: `Type "[project name]" to confirm`.
6. Confirm button (enabled only when: text matches + any conditional checkboxes are checked).
7. Cancel button (always enabled).

**States:**

| State | Behaviour |
|-------|-----------|
| Default | Confirm disabled; text field empty |
| Partial warning visible | Checkbox must be checked before confirm enables |
| Text match | Confirm button enabled |
| Text mismatch | Input border red; "Name does not match" hint text |
| Submitting | Spinner on confirm button; all inputs disabled |
| Error | Inline error; inputs re-enabled; spinner removed |

---

### 4.12 `ScreeningModeSelector`

**Purpose:** Pre-screening setup. Shown before first card in a stage.

**Modes:**
- **Solo**: One user makes all decisions.
- **AI-Recommendation**: AI models screen all works and produce recommendations. User reviews each recommendation and approves or overrides. The human decision is always final. AI reasoning (if available from API response) is shown inline.
- **Collaborative** *(v2 only — shown as disabled with "Coming in v2" badge)*: Multiple human reviewers; conflicts surfaced to Conflict Queue.

**AI mode behaviour:**
- AI produces: `recommendation` (Include / Exclude / Maybe) + `confidence` (0–1) + `reasoning_snippet` (string, optional, from API response).
- Human sees recommendation prominently but must make an active decision (approve or override).
- Overrides are logged with original recommendation preserved.
- AI recommendation is never treated as a final decision — it is advisory only.

---

### 4.13 `ScreeningWorkspace`

**Purpose:** Primary screening screen. Full-viewport, distraction-free.

**Layout zones:**
1. **Top bar**: Project name, stage label, progress pill ("128 / 312"), mini progress bar, mode badge, keyboard shortcut icon.
2. **Content area** (max-width 720px, centred): Title, authors/venue/year, full abstract (scrollable), DOI links, "Show full cluster" toggle.
3. **Decision bar** (fixed bottom): Include ✓ / Exclude ✗ / Maybe ◎ buttons. Keyboard shortcuts: I / E / M. Undo ← (far left). Skip → (far right).

**AI-Recommendation mode additions to content area:**
- Recommendation chip: "AI recommends: Include (confidence: 87%)"
- Reasoning block (collapsible): AI reasoning snippet, if available.
- Override indicator (post-decision): If user overrides AI, a small "You overrode AI recommendation" note persists on the decided card in history.

**Exclusion Reason Picker:**
- Appears when Exclude is pressed: slides up from decision bar.
- Mandatory if SLR template is PRISMA, Cochrane, or Scoping.
- Optional for Rapid and Custom templates.
- Reason codes: "Wrong population" / "Wrong outcome" / "Wrong study design" / "Not peer-reviewed" / "Outside date range" / "Duplicate" / "Not accessible" / "Other (free text)".
- Keyboard: codes are numbered 1–8 for quick input.

**Keyboard shortcut map:**

| Key | Action |
|-----|--------|
| I | Include |
| E | Exclude |
| M | Maybe |
| U | Undo last decision |
| S | Skip (move to end of queue) |
| J / ↓ | Next work (in history review) |
| K / ↑ | Previous work (in history review) |
| 1–8 | Select exclusion reason (when picker open) |
| Esc | Close exclusion picker / cancel action |
| ? | Toggle keyboard cheatsheet overlay |

**Mobile layout:**
- Decision buttons: full-width tap targets, thumb zone.
- Swipe Up from bottom third = Include (avoids iOS Safari/Android Chrome back-forward navigation conflict).
- Swipe Down from top third = Exclude.
- Long press (500ms) = Maybe.
- Horizontal swipes are **not used** — reserved by the OS browser for navigation.
- On first mobile session: bottom sheet prompt explains swipe gestures and offers full-screen mode (`document.requestFullscreen()`). Stored in `localStorage`; shown once only.
- Tap "Show full cluster" still available.
- Top bar collapses to single-line progress pill.

**States:**

| State | Behaviour |
|-------|-----------|
| Loading next work | Skeleton of content area |
| Queue complete | Completion screen: stats summary, link to Conflict Queue if conflicts exist, link to next stage if eligible |
| All excluded | Warning: "All works excluded at this stage. Review before proceeding." |
| Conflict routed | Work skipped from main queue + conflict badge increments in tab |
| Undo stack empty | Undo button disabled |

---

### 4.14 `ScreeningProgressBar`

**Purpose:** Live progress tracker, top of screening stage.

**Data:** Total / screened / included / excluded / maybe / in-conflict / remaining + % complete.

**Segments:** Colour-coded bar: included (green) + excluded (red) + maybe (amber) + unscreened (muted). Conflict count shown as separate counter badge (not in bar itself, to avoid confusion with main progress).

**Interaction:** Click a segment to filter work list by that decision class.

**Important rule:** Conflicted works are **not counted** in the primary progress denominator. A work routed to the Conflict Queue exits the main queue count. Progress = (screened non-conflict) / (total non-conflict). Conflict Queue has its own resolution progress counter.

---

### 4.15 `ConflictQueue`

**Purpose:** Durable sub-surface for works where AI models (or, in v2, human reviewers) disagreed. Architecturally supports both AI disagreements (v1) and human-human disagreements (v2) without rebuilding the surface.

**Structure:**
- Queue list: Work cards with conflict metadata (which models disagreed, what each decided, confidence scores).
- Per-work detail: Side-by-side decision comparison. Model A / Model B / Model C columns with recommendation + confidence + reasoning snippet.
- Human decision controls: Same Include / Exclude / Maybe buttons as ScreeningWorkspace. Exclusion reason picker applies.
- Resolution logging: Human decision stored as `resolution_source: human`, with the original AI decisions preserved in audit record.

**v2 extension:** When collaborative mode ships, the same queue will surface human-human conflicts with `resolution_source: human_adjudicator`. The queue structure, data model, and UI layout require no changes.

**States:**

| State | Behaviour |
|-------|-----------|
| Empty | Empty state: "No conflicts at this stage" |
| Active | Queue list with unresolved count badge |
| Work resolved | Row marked resolved; moves to resolved section (collapsible) |
| All resolved | Completion message + link back to stage progress |

---

### 4.16 `ExportBuilder`

**Purpose:** Guided 3-step export flow.

**Step 1 — Scope:**
- All works in corpus
- Screened-in (Stage 1)
- Screened-in (Stage 2)
- Custom filter (opens filter UI, same controls as CorpusFilterSidebar)
- Live record count updates on selection.

**Step 2 — Format (icon card grid, multi-select):**

| Format | v1 Status | Notes |
|--------|-----------|-------|
| BibTeX | ✅ Live | |
| RIS | ✅ Live | |
| CSV + Excel | ✅ Live | |
| JSON (AI preset) | ✅ Live | All normalised fields; API-pipeline ready |
| Annotated Bibliography | ⏸ Gated | "Available when Dissemination module is ready" |
| PRISMA Flowchart | ⏸ Gated | "Available when Dissemination module is ready" |

**Gated format card appearance:** Greyed out with lock icon. Tooltip copy (role-conditional):
   - **Standard users (student / researcher):** "This export format is in development and will unlock automatically when ready. No action needed."
   - **Admin / Owner role:** "This format requires the Dissemination pipeline (currently in progress). It will unlock automatically when the module ships."
   No click action on the card itself.

**Step 3 — Options:**
- Filename prefix field
- Field inclusion checkboxes (abstract, ORCID, citation count, open-access status, completenessScore, source count)
- PRISMA override panel (visible only when PRISMA format selected and unlocked in v2)
- "Export [N] works as [formats]" download button

**Live preview panel (desktop, right column):**
- BibTeX/RIS/JSON: Syntax-highlighted preview of first 3 records.
- CSV: Mini table preview with column headers.
- PRISMA: Rendered flowchart diagram (v2 only).
- If multiple formats selected: tabs across top of preview panel.

---

### 4.17 `PRISMAFlowDiagram`

**Status:** v2 gated. Spec included for completeness.

**Auto-populated fields from package:**
- Records identified per database: from `ProviderProgress.result_count` per provider.
- Duplicates removed: from `DedupClusterRepository` dedup savings count.
- Records screened: from screening decision count (Stage 1 total).
- Records excluded (with reasons): from exclusion reason code counts.
- Reports sought / not retrieved / assessed: from Stage 2 counts.
- Studies included: final screened-in count.

**Manual override:** Any auto-computed number can be clicked to override. Override opens an inline input. On save, the cell shows "Edited" badge with original value in tooltip. Override is logged with: original value, override value, user ID, timestamp, and optional note (mandatory if SLR template is PRISMA or Cochrane — note is required, not optional).

**Export:** SVG and PDF when Dissemination module ships.

---

### 4.18 `CitationNetworkPlaceholder`

**Purpose:** Substantive placeholder that functions as a trust signal, not a dead end.

**Contents:**
- Ghost wireframe illustration: node-edge graph silhouette showing what the visualisation will look like (cluster nodes, weighted edges, paper labels).
- Heading: "Citation Network — Coming in v2"
- Description (2–3 sentences): Explains that citation relationship data is being collected in the background by `CitationGraphRepository` and will be visualised here once the feature ships. Does not say "coming soon" without substance.
- "Notify me when this is ready" toggle (stores preference in user profile).
- Data collection status: "Citation data collected for [N] works in this corpus." (Live count from CitationGraphRepository if accessible, else hidden.)

---

## 5. Screening Logic

### 5.1 Solo Mode

- One user makes all decisions.
- No conflict detection.
- All decisions are immediately final.
- Undo available for the most recent N decisions (N = 10 in v1).

### 5.2 AI-Recommendation Mode

**Setup:** User selects AI-Recommendation mode in `ScreeningModeSelector`. Up to 3 AI models can be configured (model selection from available API-connected providers). If only 1 model is configured, conflict detection is disabled (no disagreement possible).

**Screening flow with 3 models:**
1. All 3 models screen the work independently (async, before the work is shown to the user).
2. System evaluates agreement:
   - All 3 agree → Work enters main queue with recommendation prominently displayed.
   - ≥2 disagree → Work is routed to Conflict Queue instead of main queue.
3. User works main queue. Each card shows the majority/unanimous recommendation + confidence.
4. User reviews Conflict Queue separately after (or interleaved, via tab).

**Conflict routing definition:**
- No conflict: All 3 models return identical decisions.
- Soft conflict: 2/3 agree on the same decision. Routed to Conflict Queue with majority recommendation shown.
- Hard conflict: All 3 models return different decisions. Routed to Conflict Queue with no majority recommendation; all three shown side-by-side.

**Human decision is always final.** AI recommendations are advisory. The product carries no liability for AI screening decisions; the human reviewer is the accountable party.

**AI reasoning display:** If the AI model's API response includes a reasoning string, it is shown in a collapsible block. If no reasoning is available, the block is hidden (not shown as empty).

### 5.3 Conflict Queue Routing Rules

| Condition | Routing |
|-----------|---------|
| All models agree | Main queue with recommendation chip |
| 2/3 agree (soft conflict) | Conflict Queue — majority recommendation shown |
| 0/3 agree (hard conflict) | Conflict Queue — all three shown, no majority |
| Single model configured | Main queue only — no conflicts possible |
| Model API error for a work | Work held in `pending-model-error` state; user notified; can manually screen the work from main queue |

### 5.4 Progress Accounting

- **Main queue denominator:** total works minus conflict-routed works.
- **Conflict Queue has a separate completion counter:** "N of M conflicts resolved."
- **Stage completion requires:** main queue 100% decided AND conflict queue 100% resolved.
- **Displayed progress:** Main progress bar shows main queue only. Conflict badge counter (tab level) shows unresolved conflicts. Combined completion state shown only on the stage completion screen.

---

## 6. Export Gating

### 6.1 v1 Live Exports (No Dependencies)

These formats are always available post-lock regardless of Dissemination module state:

| Format | Scope options | Notes |
|--------|--------------|-------|
| BibTeX | All / Screened-in S1 / Screened-in S2 / Custom | Standard BibTeX fields from normalised Work |
| RIS | All / Screened-in S1 / Screened-in S2 / Custom | Standard RIS fields |
| CSV + Excel | All / Screened-in S1 / Screened-in S2 / Custom | All normalised fields; Excel with column headers |
| JSON (AI preset) | All / Screened-in S1 / Screened-in S2 / Custom | All normalised fields; completenessScore included |

### 6.2 v2 Gated Exports (Dissemination Module Required)

| Format | Gate state | User-facing language |
|--------|-----------|----------------------|
| Annotated Bibliography | Locked | "Available when Dissemination pipeline is ready — unlocks automatically" |
| PRISMA Flowchart (SVG) | Locked | "Available when Dissemination pipeline is ready — unlocks automatically" |
| PRISMA Flowchart (PDF) | Locked | Same as above |

**Gate language rules:**
- Never use module/package terminology (e.g., "Dissemination module") in user-facing copy. Use: "this export format is in development and will unlock automatically."
- Never imply the user needs to do anything to unlock these — the unlock is system-side.
- Never show a broken or erroring state for gated exports — show the lock state as intentional.

### 6.3 Export Availability by Lifecycle State

| Project state | Export available? | Notes |
|---------------|-----------------|-------|
| Draft | No | No data yet |
| Active Search (pre-lock) | Partial | BibTeX/RIS/JSON of current raw results available as "preliminary export" with prominent banner: "This corpus is not yet locked. This export is not citable." |
| Corpus Locked | Yes | Full v1 export suite |
| Screening | Yes | Scope options now include Screened-in filters |
| Reporting | Yes | All scope options available |
| Archived | Yes | Read-only exports from locked snapshot |

---

## 7. Permanent Records

### 7.1 Corpus Snapshot

- Snapshot created at lock time against `DedupClusterRepository`.
- Snapshot is immutable. No records are modified, added, or removed post-lock.
- Snapshot stores: lock timestamp, triggering user ID, run IDs included, total raw record count, total unique work count, dedup cluster state.
- Snapshot is fully readable from the Results tab in all post-lock project states, including Archived.

### 7.2 Audit Visibility

The following actions are logged and viewable in the project's audit trail (accessible via Overview → "View audit log"):

| Action | Logged fields |
|--------|--------------|
| Corpus locked | User, timestamp, corpus stats |
| Screening decision | User (or AI model), work ID, decision, exclusion reason if applicable, stage |
| AI recommendation | Model name, decision, confidence, reasoning snippet (if available) |
| AI override | User, work ID, original AI decision, override decision, stage |
| Conflict resolved | User, work ID, conflict type, final decision, original AI decisions |
| PRISMA number overridden | User, field, original computed value, override value, note (mandatory for PRISMA/Cochrane templates) |
| Export generated | User, format, scope, record count, timestamp |
| Project archived | User, timestamp |

**Audit log visibility:**
- Owner: Full log.
- Reviewer: Own decisions only.
- Observer: No access.

### 7.3 Query Lineage

Every run retains a full copy of the query YAML submitted at run time, independent of any subsequent edits to the query in the Query Library. If the user later edits the saved query, the run's stored YAML is not affected. This ensures reproducibility: the run can always be inspected to see exactly what was submitted.

### 7.4 Post-Lock Persistence Rules

| Data | Persists after lock? | Notes |
|------|---------------------|-------|
| Raw provider records | Yes | Retained in full in DedupCluster |
| Normalised Work records | Yes | Locked snapshot |
| SourcesPanel dedup audit | Yes | Accessible from WorkDetailDrawer |
| Run YAML | Yes | Per-run, immutable |
| Screening decisions | Yes | Written against locked work IDs |
| AI recommendations | Yes | Original recommendations preserved even after override |
| Conflict records | Yes | Including all model decisions and human resolution |
| PRISMA overrides + notes | Yes | Audit log entry per override |
| Export history | Yes | Re-download available from archive |

### 7.5 Dismissal and Persistence Rules for Banners and Notices

| Banner / Notice | Dismissible? | Re-appears? |
|----------------|-------------|------------|
| Post-lock status banner (Overview) | No (0–1 hr) → Yes (after 1 hr) → Auto-hides (48 hrs) | On dismiss or 48-hr auto-hide: replaced by permanent `LifecycleStatusBar` chip "ℹ Corpus locked". Chip survives reload/re-login indefinitely. |
| Partial run warning (in LockCorpusModal) | N/A (modal-only) | Shown every time modal opens if condition is true |
| "Corpus not locked — preliminary export" | No | Permanent until corpus is locked |
| Conflict count badge (Screening tab) | No | Remains until all conflicts resolved |
| Provider failure warning (Run Monitor) | Yes (once) | Does not re-appear after dismissal |
| "Coming in v2" on gated exports | No | Permanent until feature ships |

---

## 8. Build Order

The following sequence is the definitive engineering implementation order. Dependencies are listed; a stage must not begin before its prerequisites are marked complete.

### Phase 0 — Infrastructure (no UI dependency)

1. Database schema: Projects, Runs, Works, DedupClusters, ScreeningDecisions, ConflictRecords, ExportHistory, AuditLog.
2. Authentication and role model: Owner / Reviewer / Observer.
3. Background job worker: Run queue, provider adapter pool, polling endpoint.
4. Provider adapters: OpenAlex, Semantic Scholar, PubMed, IEEE, arXiv, DOAJ, Crossref.
5. Deduplication pipeline: ID-based matching, completenessScore computation, representative election.
6. Corpus snapshot logic: Immutable lock, `WorkRepository` freeze.

### Phase 1 — Project and Run Core (unblocked after Phase 0)

7. `ProjectCard`, `ProjectWizard`, `LifecycleStatusBar`.
8. `QueryBuilderForm` + `YAMLEscapeHatch` (all 4 YAML sync states).
9. `SavedQueryLibrary` (user-scoped, source_query_id, query YAML lineage).
10. `ProviderConfigPanel` + `ProviderAPIKeyManager` (Settings).
11. `RunStatusCard` + `ProviderProgressTable` (polling, all run states).
12. `RunNotificationToast`.

### Phase 2 — Results Browser (unblocked after Phase 1, requires ≥1 completed run)

13. `CorpusStatsBar` + `CorpusFilterSidebar`.
14. `WorkCard` (all states including completenessScore indicator).
15. `WorkDetailDrawer` + `SourcesPanel` (dedup audit).
16. `LockCorpusModal` (all prerequisite checks, typed confirmation, partial-run warning block).
17. Post-lock state persistence: results tab, search runs tab, banner.

### Phase 3 — Screening (unblocked after Phase 2 — requires locked corpus)

18. `ScreeningModeSelector` (Solo mode first; AI-Recommendation mode second).
19. `ScreeningWorkspace` — desktop (keyboard shortcuts, exclusion reason picker, AI recommendation display).
20. `ScreeningProgressBar` (progress denominator excluding conflicts).
21. `ConflictQueue` (AI disagreement routing, side-by-side comparison, resolution logging).
22. `ScreeningWorkspace` — mobile (swipe gestures, thumb-zone tap targets).
23. Stage 2 unlock logic.

### Phase 4 — Export (unblocked after Phase 1; full scope available after Phase 3)

24. `ExportBuilder` (Steps 1–3; v1 formats only).
25. Export live preview panel (BibTeX/RIS/JSON syntax highlighting; CSV table preview).
26. `ExportHistoryList` (re-download, duplicate settings).
27. Gated format cards (lock state, correct user-facing copy, no broken states).

### Phase 5 — Audit, Settings, Notifications

28. Audit log (all action types, per-role visibility).
29. PRISMA override logging (mandatory note enforcement for PRISMA/Cochrane templates).
30. `TeamMemberList` + invite flow (Owner-only).
31. `BillingPlanCard` + usage tracking.
32. `NotificationCenter` (run completions, provider failures, conflicts).

### Phase 6 — v2 Deferred Surfaces (do not begin before Phase 5 is complete)

33. `CitationNetworkPlaceholder` upgrade → full `CitationNetworkView` (when CitationGraphRepository ships).
34. PRISMA flowchart export (when Dissemination module ships).
35. Annotated bibliography export (when Dissemination module ships).
36. Collaborative screening mode (after human-human conflict model is fully specified).
37. Corpus amendment workflow (after amendment UX is designed and approved).

---

## 9. Design Tokens and Interaction Rules

### 9.1 State Hierarchy (applies to all components)

Every component that accepts data must implement states in this priority order:

1. **Loading** — skeleton shimmer matching component layout.
2. **Error** — inline error message, never a raw error code.
3. **Empty** — warm message + action + visual (never just "No items").
4. **Partial** — data present but incomplete (e.g., partial run, missing abstract).
5. **Gated** — feature exists but is locked; honest language, no broken appearance.
6. **Default** — normal populated state.

### 9.2 Destructive Action Rules

Any action that is architecturally irreversible (corpus lock, account deletion, run cancellation) must:
- Require typed confirmation matching a meaningful string (project name, not "DELETE").
- Show an explicit irreversibility statement in the modal body.
- Show downstream consequences (what becomes unavailable after this action).
- Log to audit trail with full context.

### 9.3 Navigation Transition Rules

- Drawer open/close: 240ms ease-out slide from right. Backdrop fades in simultaneously.
- Modal open: 200ms scale-up from centre (0.95 → 1.0) + fade. Close: reverse.
- Page transitions within project: instant (no animation — functional app, not marketing site).
- Skeleton to content: 150ms fade-in on content; skeleton fades out simultaneously.
- Decision bar appear (screening workspace): slides up from bottom on first load, then persistent.

### 9.4 Responsive Breakpoints

| Breakpoint | Token | Notes |
|-----------|-------|-------|
| Mobile | 375px | Screening workspace full; sidebar hidden (hamburger) |
| Tablet | 768px | Sidebar collapses to icon rail; most surfaces adapted |
| Desktop | 1024px+ | Full sidebar; filter panel visible; preview panels visible |
| Wide | 1280px+ | Results browser: 3-column card grid |

---

## 10. Acceptance Criteria Summary

The following are the minimum acceptance criteria for v1 production readiness. Each must be verified before shipping:

- [ ] All 6 project lifecycle states transition correctly with correct gate rules.
- [ ] All run lifecycle states render correctly including partial, failed, and cancelled.
- [ ] Hard/soft provider failure thresholds trigger correct run state and user messaging.
- [ ] LockCorpusModal requires typed confirmation + conditional partial-run acknowledgement before confirming.
- [ ] Post-lock banner persists across reload and re-login.
- [ ] YAML sync operates in all 4 states: synced, manual-edit, parse-error, diverged.
- [ ] Query Library is user-scoped (not project-scoped); source_query_id linkage present.
- [ ] ScreeningProgressBar denominator excludes conflict-routed works.
- [ ] ConflictQueue tracks conflict count separately from main queue progress.
- [ ] Stage completion requires both main queue and conflict queue at 100%.
- [ ] AI recommendations are advisory only; human decision is always the stored final decision.
- [ ] AI recommendation and original AI decision are preserved in audit log even after human override.
- [ ] Gated export cards show correct user-facing copy (no module/technical language).
- [ ] Gated export cards are never in an error or broken state — they show the locked state.
- [ ] Post-lock banner is non-dismissible.
- [ ] PRISMA override logs: note is mandatory for PRISMA/Cochrane templates.
- [ ] CitationNetworkPlaceholder shows substantive ghost wireframe + data collection count.
- [ ] All components implement: loading, empty, error, partial, and gated states.
- [ ] Audit log enforces role-based visibility (Owner: full; Reviewer: own decisions; Observer: none).
- [ ] Mobile screening workspace: swipe gestures functional; decision buttons in thumb zone.
- [ ] Export history supports re-download and duplicate-settings actions.
- [ ] Preliminary export (pre-lock) shows persistent "not citable" banner.

---

- [ ] `completenessScore` indicator renders as 6-square micro-grid (DOI / ABS / AUTH / VEN / CIT / ORCID); amber when field is missing; collapses to `[N/6]` count pill via container query at card width < 260px.
- [ ] Mobile screening workspace: Swipe Up = Include, Swipe Down = Exclude, Long press = Maybe. No horizontal swipe gestures implemented. Full-screen prompt shown once on first mobile session.
- [ ] Post-lock banner is non-dismissible for 1 hour after lock; dismissible thereafter with ✕ control; replaced by permanent `LifecycleStatusBar` chip on dismiss; auto-hides after 48 hours with chip remaining.
- [ ] `WorkCard` shows `WorkIdSet` identity confidence pill (🟢 High / 🟡 Medium / 🔴 Low); `WorkDetailDrawer` and `SourcesPanel` show 4-chip identity key row (DOI / PMID / arXiv ID / S2 ID).
- [ ] Lifecycle banner amber state appears after any soft provider failure; red state appears after hard failure (≥3 providers or required-provider failure); red state requires acknowledgement checkbox before lock is confirmed.
- [ ] `ProviderProgressTable` renders 7 shimmer skeleton rows on initial load; poll trust timestamp shown during active run; timestamp freezes to completion time on run termination.
- [ ] `SavedQueryLibrary` "Used in N projects" count is derived from `len(project_context)` (distinct projects), not total execution count.
*End of NexusScholar v1 Product Specification. All prior design passes, critique documents, and resolution notes are superseded by this document. Questions about specific decisions should reference the section and rule number above.*
