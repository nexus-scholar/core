# Task 01: Host App Setup & Migrations

## Objective
Initialize the Host Laravel Application, install the `nexus-scholar/core` package, and create the necessary SaaS-specific database migrations that the core package relies on but does not own.

## Context Constraints (For the AI Coding Assistant)
- **Do not modify the `nexus-scholar/core` package.** Your work is strictly in the Host App.
- **Focus ONLY on this task.** Do not build UI components or API endpoints yet.
- **Workflow Enforcement:** You MUST follow this sequence:
  1. **Start:** Read `docs/UI/core_vs_host_boundary_plan.md` to understand the boundary.
  2. **Implement:** Scaffold the app and migrations.
  3. **Test:** Run `php artisan migrate:fresh --seed` to ensure the schema builds cleanly.
  4. **Commit & Push:** Create a semantic commit (e.g., `chore: setup host app migrations`) and push.

## Specifications
1. Install a fresh Laravel 11 app (or use the existing one if already initialized).
2. Ensure Authentication scaffolding is present (e.g., Laravel Breeze/Sanctum).
3. Create the `users` table and a `project_user` pivot table for team management.
4. Create a migration to `ALTER TABLE projects ADD COLUMN owner_user_id UUID REFERENCES users(id)`.
5. Create the `saved_queries` table (`id`, `user_id`, `name`, `yaml_payload`, `last_used_at`, `project_context`, `source_query_id`).
6. Create the `audit_logs` and `notification_events` tables.
7. Create the `export_history` table.

## Acceptance Criteria
- [ ] Laravel app boots successfully.
- [ ] `php artisan migrate` runs without foreign key or dependency errors.
- [ ] The `projects` table correctly references the `users` table for ownership.
