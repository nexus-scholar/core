# NexusScholar: Core Package vs. Host App Boundary Plan

**Purpose:** This document reconciles the gap between the UI/UX Product Requirements Document (PRD) and the current database schema in the `nexus-scholar/core` package.
**Design Constraint:** To minimize the blast radius of changes to the core package (and its extensive test suite), we adopt a strict boundary: the Core Package only manages the *science* and *domain invariants* of an SLR, while the Host App manages the *SaaS wrapper*, *UX*, and *Identity*.

---

## 1. The Core Philosophy

- **`nexus-scholar/core` (The Engine):** Responsible for scientific integrity, reproducibility, search orchestration, deduplication rules, blinding, and conflict resolution logic. It knows nothing about Users, Passwords, Stripe billing, or Web UI components.
- **Host Laravel Application (The SaaS):** Responsible for Authentication (Sanctum/Breeze/Jetstream), Authorization (Policies, Roles), UX state conveniences (Saved Queries), Analytics, and triggering notifications (via WebSockets/Emails).

---

## 2. Minimal Viable Core Updates (Modifications to `nexus-scholar/core`)

To support the PRD without rewriting the entire core package, we need surgical, domain-critical additions to the core package's migrations and models. These are required because they affect the scientific validity of the review.

### A. Project Lifecycle Enforcement
The core domain must know if a project is locked, as you cannot legally append new search runs to a locked Systematic Literature Review.
*   **Schema Addition:** Create a new migration in `core` to `ALTER TABLE projects`.
    *   `ADD COLUMN status VARCHAR(32) DEFAULT 'draft'` (draft, active_search, corpus_locked, screening, reporting, archived).
    *   `ADD COLUMN locked_at TIMESTAMP NULL`.
    *   `ADD COLUMN archived_at TIMESTAMP NULL`.
*   **Domain Impact:** The `SearchAggregator` and `WorkRepository` must throw exceptions if a write operation is attempted on a project where `locked_at IS NOT NULL`.

### B. Corpus Snapshot / Baseline
The PRD requires an immutable lock. 
*   **Schema Addition:** Instead of a massive new snapshot table, we add a `locked_in_project` boolean or associate the locked state directly with the project's `dedup_clusters` at the time of locking. 
*   **Domain Impact:** A `LockCorpus` command that finalizes the dedup clusters and prevents further `absorb()` operations for that project.

### C. Conflict Records (Screening Domain)
The AI-Recommendation mode and collaborative screening require tracking disagreements.
*   **Schema Addition:** Create a `conflict_records` table in `core`.
    *   `project_id`, `work_id`, `stage`, `status` (unresolved, resolved), `resolved_by`, `resolved_at`, `resolution_decision`.
*   **Domain Impact:** Update the `ScreeningDecision` service to detect when incoming decisions (from AI or humans) conflict, automatically writing to `conflict_records`.

---

## 3. Host Application Responsibilities (Built Outside the Core)

The following tables and features are strictly the responsibility of the Host Application. The core package remains completely agnostic to them.

### A. Identity, Roles, and Team Management
*   **Implementation:** The Host App creates a `users` table and a `project_user` pivot table for team management.
*   **Bridge:** The Host App writes a migration: `ALTER TABLE projects ADD COLUMN owner_user_id UUID REFERENCES users(id)`.
*   **Access Control:** The Host App implements Laravel Policies (`ProjectPolicy`) to enforce Owner/Reviewer/Observer rules before ever calling the `nexus-scholar/core` classes.

### B. Saved Query Library
*   **Implementation:** The Host App creates a `saved_queries` table (`id`, `user_id`, `name`, `yaml_payload`, `last_used_at`).
*   **Bridge:** When a user selects a saved query from the UI, the Host App simply reads the `yaml_payload` and passes it to the core's `SearchAcrossProviders` command. The core package never needs to know the query was "saved" in a library.

### C. Audit Logs & Notifications
*   **Implementation:** The Host App creates `audit_logs` and `notification_events` tables.
*   **Bridge:** The core package dispatches standard Laravel Events (e.g., `Nexus\Search\Event\CorpusLocked`, `Nexus\Search\Event\SearchRunFailed`). The Host App registers listeners for these events and writes to its own audit and notification tables. This keeps the core package free of heavy logging infrastructure.

### D. Export History Tracking
*   **Implementation:** The Host App creates an `export_history` table.
*   **Bridge:** The core package's Dissemination module generates the physical file (CSV, BibTeX) and returns the file path/stream. The Host App serves the download to the user and records the download event in its own history table.

---

## Summary of Next Steps for Development

1.  **Core Package Devs:** Draft 2-3 minimal migrations in `nexus-scholar/core` to add `status`/`locked_at` to projects, and create `conflict_records`. Add the necessary domain validation rules and tests.
2.  **Host App Devs:** Spin up a fresh Laravel 11 project, install `nexus-scholar/core` via Composer, run `php artisan vendor:publish` and `php artisan migrate`. Then, create the SaaS-specific migrations (`users`, `saved_queries`, `audit_logs`) and build the API/Frontend over the top.