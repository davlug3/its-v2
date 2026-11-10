# Changelog — IHOMS Ticketing System Rebuild

All notable changes to this project are recorded here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
The project does not follow semantic versioning yet.
Versions start at `0.1.0` when the scaffold is ready.

---

## [Unreleased]

### Added

- **Architecture evaluation and cleanup (2026-11-10).**
  - Split the 1,369-line `TicketService` God object into two focused services:
    - `TicketQueryService` (658 lines) — read-side: listing, aggregation, log retrieval.
    - `TicketMutationService` (739 lines) — write-side: create, edit, assign, comment,
      approve/disapprove/undo, batch approve/disapprove, close.
    `TicketController` now injects both via constructor; no public method moved.
  - Added Yup schemas for the remaining forms:
    - `ticketCreateSchema` — new ticket form (`NewTicketPage`).
    - `ticketEditSchema` — ticket edit form (`TicketDetailPage`).
    - `passwordResetSchema` — admin password reset (`AdminUsersPage`).
    - `categoryNameSchema` — category create/edit (`AdminCategoriesPage`).
    Validation now runs through `schema.validate()` with `abortEarly: false`, replacing
    inline `if (...)` checks and native `required` attributes.
  - `README.md` updated to reflect the built application (it previously claimed the
    scaffold did not exist) and to note the layering simplification.

### Changed

- **CI pipeline (2026-11-10).**
  - Switched frontend install from `npm` to `pnpm` to match the `pnpm-lock.yaml`
    lockfile and the Docker `node` service.
  - Added `pnpm lint` and `pnpm typecheck` steps (previously only `npm run build` ran).
  - Set `CI=true` for `pnpm install` so non-interactive lockfile verification works.
- **`eslint.config.mjs`** now ignores `.agents/**` and `skills-lock.json` so the
  vendored skill assets do not fail the lint gate.

### Fixed

- **`NewTicketPage` unused import.** Removed the unused `newIdempotencyKey` import
  after the form switched to schema-based validation.

- **Git repository (2026-11-04).**
  - Initialized with branch `main`.
  - Added `.gitignore` for secrets, PHP, Node, tests, editors, and local scratch files.
  - `.env` is ignored and is not tracked.
  - First commit: `docs: add rebuild plan, decisions, and production baseline`.

- **Project records.**
  - `PLAN.md` — 13-phase plan, progress tracker, risk list, open items.
  - `DECISIONS.md` — approved decisions with reasons and evidence.
  - `CHANGELOG.md` — this file.
  - `README.md` — project overview and document map.

- **Production baseline captured (2026-11-04).**
  - DDL for all 13 tables, saved at `docs/baseline/its_ddl.sql`.
  - Full 69-row `mods` registry export, saved at `docs/baseline/mods.json`.
  - Row counts, max IDs, and engine type for every table.
  - Neither file contains credentials or personal data.

- **Decisions recorded.**
  - C1 — protect the 8 real acl-9 users, not `users.id = 1`.
  - C2 — protect `categories.id = 1` and hide it from the admin list.
  - C6 (revised) — identity, location, and state come from `jblmrhpayroll`. Retire local copies.
  - C7 — override the broken `change_password` module row.
  - C8 — retire `its.users` and `its.locations`. Add `user_profile` table.
  - D27 — staff-create tickets write `assigned_gid = 0`.
  - D53 — keep `assigned_gid` as legacy group ID; add `units_count`.
  - D74 — in-app notification bell.
  - T1 — teams fully retired.

- **Phase 1 scaffold complete (2026-11-04).**
  - Laravel 13.40 with strict REST API skeleton.
  - Vue 3.5 single-page application with Vite 8 and TypeScript 5.9.3 (strict).
  - Tailwind CSS 4 with the Attio design tokens from `DESIGN.md`.
  - shadcn-vue 2.x copied components + Reka UI 2.x.
  - Yup 1.7.1 with `@vee-validate/yup` for forms.
  - JWT authentication with `php-open-source-saver/jwt-auth`.
  - Docker Compose with `app`, `nginx`, and optional `node`, `db`, `queue`, `redis`.
  - Pint, PHPStan/Larastan, ESLint, and Prettier configured.
  - `docker compose up` serves the SPA on port 8080.
  - No secret committed. `.env` is ignored.

- **Dev container (2026-11-04).**
  - Added `.devcontainer/devcontainer.json` and `.devcontainer/docker-compose.yml`.
  - Added a `dev` build target to `.docker/php/Dockerfile` with Node and git.
  - The development image includes `pdo_sqlite`, so PHPUnit can use the default test configuration.

- **Package manager changed to pnpm (2026-11-04).**
  - Decision U7. `package-lock.json` removed, `pnpm-lock.yaml` committed.
  - All scripts, Dockerfiles, and the dev container use `pnpm`.
  - Node service switched to `node:24-bookworm` for native binding compatibility.

- **Phase 2 database complete (2026-11-04).**
  - 8 additive migrations on a staging copy of `its`.
  - New tables: `user_profile`, `auth_audit`, `admin_audit`, `email_ingest_log`.
  - New columns on `tickets`, `tickets_log`, `attachements`, `categories`, `groups`, `notifications`.
  - 17 Eloquent models. Legacy tables and columns untouched.
  - `its.users` and `its.locations` are read-only. No columns added to them.

- **Phase 6 administration complete (2026-11-05).**
  - Admin user list reads from PIS with ITS attributes overlaid.
  - Access, supervisor, fpc toggle, soft delete, and password reset endpoints.
  - Category CRUD with protected id 1.
  - Permission endpoint lists ACLs.
  - Admin audit writes on all admin mutations.
  - Supervisor tree with cycle detection.

- **Dashboard and list parity slices (2026-11-09).**
  - Added `GET /api/v1/tickets/summary` dashboard aggregates (SQL counts) for dashboard cards.
  - Added “Closed by you” view (`scope=closed_by_me`, `GET /tickets` filter, route `/tickets/closed-by-me`, nav entry for ACL 2+).
  - Added all-tickets search filters (ticket ID, requester, location, category, subject, issue, closed-by), 500-row search cap, lazy location picker, and “Search Results”/reset/print actions.
  - Added legacy `#ticket=<id>` deep-link redirect to `/tickets/<id>`.

- **Hardening slices (2026-11-09).**
  - Added `Idempotency-Key` to remaining mutating SPA actions and a shared `newIdempotencyKey()` helper.
  - Added `NotificationSanitizer` and applied it to notification reads and the `notifications:redact-legacy` command.
  - Added `SecureHeaders` middleware for baseline security headers (CSP, frame/referrer/nosniff, permissions policy; HSTS on secure requests).
  - Added `tests/Unit/NotificationSanitizerTest.php`.

### Fixed

- **Dashboard metrics (D46–D48, partial D49 context).**
  - Replaced the simplified dashboard counts with legacy-style aggregate cards.
  - Fixed the group-closed-today metric with a single aggregate query (D46).
  - Added distinct “Closed by you / Today” and “Closed by you / This year (YTD)” cards (D47, D48).
  - Kept the SPA dashboard free of a dead Tech Status link (D49).

- **Closed Tickets By You (D52).**
  - Added a scoped list for tickets resolved by the current user with `state_id = 2`.

- **Live monitoring (D51).**
  - Updated `MonitoringPage.vue` to refresh data in place on a 5-second interval, avoid overlapping requests, and pause/resume polling on tab visibility and window focus.

- **Ticket list scopes (Phase 7 progress).**
  - Added `assigned_to_me`, `unassigned`, `involving_me`, `end_user_tickets`, and `tickets_by_supervisor` API scopes with SPA pages/routes.
  - Added “Tickets involving you” (legacy created-by-user getter semantics; D62 naming/scope decision remains open), “By supervisor”, and an end-user tickets view.
  - Added a retired `assigned_to_group` page with a clear explanation (teams retired).

### Changed

- **Ticket creation source (D10/D11 hardening).**
  - Staff `POST /tickets` now infers staff/end-user source from ACL instead of always treating the request as staff.

### Added (tests)

- Added `tests/Unit/SecureHeadersTest.php` and `tests/Feature/RouteSecurityTest.php` for baseline security headers and ACL/auth route middleware coverage.

- **Attachment validation hardening.**
  - Tightened upload content-type checks using detected MIME and stored the detected MIME when available.

### Security

- **Baseline response headers.**
  - Added `SecureHeaders` middleware with CSP, `X-Frame-Options`, `Referrer-Policy`, `X-Content-Type-Options`, and `Permissions-Policy`.
  - Added HSTS only for secure requests.

- **Notification secret redaction.**
  - Redacted credential-like patterns in notification list responses (defense in depth).
  - Added `notifications:redact-legacy` command (dry-run by default) for legacy cleanup.

### Changed

- **UI library changed to shadcn-vue (2026-11-04).**
  - Decision U1. PrimeVue 5 is under the PrimeUI commercial license and needs a key.
  - shadcn-vue 2.x with Reka UI 2.x replaces it. Both are MIT.
  - Components are copied into `resources/js/components/ui/` and owned by this project.
  - `ARCHITECTURE.md`, `PLAN.md`, `README.md`, `DECISIONS.md`, and `DESIGN.md` updated.
  - Local `DESIGN.md` component map replaced with shadcn-vue components.

- **Frontend dependency decisions.**
  - U2: TypeScript `5.9.3`. TypeScript 7 is blocked by `@volar/typescript` peer `<7`.
  - U3: Vue Router `5.4.0`. The `4.x` note was out of date.
  - U4: `vee-validate` for forms.
  - U5: Yup `1.7.1` with `@vee-validate/yup` is the schema library.
    - Zod is removed.
    - Zod below `4.6.6` has a high-severity advisory with no 3.x fix (GHSA-mrhx-c7xc-m3w5).
    - Yup has no known advisories.
    - `npm audit` reports 0 vulnerabilities.
  - Icons use `@lucide/vue` `1.67.0`. `lucide-vue-next` is deprecated.

- **Frontend language is TypeScript.**
  - `ARCHITECTURE.md` §3.1 now requires TypeScript in strict mode.
  - `ARCHITECTURE.md` §5 and §11.2 add the TypeScript version and the `vue-tsc` type check.
  - `ARCHITECTURE.md` §19.2 records the requirement.
  - `PLAN.md` stack table and Phase 1 add TypeScript, `tsconfig.json`, and `npm run typecheck`.
  - `PLAN.md` Phase 13 adds the type check to CI.
  - `README.md` stack and setup steps add TypeScript.
  - `DECISIONS.md` A2 records the requirement and its effect.
  - `DESIGN.md` application notes require typed components.
  - Rule: no plain JavaScript files in `src/`. Component files use `<script setup lang="ts">`.

- **`ARCHITECTURE.md`.**
  - Added "Approved Rebuild Decisions (2026-11-04)".
  - Recorded the second database connection for the PIS sync.
  - Recorded that teams are retired.

- **`DESIGN.md`.**
  - Added "Project Application Notes — IHOMS Ticketing System".
  - Recorded mobile-first rules for the helpdesk use case.

### Not changed

- **Production database.**
  - No row, column, index, or table was changed.
  - Every query in this phase was read-only (`SHOW`, `SELECT`).

- **`PROMPT.md`.**
  - Treated as the behavioral contract. Not edited.
  - Conflicts with live data are recorded in `PLAN.md` §6 and `DECISIONS.md`.

- **`/home/dave/its-helpdesk`.**
  - Ignored completely. No file was copied or modified.

### Security

- **Repository holds live development credentials.**
  - `.env` contains the development database host, user, and password.
  - `PROMPT.md` Appendix C quotes the legacy fallback password.
  - The directory is not a git repository and has no `.gitignore` yet.
  - Action: create `.gitignore` before `git init`, and never commit `.env` or `PROMPT.md` secrets. Rotate the credentials at cutover (D19).

### Known issues

- Duplicate usernames make login ambiguous for some accounts, including `admin` (3 rows). D72 applies. Needs a data-owner decision.
- 22 users have `payroll.department = 0` and `location_id = 0`. The PIS sync must fail closed for them.
- 5 `notifications` rows contain plaintext passwords and malformed URLs. They must be redacted before display.
- 3 tickets have orphan `assigned_gid` values (66, 74, 100). They are left as legacy data.

---

## Notes on this file

### What to record

Record a change when it affects:

- Behavior that a user can see.
- The database schema or data.
- The public API.
- Authentication or authorization.
- Deployment, hosting, or release settings.
- A recorded decision.

### What not to record

Do not record:

- A suggestion that Dave has not approved.
- A change that exists only in a plan.
- A test run with no code change, unless the result changed a decision.

### Format

Use these headings under `[Unreleased]`:

- `Added` — new features, files, or records.
- `Changed` — changes to existing behavior.
- `Deprecated` — features marked for removal.
- `Removed` — features removed with approval.
- `Fixed` — defect repairs.
- `Security` — vulnerability repairs.
- `Not changed` — items a reader may expect to change but did not.

Write each entry in ASD-STE100 style.
Use short sentences.
Use active voice.
Name the files.
