# NexusScholar — Laravel + Frontend Implementation Plan

**Purpose:** Practical engineering plan mapped to backend, API, frontend, and QA ownership.  
**Source basis:** `nexusscholar_component_checklist_v1.2.md` and the v1.1 PRD-derived component model.  
**Recommended stack assumption:** Laravel 11, PostgreSQL, Laravel Queues/Horizon, Redis, Laravel Sanctum, Vue 3 or React SPA frontend, Tailwind, TypeScript.

---

## Team lanes

| Lane | Primary ownership | Typical outputs |
|---|---|---|
| Backend platform | Laravel/backend | Schema, jobs, services, policies, queues, exports, audit log |
| API layer | Laravel/backend | REST/JSON endpoints, DTOs/resources, validation, auth, polling responses |
| Frontend app shell | Frontend | Routing, sidebar, shared layout, permissions, global stores |
| Frontend workflow UI | Frontend | Query builder, run monitor, results, lock flow, screening, export |
| QA | QA + dev support | State-machine tests, E2E smoke tests, regression coverage |
| Product/design | PM/design | Copy review, gated-state review, acceptance signoff |

---

## Architecture split

### Backend responsibilities

- Own canonical project lifecycle state.
- Own search-run orchestration and per-provider progress state.
- Own deduplication, representative record election, `WorkIdSet`, and completeness payload generation.
- Own immutable lock snapshot and all post-lock read behavior.
- Own screening persistence, conflict routing, export generation, and audit trails.
- Expose only UI-ready payloads; do not force frontend to reconstruct lifecycle rules from raw tables.

### API responsibilities

- Normalize backend state into stable response contracts for UI consumption.
- Provide aggregate view-model endpoints for complex screens, not only low-level CRUD.
- Return explicit gated/disabled state booleans and reason strings where possible.
- Return polling metadata like `last_updated_at`, `poll_interval_seconds`, and terminal state flags.

### Frontend responsibilities

- Render the exact state machines defined by the PRD/checklist.
- Treat API booleans and enums as the source of truth for gating, not local heuristics.
- Own local interaction states: drawers, modals, loading skeletons, keyboard shortcuts, responsive behavior.
- Preserve usability and accessibility rules, including mobile gesture constraints and focus handling.

---

## Recommended Laravel domain structure

### Core domains

- `Project`
- `SearchRun`
- `ProviderProgress`
- `Work`
- `WorkSourceRecord`
- `CorpusSnapshot`
- `ScreeningDecision`
- `ConflictRecord`
- `SavedQuery`
- `ExportJob` / `ExportHistory`
- `AuditEvent`
- `NotificationEvent`

### Suggested Laravel folders

- `app/Models`
- `app/Policies`
- `app/Actions`
- `app/Services/Search`
- `app/Services/Dedup`
- `app/Services/Screening`
- `app/Services/Export`
- `app/Services/Audit`
- `app/Jobs`
- `app/Http/Controllers/Api`
- `app/Http/Requests`
- `app/Http/Resources`
- `app/Data` or DTO layer for UI-specific response objects

---

## Delivery sequence

## Phase 0 foundation

### Backend ownership

- Create schema for projects, runs, provider progress, works, source records, snapshots, screening decisions, conflicts, saved queries, exports, notifications, audit events.
- Implement enums for project lifecycle, run lifecycle, provider status, screening decision, export format, conflict source, role.
- Implement policies for Owner / Reviewer / Observer.
- Configure Redis queue + Horizon.
- Implement audit event writer service.
- Implement notification event writer service.

### API ownership

- Create auth/session endpoints.
- Create project list/create/read/update endpoints.
- Create team membership endpoints.
- Create settings endpoints for provider configuration and required-provider flags.

### Frontend ownership

- Build app shell, auth bootstrap, route guards, permission store.
- Build top-level sidebar and project sub-nav.
- Build shared primitives: modal, drawer, chip, banner, skeleton row, empty-state block, tooltip, confirmation input.

### QA ownership

- Verify role gating on protected endpoints.
- Verify enum/state serialization is stable across API responses.
- Verify app shell reflects role permissions correctly.

### Phase 0 dependency notes

- Frontend should not hardcode role capability maps; consume permission payload from API.
- Audit logging should be integrated before irreversible actions are built.

### Phase 0 handoff outputs

- Stable base schema
- Authenticated app shell
- Policy-tested API foundation
- Shared UI component library

---

## Phase 1 search and query workflow

### Backend ownership

#### Saved queries
- Create `saved_queries` model, lineage support via `source_query_id`, and `project_context` usage tracking.
- Implement service method for `used_in_projects_count` from distinct project IDs.
- Enforce user-scoped visibility.

#### Query submission and run creation
- Implement `CreateSearchRunAction`.
- Persist exact submitted YAML immutably on each run.
- Store provider selection, query metadata, and run state.
- Dispatch parent run job and downstream provider jobs.

#### Provider orchestration
- Build adapter contracts for OpenAlex, Semantic Scholar, PubMed, IEEE, arXiv, DOAJ, Crossref.
- Normalize provider responses to a common raw record shape.
- Persist provider progress incrementally as jobs resolve.
- Compute terminal run state: `completed`, `partial`, `failed`, etc.
- Expose poll interval from config, default 10 seconds.

### API ownership

#### SavedQuery endpoints
- `GET /api/saved-queries`
- `POST /api/saved-queries`
- `POST /api/saved-queries/{id}/fork`
- `PATCH /api/saved-queries/{id}`
- `DELETE /api/saved-queries/{id}`
- Response includes: `used_in_projects_count`, `is_fork`, `source_query_id`, `last_used_at`, `tags`

#### Query/run endpoints
- `POST /api/projects/{project}/runs`
- `GET /api/projects/{project}/runs`
- `GET /api/runs/{run}`
- `GET /api/runs/{run}/provider-progress`
- `POST /api/runs/{run}/retry-provider/{provider}`
- Run detail payload includes: `status`, `can_view_results`, `can_retry_failed_providers`, `last_updated_at`, `poll_interval_seconds`, `is_terminal`

### Frontend ownership

#### QueryBuilderForm
- Build visual query builder with YAML editor synchronization.
- Implement `synced`, `manual-edit`, `parse-error`, `diverged` local states.
- Add global Query Library load drawer button.
- On library selection: auto-close drawer, repopulate form, reset state to `synced`.
- On `manual-edit`: show discard warning before load.
- On `parse-error`: block library load.

#### SavedQueryLibrary
- Build global page and drawer variant.
- Render `Used in N projects`, not execution count.
- Render empty/loading/populated/filter states.

#### Run monitor
- Build run list and run detail pages.
- Build `RunStatusCard` and `ProviderProgressTable`.
- Show 7 skeleton rows before provider data resolves.
- Show polling trust timestamp with API-provided interval.
- Freeze polling when run enters terminal state.

### QA ownership

- E2E: create project → build query → submit run → observe progress polling.
- Verify YAML snapshot is immutable per run.
- Verify library drawer warning/blocking rules.
- Verify polling interval default is 10s and configurable from backend.

### Phase 1 key contracts

| Contract | Owned by | Consumed by |
|---|---|---|
| `used_in_projects_count` | Backend service/API | Query Library UI |
| `poll_interval_seconds` | Backend config/API | ProviderProgressTable |
| `can_view_results` | Backend/API | RunStatusCard |
| `is_terminal` | Backend/API | Polling UI |

---

## Phase 2 dedup and results browser

### Backend ownership

#### Dedup engine
- Implement ID-first matching pipeline.
- Build `WorkIdSet` aggregator for DOI / PMID / arXiv ID / S2 ID.
- Assign confidence tier: High / Medium / Low.
- Elect representative source record.
- Compute 6-field completeness payload: DOI, ABS, AUTH, VEN, CIT, ORCID.
- Persist both normalized Work and raw source records.

#### Results aggregation
- Build project-level result stats service: raw count, unique count, dedup savings, provider breakdown, year distribution.
- Build filter query layer for provider/year/open-access/has-abstract/completeness.

### API ownership

- `GET /api/projects/{project}/results/summary`
- `GET /api/projects/{project}/works`
- `GET /api/projects/{project}/works/{work}`
- `GET /api/projects/{project}/works/{work}/sources`
- Return UI-shaped fields for micro-grid cells, cell status labels, confidence pill, and 4-chip identifier row.

#### Example response shape — work card payload
- `id`
- `title`
- `authors_preview`
- `venue`
- `year`
- `abstract_preview`
- `source_count`
- `completeness_grid[]` with `{key, label, present, tooltip, severity}`
- `identity_confidence` with `{level, basis, tooltip}`
- `screening_state`

### Frontend ownership

#### Results browser
- Build `CorpusStatsBar`, `CorpusFilterSidebar`, results list/grid, active filter chips.
- Build `WorkCard` micro-grid with per-cell tooltip.
- For DOI/ABS missing cells, render extended tooltip copy about screening impact.
- Collapse micro-grid to `[N/6]` at card width < 260px.
- Build `WorkDetailDrawer` and `SourcesPanel` with 4-chip identity row.
- Keep source rows and representative-row highlighting aligned to API payload.

### QA ownership

- Verify same Work shows consistent confidence pill + 4-chip identity row across card/drawer/panel.
- Verify no frontend recomputation is needed for micro-grid or identity confidence.
- Verify filter combinations produce stable counts.

### Phase 2 key contracts

| Contract | Owned by | Consumed by |
|---|---|---|
| `completeness_grid[]` | Backend/API | WorkCard |
| `identity_confidence` | Backend/API | WorkCard |
| `identifier_row[]` | Backend/API | WorkDetailDrawer, SourcesPanel |
| `results_summary` | Backend/API | CorpusStatsBar |

---

## Phase 3 lock flow and lifecycle state

### Backend ownership

- Implement `LockCorpusAction`.
- Validate no active runs before lock.
- Validate typed confirmation.
- Create immutable corpus snapshot.
- Persist lock metadata and snapshot counts.
- Emit audit event and notification event.
- Expose `corpus_locked` signal in project state payload.
- Expose post-lock banner timing metadata: `locked_at`, `post_lock_banner_dismissible_at`, `post_lock_banner_auto_hide_at`.

### API ownership

- `POST /api/projects/{project}/lock`
- `GET /api/projects/{project}/lifecycle`
- `GET /api/projects/{project}/lock-preview`
- Lifecycle endpoint returns:
  - `project_status`
  - `corpus_locked`
  - `current_stage`
  - `banner_state`
  - `banner_message`
  - `banner_severity`
  - `banner_dismissible`
  - `post_lock_chip_visible`
  - `can_lock`
  - `lock_block_reason`

### Frontend ownership

#### Overview and lock flow
- Build `LifecycleStatusBar` and lifecycle banner strip from API lifecycle payload.
- Build `LockCorpusModal` with typed confirmation and acknowledgement checkbox.
- Disable lock button when API returns `can_lock = false`.
- Build post-lock banner machine exactly from API-driven timing state.
- Show permanent chip after dismiss or auto-hide.

#### Cross-team dependency
- Export Builder consumes `corpus_locked`; frontend should not infer lock status from nav availability or timestamps.

### QA ownership

- Verify lock is impossible while a run is active.
- Verify snapshot immutability.
- Verify post-lock banner timing state changes across the three windows.
- Verify `corpus_locked` drives both Overview and Export pre-lock/post-lock behavior.

### Phase 3 key contracts

| Contract | Owned by | Consumed by |
|---|---|---|
| `corpus_locked` | Backend/API | Lock UI, Export UI, Nav gating |
| `banner_state` | Backend/API | Lifecycle banner |
| `can_lock` | Backend/API | Lock action button |
| `lock_preview` | Backend/API | LockCorpusModal |

---

## Phase 4 screening workflow

### Backend ownership

#### Screening state
- Create screening queue from locked snapshot only.
- Support stage-specific queues and decisions.
- Persist Include / Exclude / Maybe / Undo actions.
- Persist exclusion reasons and free text.

#### AI recommendation mode
- Persist AI recommendation, confidence, and optional reasoning.
- Route conflicts into a dedicated conflict queue.
- Preserve original AI outputs after human override.
- Suppress conflict bucket entirely when mode is solo.

#### Completion rules
- Stage completion requires both main queue and conflict queue completion.
- Unlock next stage only after prior stage criteria pass.

### API ownership

- `GET /api/projects/{project}/screening/session`
- `POST /api/projects/{project}/screening/decision`
- `POST /api/projects/{project}/screening/undo`
- `GET /api/projects/{project}/screening/progress`
- `GET /api/projects/{project}/screening/conflicts`
- `POST /api/projects/{project}/screening/conflicts/{conflict}/resolve`

#### Screening session payload should include
- current work payload
- `mode`
- decision shortcuts metadata
- recommendation block
- reasoning-present vs reasoning-absent layout flags
- confidence presence flag
- `show_conflict_badge`
- `conflict_count`
- `stage_complete`

### Frontend ownership

#### ScreeningWorkspace
- Build desktop workflow, keyboard shortcuts, undo, skip, reason picker.
- Build mobile mode with vertical gestures only.
- Add one-time fullscreen prompt and first-session gesture explainer.

#### ScreeningProgressBar
- Render counts and remaining totals.
- Hide conflict badge entirely in solo mode.
- Show conflict badge only in AI-Recommendation or future Collaborative mode.

#### ConflictQueue
- Build Layout A for reasoning present.
- Build Layout B for reasoning absent: muted `No reasoning provided`, full abstract below, `—` confidence slot with tooltip.
- Build soft/hard conflict comparison view.
- Build resolution flow.

### QA ownership

- Verify screening only works after lock.
- Verify mobile gestures do not conflict with browser back/forward navigation.
- Verify conflict badge absence in solo mode.
- Verify stage completion requires conflict resolution.
- Verify reasoning-absent layout renders without broken spacing.

### Phase 4 key contracts

| Contract | Owned by | Consumed by |
|---|---|---|
| `show_conflict_badge` | Backend/API | ScreeningProgressBar |
| `reasoning_layout` | Backend/API | ConflictQueue |
| `confidence_available` | Backend/API | ConflictQueue |
| `stage_complete` | Backend/API | Screening completion UI |

---

## Phase 5 export workflow

### Backend ownership

- Build export scope resolver from locked snapshot + screening decisions.
- Build generators for BibTeX, RIS, CSV, Excel, JSON.
- Support preliminary pre-lock exports with non-citable flag.
- Persist export history and downloadable artifacts.
- Gate future formats without exposing half-built endpoints.

### API ownership

- `POST /api/projects/{project}/exports/preview`
- `POST /api/projects/{project}/exports`
- `GET /api/projects/{project}/exports/history`
- `GET /api/exports/{export}/download`
- `GET /api/projects/{project}/export-options`
- Export options payload includes live formats, gated formats, plain-language tooltip copy, and `corpus_locked`-aware banner state.

### Frontend ownership

- Build 3-step `ExportBuilder`.
- Render multi-format selection and preview tabs.
- Render gated cards as intentionally unavailable.
- Use plain-language tooltip for standard users; technical tooltip only for Admin/Owner.
- Show pre-lock non-citable banner based on `corpus_locked`.
- Build export history list and duplicate-settings action.

### QA ownership

- Verify 5 live formats generate valid files.
- Verify gated formats never appear broken.
- Verify pre-lock banner disappears immediately after lock state changes.

### Phase 5 key contracts

| Contract | Owned by | Consumed by |
|---|---|---|
| `available_formats[]` | Backend/API | ExportBuilder |
| `gated_formats[]` | Backend/API | ExportBuilder |
| `prelock_banner_visible` | Backend/API | Export UI |
| `export_history[]` | Backend/API | Export History UI |

---

## Phase 6 settings, audit, notifications

### Backend ownership

- Implement provider key management, validation, rotation, and required-provider flags.
- Implement team member invite/change/remove flows.
- Implement audit log visibility rules.
- Implement notification fan-out for run completion/failure/conflict-ready events.

### API ownership

- Provider settings endpoints
- Team management endpoints
- Audit log endpoints
- Notifications list/read endpoints

### Frontend ownership

- Build `ProviderAPIKeyManager`, `TeamMemberList`, notification center, audit log list.
- Respect role-based visibility in UI, but treat backend auth as authoritative.

### QA ownership

- Verify Observer cannot access audit logs.
- Verify Owner-only settings actions are blocked server-side and UI-side.
- Verify required-provider failure changes lifecycle banner severity.

---

## API design recommendations

### Use view-model endpoints for workflow screens

Prefer workflow endpoints like:
- `/projects/{id}/overview`
- `/projects/{id}/screening/session`
- `/projects/{id}/export-options`

over forcing the frontend to stitch together 7 CRUD calls. This app has many state machines; view-model endpoints reduce divergence bugs.

### Keep enums explicit

Use string enums in API responses:
- `project_status: draft | active_search | corpus_locked | screening | reporting | archived`
- `run_status: queued | running | partial | completed | failed | cancelled`
- `identity_confidence: high | medium | low`
- `banner_severity: info | success | warning | error`

### Return booleans for permission/gating

Examples:
- `can_lock`
- `can_view_results`
- `can_retry_failed_providers`
- `show_conflict_badge`
- `banner_dismissible`
- `can_export_prelock`

This keeps frontend logic thin and consistent.

---

## Frontend state management recommendations

### Global stores

- Auth / current user / permissions
- Active project summary
- Notifications
- UI preferences (non-critical only)

### Screen-local state

- QueryBuilder YAML sync state
- Drawer open/close state
- Lock modal typed confirmation input
- Screening session keyboard help visibility
- Export stepper state

### Do not store as frontend-derived truth

- `corpus_locked`
- lifecycle banner severity
- stage completion
- results availability
- conflict badge visibility
- provider poll interval

These should always come from API payloads.

---

## Suggested ownership by component

| Component / surface | Backend | API | Frontend |
|---|---|---|---|
| `ProjectWizard` | project creation rules, templates | create/list/read project endpoints | wizard UI, validation, step flow |
| `LifecycleStatusBar` | lifecycle state service | lifecycle payload | bar rendering, chip rendering |
| lifecycle banner | severity + dismissibility rules | banner payload | banner UI |
| `QueryBuilderForm` | query schema validation | run create endpoint | visual builder, YAML sync UI |
| `SavedQueryLibrary` | saved query storage + project counts | saved query endpoints | page + drawer UI |
| `RunStatusCard` | run state machine | run detail payload | card UI |
| `ProviderProgressTable` | provider progress updates | progress endpoint | polling UI, skeletons |
| `WorkCard` | completeness + confidence computation | work list payload | card rendering + tooltips |
| `WorkDetailDrawer` | normalized work + identifiers | work detail endpoint | drawer UI |
| `SourcesPanel` | raw source aggregation | source panel payload | panel UI |
| `LockCorpusModal` | lock validation + snapshot | lock preview + lock action | modal UI |
| `ScreeningWorkspace` | queue/session generation | screening session endpoint | workspace UI |
| `ConflictQueue` | conflict routing + persistence | conflict endpoints | comparison/resolution UI |
| `ExportBuilder` | export option resolution + file generation | preview/create/history endpoints | stepper UI |
| `ProviderAPIKeyManager` | key storage/validation | provider settings endpoints | settings UI |
| audit log | event persistence + visibility | audit endpoints | log list UI |

---

## Sprint planning recommendation

### Sprint A
- Phase 0 foundation
- project creation
- app shell
- saved query base

### Sprint B
- search run submission
- provider progress polling
- run detail

### Sprint C
- dedup pipeline
- results browser
- Work surfaces

### Sprint D
- lock flow
- lifecycle banner system
- post-lock read behavior

### Sprint E
- screening desktop + mobile
- conflict queue
- progress/completion rules

### Sprint F
- export builder
- export history
- settings/audit/notifications hardening

---

## Delivery risks to manage

- Frontend deriving lifecycle or lock rules locally instead of consuming API flags.
- Inconsistent `WorkIdSet`/micro-grid rendering if backend does not ship UI-ready payloads.
- Polling drift if interval is hardcoded in frontend.
- Conflict queue bugs if reasoning-absent layout is treated as an error state.
- Cross-team delay if `corpus_locked` is not exposed early as a stable API contract.

---

## Minimum staffing assumption

- 1 backend engineer focused on schema, queues, services, exports.
- 1 backend/API engineer focused on endpoints, policies, response contracts.
- 1 frontend engineer focused on app shell, query/runs/results.
- 1 frontend engineer focused on lock/screening/export workflows.
- 1 QA engineer or shared QA ownership across the team.

This split is enough to run backend/API and UI work in parallel once the Phase 0 contracts are settled. [file:3]
