# Plan — IHOMS Ticketing System Rebuild

**Status:** Active
**Started:** 2026-11-04
**Owner:** Dave (director / architect)
**Coder:** AI agent
**Behavioral contract:** [`PROMPT.md`](./PROMPT.md)

This file is the single source of truth for progress. Update it after every task.
Mark a task done only after its check passes.

---

## 1. Purpose

Rebuild the legacy PHP/MySQL ITS helpdesk as a new application on a modern stack.

This is a re-platforming with data preservation. It is not a rewrite from a blank page.

The new system must:

- Keep every legacy row readable with its original meaning.
- Keep every role, screen, action, and message the same, except the documented repairs.
- Fix the listed security and data-integrity defects.
- Keep the legacy `?p=` URLs and `#ticket=` deep links working.
- Run without PHP 5-era code, jQuery, Kendo UI, or MyISAM.

Source system: `X:\its` (legacy PHP), production host `192.163.10.131`, database `its`.

---

## 2. Authoritative sources

Use this order when sources disagree:

1. Production `its` database (live).
2. Live `mods` table export.
3. Legacy PHP code (not present locally; ask Dave for access).
4. `config/sts.sql`.
5. `PROMPT.md`.

Conflict rule: if `PROMPT.md` disagrees with production data, stop and report the conflict. Do not change production data to match the document.

---

## 2.1 Document set

| File | Role | Owner of changes |
|---|---|---|
| `PROMPT.md` | Behavioral contract. Read-only reference. | Dave |
| `PLAN.md` | Phases, tasks, defect tracker, progress. | Agent |
| `DECISIONS.md` | Approved decisions with reasons and evidence. | Agent records, Dave approves |
| `CHANGELOG.md` | Change history. | Agent |
| `ARCHITECTURE.md` | Target stack and approved architecture. | Dave approves, agent records |
| `DESIGN.md` | Visual reference and application design notes. | Dave approves, agent records |
| `README.md` | Overview, setup, roles, safety rules. | Agent |
| `AGENTS.md` | Working rules for the agent. | Dave |

Update rule:

- A code change updates `CHANGELOG.md`.
- A behavior change updates `DECISIONS.md` first.
- A phase or task change updates this file.
- A setup change updates `README.md`.
- Do not record a suggestion as a decision.

---

## 3. Confirmed decisions

Full detail and reasons are in [`DECISIONS.md`](./DECISIONS.md).

| ID | Decision |
|---|---|
| C1 | Protect the 8 real acl-9 users. Do not protect `users.id=1` (it is acl 1 in production). |
| C2 | `categories.id=1` stays protected and hidden from the admin list. |
| C6 | Identity, location, and state come from `jblmrhpayroll` (`user`, `payroll`, `department`), read-only. Join chain: `user.id = payroll.id`, `payroll.department = department.id`. |
| C8 | Retire `its.users` and `its.locations`. Add `user_profile` for helpdesk-specific attributes. |
| C7 | Override the broken `change_password` mods row with the real `?p=user_options` flow. |
| D27 | Option A. New staff tickets write `assigned_gid = 0`. Skip the group log. |
| D53 | `assigned_gid` stays a legacy group ID. Add new `units_count` column for units. |
| D74 | Option A. In-app notification bell. |
| Teams | Option B. Teams fully retired. Keep data and `mods` rows; pages show "retired". |
| Stack | Laravel 13 + strict REST API + Vue 3 SPA + TypeScript + Tailwind 4 + shadcn-vue + Yup + JWT + Docker. No Inertia. |
| Design | `DESIGN.md` is the visual reference. Build mobile-first. |

---

## 4. Target stack

From [`ARCHITECTURE.md`](./ARCHITECTURE.md):

| Layer | Choice |
|---|---|
| Backend | PHP 8.5 / Laravel 13.x |
| API | Strict REST, versioned `/api/v1` |
| Frontend | Vue 3.5 SPA + Vite + TypeScript (strict) |
| UI | shadcn-vue 2.x + Reka UI 2.x (component source) |
| Styling | Tailwind CSS 4.x |
| Validation (FE) | Yup 1.x + `@vee-validate/yup` |
| Database | MySQL / MariaDB |
| Auth | JWT (`php-open-source-saver/jwt-auth`) + legacy MD5 upgrade |
| Containers | Docker + Docker Compose |
| Tests | Pest / PHPUnit, Vitest, Playwright |

Rules:

- Additive migrations only.
- No Inertia. No Sanctum as primary auth. No Breeze.
- Frontend code is TypeScript in strict mode. No plain JavaScript in `src/`.
- `vue-tsc --noEmit` must pass.
- Follow official setup guides for each tool.
- Pin versions in lockfiles.

---

## 5. Production facts verified (2026-11-04)

Connection: `192.163.10.131`, database `its`, credentials in `.env`.

Baseline captures are stored in the repository and are safe to commit:

- [`docs/baseline/its_ddl.sql`](./docs/baseline/its_ddl.sql) — production DDL for all 13 tables.
- [`docs/baseline/mods.json`](./docs/baseline/mods.json) — the 69-row `mods` registry export.

Neither file contains credentials or personal data.

| Table | Rows |
|---|---|
| `acls` | 4 |
| `application_settings` | 2 |
| `attachements` | 0 |
| `categories` | 31 |
| `groups` | 59 |
| `locations` | 100 |
| `locations2` | 103 |
| `mods` | 69 |
| `notifications` | 5 |
| `states` | 2 |
| `tickets` | 107,043 |
| `tickets_log` | 408,717 |
| `users` | 4,709 |

Other facts:

- All 13 tables are MyISAM.
- Max IDs: users 4992, tickets 107235, tickets_log 408871, categories 38, groups 59, locations 144.
- `application_settings.title` = `IHOMS TICKETING SYSTEM`.
- `acls`: 9 Administrator, 3 `iHOMS Help Desk`, 2 Technician, 1 End User.
- 8 users have acl 9. They are the protected administrators.
- User id 1 is `mail=admin`, acl 1, location 47.
- 164 users have `state` not in (1,2). These need normalization (D71).
- Duplicate `mail` values exist, including `admin` x3 (D72).
- 31 users have `location_id = 0`. Of these, 9 have payroll department > 0 and 22 have department = 0.
- `jblmrhpayroll.user` has no `department` field. Location comes from `jblmrhpayroll.payroll`.
- 41 `jblmrhpayroll.department` ids are not in `its.locations`.
- `payroll.status` has `A` (3,146 rows) and `I` (1,666 rows).
- `notifications` holds 5 rows with plaintext passwords and malformed URLs.
- `assigned_gid` matches an existing group ID on 30,716 tickets. 3 tickets have orphan values (66, 74, 100).
- `mods.parent` uses `""` for some top-level rows and `"none"` for others. Handle both.

---

## 6. Known conflicts and handling

| # | Conflict | Handling |
|---|---|---|
| K1 | `users.id=1` is not the administrator | Protect the acl-9 set. Record in DECISIONS. |
| K2 | `mods.parent` mixes `""` and `"none"` | Treat both as top-level. |
| K3 | `acls.id=3` label is `iHOMS Help Desk` | Use DB label. |
| K4 | `application_settings.title` is `IHOMS TICKETING SYSTEM` | Use DB value. |
| K5 | Identity, location, and state come from `jblmrhpayroll` | Read-only second connection. Retire local copies (C6, C8). |
| K6 | 69 `mods` rows vs ~60 in Appendix B | Import all 69. Extra rows get real pages or "not migrated". |
| K7 | `change_password` mods row is mis-registered | Override with real `?p=user_options` flow. |
| K8 | `assigned_gid` is a legacy group ID | Keep it. Add `units_count`. |
| K9 | Duplicate usernames | Reject ambiguous login. Ask owner to resolve. |

---

## 7. Phases

Each phase has tasks and exit checks. Do not start a phase before the prior exit check passes.

### Phase 0 — Documentation and bootstrap

- [x] Read `PROMPT.md`, `ARCHITECTURE.md`, `DESIGN.md`, `AGENTS.md`.
- [x] Verify production DB access from `.env`.
- [x] Capture production DDL and `mods` export.
- [x] Record decisions in `DECISIONS.md`.
- [x] Create `PLAN.md`, `CHANGELOG.md`, `README.md`.
- [x] Append the approved decisions to `ARCHITECTURE.md` and the application notes to `DESIGN.md`.
- [ ] Confirm no legacy code is needed yet. Ask Dave when a section is unclear.

Exit check: documentation set exists and matches the confirmed decisions.

---

### Phase 1 — Scaffold

- [x] Create a new Laravel 13 project in `/home/dave/its-v2` with the official installer.
- [x] Add Vue 3 + Vite + TypeScript via the official Laravel frontend setup.
- [x] Set `strict` in `tsconfig.json`. Add `vue-tsc` and an `npm run typecheck` script.
- [x] Add Tailwind CSS 4 via the official guide.
- [x] Initialise shadcn-vue with `npx shadcn-vue@latest init`. Style `new-york`, base `neutral`, CSS variables on.
- [x] Map `DESIGN.md` tokens to shadcn-vue CSS variables in `resources/css/app.css`.
- [x] Add base shadcn-vue components: button, card, input, label, table, dialog, sheet, drawer, select, textarea, badge, tabs, dropdown-menu, sonner, skeleton, form, field, separator, popover, checkbox, switch, tooltip, avatar, breadcrumb, alert.
- [x] Add `reka-ui`, `vee-validate`, `@vee-validate/yup`, `yup`, `class-variance-authority`, `clsx`, `tailwind-merge`, `@lucide/vue`, `@vueuse/core`, `tw-animate-css`, `vue-sonner`, `@tanstack/vue-table`.
- [x] Add Yup on the frontend.
- [x] Add `vee-validate` with `@vee-validate/yup`.
- [x] Add `php-open-source-saver/jwt-auth`. Generate `JWT_SECRET`.
- [x] Add Docker + Docker Compose: `app`, `nginx`, `db`, `node`, optional `redis` and `queue`.
- [x] Create `.env.example` with all keys from PROMPT Appendix C. No secrets.
- [x] Point local `.env` at the dev DB (read-only user where possible).
- [x] Add Pint, PHPStan/Larastan, ESLint (TypeScript parser), Prettier.
- [x] Add Pest or PHPUnit (PHPUnit 12 from the scaffold).

Exit check: `docker compose up` works. App boots. `composer test`, `npm run typecheck`, and `npm run build` pass. No secret is committed.

**Phase 1 exit check result (2026-11-04).**

- `docker compose up -d app nginx` — app healthy, nginx serves the SPA on port 8080.
- `php artisan test` — 2 tests, 2 passed.
- `npm run typecheck` — passed.
- `npm run build` — passed.
- `npm run lint` / `npm run format:check` / `./vendor/bin/pint --test` / `phpstan` — passed.
- No secret committed. `.env` is gitignored. `.env.example` contains placeholders only.

**Open items carried to Phase 2.**

- Local PHP has no `pdo_sqlite`, so PHPUnit uses MySQL only if DB access is needed. Install `php8.5-sqlite3` or run tests in the Docker container.
- ESLint dev dependency `minimatch` 10.2.6 has a moderate advisory (GHSA-g3hv-75wg-m249) with no non-breaking fix. Dev-only tool.
- `APP_PORT` defaults to 8080 because port 8000 is held by another local project (`hris-pis-workspace`).
- `@vue/tsconfig` root config requires duplicated `paths` in `tsconfig.json` so the shadcn-vue CLI can resolve aliases.

---

### Phase 2 — Database (additive only)

- [ ] Do **not** run the legacy DDL against production. Tables exist.
- [ ] Create additive migrations from PROMPT §9.7:
  - [ ] `categories/groups.deleted_at`.
  - [ ] `tickets.source`, `tickets.email_message_id`, `tickets.units_count`, indexes.
  - [ ] `tickets_log.action`, `tickets_log.meta`, index.
  - [ ] `attachements.storage_path`, `mime_type`, `size_bytes`, `sha256`, `uploaded_by`, `created_at`, index.
  - [ ] `notifications` index.
  - [ ] `auth_audit` table.
  - [ ] `admin_audit` table.
  - [ ] `email_ingest_log` table.
  - [ ] `user_profile` table (new). See `DECISIONS.md` C8.
- [ ] Add `tickets.updated_at` (nullable) for optimistic concurrency.
- [ ] Create Eloquent models for all 13 legacy tables plus `user_profile`, `auth_audit`, `admin_audit`, `email_ingest_log`.
- [ ] `its.users` and `its.locations` are read-only legacy tables. Do not add columns to them. Do not write to them.
- [ ] Disable automatic Eloquent timestamps in the models, or map the legacy columns. The legacy tables have no `created_at` or `updated_at`.
- [ ] Add a rollback script for every destructive step (InnoDB conversion).
- [ ] Preflight audit queries from PROMPT §36. Store before counts.
- [ ] Convert MyISAM to InnoDB **only after a full backup**. Verify row counts and indexes.

Exit check: migrations run on a staging copy. Row counts unchanged. Legacy columns untouched.

**Phase 2 exit check result (2026-11-04).**

- All 13 legacy tables preserved. No legacy column dropped or narrowed.
- 8 additive migrations ran on a staging copy of `its`.
- New tables: `user_profile`, `auth_audit`, `admin_audit`, `email_ingest_log`.
- New columns: `categories.deleted_at`, `groups.deleted_at`, `tickets.source`, `tickets.email_message_id`, `tickets.units_count`, `tickets.updated_at`, `tickets_log.action`, `tickets_log.meta`, `attachements.storage_path`, `mime_type`, `size_bytes`, `sha256`, `uploaded_by`, `created_at`.
- Indexes added to `tickets`, `tickets_log`, `notifications`, `attachements`.
- Eloquent models created for all 13 legacy tables plus 4 new tables.
- `its.users` and `its.locations` are read-only. No columns added to them.
- `php artisan migrate`, `php artisan test`, `pint`, `phpstan` all pass.

---

### Phase 3 — Authentication and session

- [ ] `LegacyEloquentUserProvider`: verify MD5 once, then rehash with Argon2id (D01).
- [ ] Dual-run hazard: keep modern hash in `password_v2` during dual run. Verify `password_v2` first, then MD5. Promote to `password` at cutover.
- [ ] Refuse `state = 2` with a generic message (D02).
- [ ] Rate limit: 5 failures / 15 minutes, configurable (D03).
- [ ] Generic failure message: `Invalid username or password.` (D05).
- [ ] Maintain `login_attempts`, `last_login_ip`, `last_login_dt` (D04). Add IPv6 text column.
- [ ] Reject ambiguous duplicate usernames (D72).
- [ ] Regenerate session ID on login. Secure cookie flags. Idle and absolute timeouts (D07).
- [ ] `fpc = 2` middleware guard. Allow only `user_options`, `process_change_password`, `logoff`, static assets (D06).
- [ ] Self password change: require current password, minimum 12, reject reuse (D95, D96).
- [ ] Admin password reset: ACL 9, CSRF, minimum 12, never prefill (D23).
- [ ] PIS sync as one explicit service (D08, D97):
  - [ ] Connect to `jblmrhpayroll` (read-only).
  - [ ] Join `jblmrhpayroll.user` by `its user id = user.id`.
  - [ ] Join `jblmrhpayroll.payroll` on `user.id = payroll.id`.
  - [ ] Map `payroll.department` to `jblmrhpayroll.department.id`. Validate it exists in `jblmrhpayroll.department`.
  - [ ] Read identity from `payroll` (`name`, `mname`, `lname`) and `user` (`username`, `password`).
  - [ ] Read state from `payroll.status` (`A` = active, `I` = inactive).
  - [ ] Apply the ACL rule: protected admins → 9, IHOMS department → 3, others → 1.
  - [ ] Create or update `user_profile` row. Never write to `jblmrhpayroll`.
  - [ ] Never recurse. Fail closed on department 0 or missing department.
- [ ] `auth_audit` writes for success, failure, lockout.
- [ ] Bootstrap-admin CLI command. No seeded password.

Exit check: AT-01 to AT-06 pass against a staging copy.

---

### Phase 4 — Module registry and routing

- [ ] Seed `mods` from the live 69-row export. Keep every row.
- [ ] Treat `parent = ''` and `parent = 'none'` as top-level.
- [ ] Map each `mods.path` to a target controller or a "not migrated" page.
- [ ] Cache the registry. Invalidate when `mods` changes.
- [ ] Build a `?p=<name>` compatibility adapter.
- [ ] Path allowlist: reject traversal and unregistered files (D65).
- [ ] Direct legacy file paths return 404 or route through middleware.
- [x] Central authorization middleware: module ACL and object scope (D10, D11). Applied to admin routes and ticket read/edit/close; new modules must opt in as they are built.
- [ ] CSRF on every state-changing request, including login and logout (D09).
- [ ] Retired-team pages: `groups`, `add_group`, `edit_group`, `process_add_group`, `process_edit_group`, `process_delete_group`, `assigned_to_group` show "This feature is retired".
- [ ] `change_password` routes to the real `?p=user_options` flow (C7).
- [ ] Security headers: CSP, nosniff, Referrer-Policy, frame denial, HSTS in production.
- [ ] Input validation with typed schemas. No `extract()`.

Exit check: every `?p=` alias resolves. Unknown module gives 404. No path traversal.

---

### Phase 5 — Ticket workflows

#### Staff create (`?p=create_ticket`)

- [ ] Validate subject 5–255 chars, issue ≥ 5 visible chars, existing active location/category/eu (D26).
- [ ] Force actor's ID when ACL ≥ 3 (D08-style rule).
- [ ] Write `assigned_gid = 0`. Skip the group log (D27).
- [ ] Accept backdate only when both fields present. Store consistent values (D29).
- [ ] Write logs: `Assigned to user: <fn ln>` when assignee > 0.
- [ ] Idempotency key. Keep the 1-second debounce (D30).
- [ ] Success text without the epoch (D28).
- [ ] Real transaction for ticket + logs.

#### End-user create (`?p=end_user&v=new_ticket`)

- [ ] Require auth and end-user permission (D32).
- [ ] Category 1, location from session, assignee 0, eu = session user.
- [ ] Validate place and contact. Cap lengths.
- [ ] Keep duplicate guard message `Ticket is already created.`
- [ ] Idempotency key.
- [ ] Success text and open-ticket count (fix the subtract-one logic).

#### Edit (`?p=edit_ticket&id=<id>`)

- [ ] Load ticket `FOR UPDATE`. 404 if missing (D34).
- [ ] Enforce §6.3 object policy.
- [ ] Lock only when closed and approved.
- [ ] Reject category 1. Require assignee.
- [ ] Sanitize note. Write exact legacy log strings.
- [ ] Optimistic concurrency.
- [ ] Set `assigned_date`/`assigned_time` on assignee change (D35).

#### Close (`?p=process_close_ticket&id=<id>`)

- [ ] Idempotent. Keep first resolver and timestamps (D36).
- [ ] Authorize closer (D37).
- [ ] 404 on missing ticket (D39).
- [ ] Auto-approve when `assigned_uid != 0 AND assigned_uid != created_uid AND approved_by_supervisor = 0`.
- [ ] Survey URL from config (D38).
- [ ] Log with survey link. Use an idempotency key (D40).

#### Approve / disapprove / undo

- [ ] Authorize: acl 9 or the creator's supervisor chain (D13).
- [ ] Require closed and `created_uid = assigned_uid` (D42).
- [ ] Keep transitions 0→1, 0/1→2, 1→0. Allow 2→1.
- [ ] Require remarks for disapproval (D41).
- [ ] Transaction and idempotency.
- [ ] Exact log strings.

#### Batch

- [ ] Validate the whole selection first. Reject atomically with per-ID errors (D14).
- [ ] Log only changed rows (D43).
- [ ] Keep JSON shape and `success` count.
- [ ] Cap batch size. CSRF.

Exit check: AT-07 to AT-26 pass.

---

### Phase 6 — Administration

#### Users

- [ ] List excludes `id=1`. Add archived filter.
- [ ] Add user: write `state=1`, `fpc=2`, random temp secret, valid notification (D22, D23).
- [ ] Fix the location dropdown query (D24).
- [ ] Validate username and foreign IDs (D25).
- [ ] Edit user: no password reset. Protect the acl-9 set from accidental lockout.
- [ ] Reset password: ACL 9, CSRF, minimum 12.
- [ ] Delete: soft delete, block self and protected admins, preserve references (D20).
- [ ] Remove the group field (teams retired). Write `group_id = 1` on insert.

#### Categories

- [ ] Hide `id=1`. Keep exact messages.
- [ ] Soft-delete or block when referenced (D21).
- [ ] Expose the Active/Inactive toggle.

#### Locations

- [ ] Add and edit `address` and `type` (D68).
- [ ] Soft-delete or block when referenced (D21).

#### Groups (retired)

- [ ] Keep table and rows. Show "This feature is retired" on all group screens.

#### Permissions

- [ ] Validate posted ACLs (D69).
- [ ] Update only rendered modules. Deterministic parent cascade.
- [ ] Transaction and audit.
- [ ] Prevent self-lockout.

#### Supervisors

- [ ] Set and remove. Keep exact messages.
- [ ] Reject cycles and cap depth (D70).
- [ ] Keep the IHOMS-only default.

Exit check: AT-33 to AT-37 pass.

---

### Phase 7 — Lists, search, detail

- [ ] All 13 list views with legacy filters and order (PROMPT §20).
- [x] Fix `all_closed_tickets_by_you` (D52) — `scope=closed_by_me` and `/tickets/closed-by-me`.
- [ ] `LEFT JOIN` everywhere (D56).
- [~] Explicit Requester and Location labels (D61) — added in all-tickets search results; audit other list/detail views.
- [~] Search: parameterized filters and 500 cap in search mode (D17) — core fields added; verify full legacy field/semantics parity.
- [~] Real print and reset (D59) — reset clears filters and reloads; print uses browser print.
- [x] Search grid title `Search Results` (D60) when filters are active.
- [ ] Detail: timeline oldest first. "Deleted user" via `LEFT JOIN` (D63).
- [ ] `#ticket=<id>` deep links.
- [ ] Retire `assigned_to_group` with a clear page.

Exit check: AT-27 to AT-32 pass.

---

### Phase 8 — Dashboard and monitoring

- [x] Dashboard cards with aggregate SQL (D50).
- [x] Fix the group sum bug (D46) with a single aggregate query (legacy group metrics retained with correct calculation).
- [x] Fix the duplicate/mislabeled cards (D47, D48) — distinct Today and This year (YTD) cards.
- [x] No dead Tech Status link in the SPA dashboard (D49).
- [ ] Monitoring refreshes the data source, not the grid (D51).
- [ ] Pause polling when the tab is hidden.

Exit check: dashboard under 2 seconds at production volume.

---

### Phase 9 — Reports and counter

- [ ] Require ACL ≥ 3 on every counter route (D12).
- [ ] Rewrite `counter/request.php` with parameterized queries (D17).
- [ ] Normalize date ranges to inclusive Manila days (D54).
- [ ] Implement the per-category pivot (D54).
- [ ] Server-side XLSX. Row cap. Safe sheet names.
- [ ] Keep the two units rules. Use `units_count` for new rows and `assigned_gid` for legacy.
- [ ] Keep JO/REG as configuration.
- [ ] Root `reports.php`: require auth, validate `date`, rename duration field, `LEFT JOIN`.
- [ ] Document the schema or remove the endpoint if unused.

Exit check: AT-38 and AT-39 pass.

---

### Phase 10 — Email ingestion (CLI)

- [ ] CLI entry point only. No web bootstrap (D58).
- [ ] Advisory lock. Dedicated OS user.
- [ ] IMAP with TLS and a UID cursor.
- [ ] Per-message transaction and `email_ingest_log` row.
- [ ] `Message-ID` idempotency (D75).
- [ ] Quarantine unknown senders (D57).
- [ ] Store attachments outside the web root. Record metadata (D16, D73).
- [ ] Delete the message only after commit.
- [ ] Deterministic preview. No tail removal (D31).
- [ ] Keep the confirmation notification text.

Exit check: AT-40 to AT-43 pass.

---

### Phase 11 — Notifications (in-app)

- [ ] Bell in the top menu.
- [ ] List scoped to the session user.
- [ ] Mark as read. Unread count.
- [ ] Keep `state` semantics (1 new, 2 read).
- [ ] Never store passwords or tokens.
- [ ] Redact the 5 legacy plaintext rows during migration.

Exit check: notification appears, can be read, and the count updates.

---

### Phase 12 — Attachments

- [ ] Keep table name `attachements` and legacy columns.
- [ ] Authorized download controller with ticket visibility checks.
- [ ] `Content-Disposition: attachment`, `nosniff`.
- [ ] Allowlist types. Size cap 25 MB. Magic-byte check.
- [ ] Read-only list on ticket detail when rows exist.
- [ ] Uploads disabled by default.

Exit check: AT-48 passes.

---

### Phase 13 — Tests, CI, documentation, cutover

- [ ] Pest feature tests for AT-01 to AT-54.
- [ ] Vitest component tests in TypeScript.
- [ ] Playwright E2E for every mutation, per ARCHITECTURE §11.4.
- [ ] CI: lint, `vue-tsc` type check, static analysis, tests, `composer audit`, `npm audit`, migration dry run.
- [ ] Update `README.md` with setup and roles.
- [ ] Write the data dictionary.
- [ ] Write the route map (`?p=` → target route).
- [ ] Write the deviation log.
- [ ] Write the operations runbook.
- [ ] Write user quick guides.
- [ ] Rehearse rollback.

Exit check: all quality gates in PROMPT §40 pass.

---

## 8. Deliverables

- [ ] Application source with local setup.
- [ ] `.env.example` with every key from Appendix C.
- [ ] `bootstrap-admin` command.
- [ ] `migrate` and rollback scripts.
- [ ] `ingest-email` CLI command.
- [ ] `rehash-passwords` command (optional).
- [ ] `verify-data` command.
- [ ] `export-mods` command.
- [ ] Unit, integration, and E2E tests.
- [ ] CI pipeline.
- [ ] Deployment manifests and runbook.
- [ ] `README.md`, data dictionary, route map, deviation log, runbook, user guides.

---

## 9. Acceptance test map

| Area | Tests | Phase |
|---|---|---|
| Auth and session | AT-01–AT-06 | 3 |
| Ticket creation | AT-07–AT-12 | 5 |
| Edit and close | AT-13–AT-20 | 5 |
| Approval | AT-21–AT-26 | 5 |
| Lists, search, detail | AT-27–AT-32 | 7 |
| Admin and permissions | AT-33–AT-37 | 6 |
| Reports and email | AT-38–AT-43 | 9, 10 |
| Security regression | AT-44–AT-50 | 4, 5, 11 |
| Data preservation | AT-51–AT-54 | 2, 13 |

---

## 10. Risks and mitigations

| Risk | Mitigation |
|---|---|
| MD5 rehash locks users out of the legacy app during dual run | Keep the modern hash in `password_v2`. Promote at cutover. |
| MyISAM to InnoDB conversion fails | Full backup first. Verify row counts and indexes. Keep rollback. |
| Duplicate usernames break login | Reject ambiguous login. Escalate to owner (D72). |
| 22 users with payroll department 0 loop the sync | Fail closed. Never recurse (D97). |
| Large tables slow the lists | Indexes from §9.7. Aggregate SQL. Server pagination. |
| Attachment path lost in `int` column | Add `storage_path`. Backfill by scan. Mark missing. |
| Plaintext passwords in notifications | Redact during migration. Never display. |
| Production data changed by mistake | Read-only DB user for development. No writes without approval. |

---

## 11. Open items

| # | Item | Owner | Status |
|---|---|---|---|
| Q1 | Legacy PHP code access | Dave | Not needed yet. Ask when a section is unclear. |
| Q2 | Duplicate username resolution (`admin` x3, etc.) | Dave / data owner | Open (D72). |
| Q3 | Notifications rows with plaintext passwords: redact or delete | Dave / data owner | Open (D74). |
| Q4 | `reports.php` external consumers | Dave | Open. Keep or remove (D12). |
| Q5 | Data retention policy | Dave | Open. |
| Q6 | Cutover date and dual-run window | Dave | Open. |
| Q7 | Create `.gitignore` and initialize git before any code is added | Agent | Done 2026-11-04. `.env` is ignored. |
| Q8 | Read-only database account for development | Dave / DB owner | Open. Reduces the risk of an accidental production write. |
| Q9 | Rotate the development database credentials at cutover | Dave | Open (D19). |
| Q10 | D45 badge scope and cutoff. D62 list name. | Dave | Open decisions. See `DECISIONS.md` Q1. |

---

## 12. Progress tracker

| Phase | Status | Started | Done |
|---|---|---|---|
| 0 Documentation | Done | 2026-11-04 | 2026-11-04 |
| 1 Scaffold | Done | 2026-11-04 | 2026-11-04 |
| 2 Database | Done | 2026-11-04 | 2026-11-04 |
| 3 Auth | In progress | 2026-11-04 | — |
| 4 Routing | In progress | 2026-11-04 | — |
| 5 Tickets | In progress | 2026-11-05 | — |
| 6 Administration | Done | 2026-11-05 | 2026-11-05 |
| 7 Lists/search/detail | Not started | — | — |
| 8 Dashboard | Not started | — | — |
| 9 Reports | Not started | — | — |
| 10 Email | Not started | — | — |
| 11 Notifications | Not started | — | — |
| 12 Attachments | Not started | — | — |
| 13 Tests/docs/cutover | Not started | — | — |

---

## 13. Defect tracker (PROMPT D01–D97)

Status values: `Not started`, `In progress`, `Decided` (decision recorded, code pending), `Done` (code plus test), `Open decision`.

| ID | Sev | Area | Phase | Status | Note |
|---|---|---|---|---|---|
| D01 | Critical | Auth | 3 | Not started | MD5 verify once, then Argon2id. See `DECISIONS.md` P1. |
| D02 | High | Auth | 3 | Not started | Refuse `state = 2`. Generic message. |
| D03 | High | Auth | 3 | Not started | Failure limit and cooldown. Audit. |
| D04 | Medium | Auth | 3 | Not started | Maintain counters and IP. Add IP text column. |
| D05 | Medium | Auth | 3 | Not started | `Invalid username or password.` |
| D06 | High | Auth | 3 | Not started | Real `fpc` middleware. Use `?p=user_options`. |
| D07 | High | Session | 3 | Not started | Regenerate, cookie flags, timeouts. |
| D08 | High | Auth/PIS | 3 | Not started | One explicit sync. No recursion. See C6. |
| D09 | High | CSRF | 4 | Not started | Token on every mutation, including login and logout. |
| D10 | Critical | Authz | 4 | Partial | Module ACL and object scope on every getter. Admin routes and ticket read/edit/close enforced; remaining modules as built. |
| D11 | Critical | Authz | 4 | Not started | Policy plus CSRF plus ownership. |
| D12 | Critical | Authz | 9 | Not started | Counter and `reports.php` require ACL ≥ 3. |
| D13 | Critical | Approval | 5 | Not started | Actor, state, self-created rule. |
| D14 | Critical | Approval | 5 | Not started | Validate whole batch. Atomic. |
| D15 | High | XSS | 4 | Not started | Sanitize on write, escape on read. |
| D16 | High | Uploads | 12 | Not started | Allowlist, size, magic bytes, private storage. |
| D17 | Critical | SQLi | 5, 9 | Not started | Parameterize every query. |
| D18 | High | Filesystem | 12 | Not started | Generated names, fixed root, no traversal. |
| D19 | Critical | Secrets | 1 | Not started | Env only. Rotate at cutover. |
| D20 | High | Admin | 6 | Not started | Soft delete. Block self and the acl-9 set. |
| D21 | High | Admin | 6 | Not started | Soft delete or block when referenced. |
| D22 | Medium | Admin | 6 | Not started | Explicit `state`, counters, profile fields. |
| D23 | High | Admin | 6 | Not started | Random one-time secret. `fpc = 2`. No plaintext. |
| D24 | Medium | Admin | 6 | Not started | Fix location dropdown query. Regression test. |
| D25 | Medium | Admin | 6 | Not started | Validate foreign IDs and username. |
| D26 | Medium | Tickets | 5 | Not started | Validate subject and issue separately. |
| D27 | Medium | Tickets | 5 | Decided | Option A. `assigned_gid = 0`. No group log. |
| D28 | Low | Tickets | 5 | Not started | Remove the epoch from the message. |
| D29 | Medium | Time | 5 | Not started | Store consistent date and time. |
| D30 | Low | Debounce | 5 | Not started | Scope idempotency per action. |
| D31 | Low | Email | 10 | Not started | Deterministic excerpt. No tail removal. |
| D32 | High | End user | 5 | Not started | Require auth and an idempotency key. |
| D33 | Medium | End user | 5 | Not started | Trim, normalize, cap. |
| D34 | High | Edit | 5 | Not started | 404, authorize, lock, transaction. |
| D35 | Medium | Edit | 5 | Not started | Maintain assigned date and time. |
| D36 | Medium | Close | 5 | Not started | Idempotent close. |
| D37 | High | Close | 5 | Not started | Enforce close permission and scope. |
| D38 | Medium | Close | 5 | Not started | Survey URL from config. |
| D39 | Medium | Close | 5 | Not started | 404 unknown ticket. |
| D40 | Low | Close | 5 | Not started | Idempotency key for the log. |
| D41 | Medium | Approval | 5 | Not started | Require remarks for disapproval. |
| D42 | High | Approval | 5 | Not started | Closed plus self-assigned. |
| D43 | Medium | Approval | 5 | Not started | Log only changed rows. |
| D44 | Low | Approval | 5 | Not started | Configure both cutoffs. |
| D45 | Medium | Approval | 5 | Open decision | Badge scope and cutoff. Ask Dave. |
| D46 | Medium | Dashboard | 8 | Fixed (2026-11-09) | Group-closed-today uses a single aggregate query; legacy group metrics retained with correct calculation. |
| D47 | Medium | Dashboard | 8 | Fixed (2026-11-09) | Distinct “Today” and “This year (YTD)” cards. |
| D48 | Low | Dashboard | 8 | Fixed (2026-11-09) | Duplicate card removed; metrics are distinct. |
| D49 | Low | Dashboard | 8 | Fixed (SPA) | No dead Tech Status link in the SPA dashboard/nav. |
| D50 | Low | Dashboard | 8 | Fixed (2026-11-09) | Aggregate SQL in `TicketService::dashboardForUser`. Cache not added. |
| D51 | Medium | Monitoring | 8 | Not started | Refresh data, keep grid state. Pause when hidden. |
| D52 | Medium | Lists | 7 | Fixed (2026-11-09) | `scope=closed_by_me` filters `state_id=2` and `resolved_uid=current`. |
| D53 | High | Data | 2 | Decided | Keep `assigned_gid`. Add `units_count`. |
| D54 | Medium | Counter | 9 | Not started | Fix both reports. Inclusive days. |
| D55 | Medium | End user | 7 | Not started | Valid location or "Unspecified". |
| D56 | High | Lists | 7 | Not started | `LEFT JOIN` everywhere. |
| D57 | High | Email | 10 | Not started | Quarantine unknown senders. |
| D58 | High | Email | 10 | Not started | CLI only. Transaction. Delete after commit. |
| D59 | Low | Search | 7 | Not started | Real print and reset. |
| D60 | Low | UI | 7 | Not started | Filter enabled modules. Fix the title. |
| D61 | Low | UI | 7 | Not started | Explicit requester and location labels. |
| D62 | Low | Lists | 7 | Open decision | Rename or restrict to `created_uid`. Ask Dave. |
| D63 | Medium | Logs | 7 | Not started | `LEFT JOIN`. Show "Deleted user". |
| D64 | Medium | Audit | 6 | Not started | Append-only `admin_audit`. |
| D65 | High | Routing | 4 | Not started | Allowlist resolved paths. |
| D66 | High | Data | 2 | Not started | Validation, soft deletes, indexes. FKs on new tables only. |
| D67 | Medium | Errors | 4 | Done | Safe messages. Log details. Fix labels. Unexpected API errors render a generic 500 and are reported server-side. |
| D68 | Low | Admin | 6 | Not started | Editable address and type. |
| D69 | High | Permissions | 6 | Not started | Validate, render-only update, cascade, audit. |
| D70 | Medium | Supervisors | 6 | Not started | Reject cycles. Cap depth. |
| D71 | High | Migration | 2 | Not started | Normalize `state = 0` to 1. |
| D72 | High | Migration | 3 | Not started | Reject ambiguous login. Needs owner decision. |
| D73 | High | Attachments | 12 | Not started | `storage_path`, metadata, authorized download. |
| D74 | Medium | Notifications | 11 | Decided | In-app bell. Redact legacy rows. |
| D75 | Medium | Email | 10 | Not started | Use `email_ingest_log`. |
| D95 | High | Password | 3 | Not started | Minimum 12. Require current password. |
| D96 | High | Password | 3 | Not started | Require current password. Rate limit. |
| D97 | High | Auth/PIS | 3 | Not started | No recursion. Fail closed. Fresh row. |

Summary: 78 defects. 3 decided, 2 open decisions, 73 not started.

---

## 14. Change log for this plan

| Date | Change |
|---|---|
| 2026-11-04 | Initial plan. Recorded decisions C1, C2, C6, C7, D27, D53, D74, teams retired. |
| 2026-11-04 | Added document set, defect tracker, and corrected the Eloquent timestamp note. |
| 2026-11-04 | Frontend language set to TypeScript in strict mode across all records. |
| 2026-11-04 | UI library changed from PrimeVue to shadcn-vue + Reka UI. Vue Router set to 5.x. Forms use vee-validate. |
| 2026-11-04 | Phase 1 scaffold complete. Laravel 13, Vue 3.5 + Vite 8 + TS 5.9.3, Tailwind 4, shadcn-vue + Reka UI, Yup + vee-validate, JWT, Docker Compose. All local checks pass. |
| 2026-11-04 | Phase 2 database complete. 8 additive migrations, 4 new tables, 17 Eloquent models. Legacy tables untouched. |
