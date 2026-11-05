# Decisions — IHOMS Ticketing System Rebuild

**Status:** Active
**Last updated:** 2026-11-04
**Owner of decisions:** Dave (director / architect)

This file records decisions and their reasons.
It does not record suggestions that Dave has not approved.
Each entry states the decision, the reason, the evidence, and the effect.

Related files:

- [`PROMPT.md`](./PROMPT.md) — behavioral contract.
- [`PLAN.md`](./PLAN.md) — phases and progress.
- [`ARCHITECTURE.md`](./ARCHITECTURE.md) — target stack.
- [`DESIGN.md`](./DESIGN.md) — visual reference.
- [`CHANGELOG.md`](./CHANGELOG.md) — change history.

---

## Index

| ID | Title | Status |
|---|---|---|
| A1 | New project in `/home/dave/its-v2` | Approved |
| A2 | Target stack | Approved |
| A3 | Mobile-first development | Approved |
| A4 | Additive migrations only | Approved |
| A5 | Production database is authoritative | Approved |
| C1 | Protect the real acl-9 users | Approved |
| C2 | Protect and hide `categories.id = 1` | Approved |
| C6 | User identity and location come from `jblmrhpayroll` | Approved |
| C8 | Retire `its.users` and `its.locations`; add `user_profile` | Approved |
| C7 | Override the broken `change_password` module row | Approved |
| U1 | shadcn-vue replaces PrimeVue | Approved |
| U2 | TypeScript version: 5.9.3 (7 blocked by `@volar/typescript` peer `<7`) | Approved |
| U3 | Vue Router 5.4.0 | Approved |
| U4 | Forms use vee-validate | Approved |
| U5 | Schema library is Yup, not Zod | Approved |
| U6 | Local application port is 8080 | Approved |
| U7 | Package manager is pnpm | Approved |
| D27 | Staff-create tickets write `assigned_gid = 0` | Approved |
| D53 | `assigned_gid` stays a legacy group ID; add `units_count` | Approved |
| D74 | In-app notification bell | Approved |
| T1 | Teams are fully retired | Approved |
| T2 | User add/edit drops the group field | Consequence of T1 |
| P1 | Dual-run password strategy | Pending cutover approval |
| Q1 | Open questions | Open |

---

## A1 — New project in `/home/dave/its-v2`

**Decision.**
Build a new application from scratch in `/home/dave/its-v2`.

**Reason.**
Dave directed this on 2026-11-04.
The existing `/home/dave/its-helpdesk` scaffold uses Inertia, Sanctum, and Breeze.
`ARCHITECTURE.md` rules those out.
A fresh start avoids carrying the wrong stack into the rebuild.

**Effect.**

- `its-helpdesk/` is ignored completely. No file is copied from it.
- The new project uses the official Laravel 13 installer.
- All setup follows the official guides for each tool.

---

## A2 — Target stack

**Decision.**
Use the stack in `ARCHITECTURE.md`:

| Layer | Choice |
|---|---|
| Backend | PHP 8.5 / Laravel 13.x |
| API | Strict REST, versioned `/api/v1` |
| Frontend | Vue 3.5 SPA + Vite |
| Frontend language | TypeScript 5.x, strict mode |
| UI components | shadcn-vue 2.x + Reka UI 2.x |
| Styling | Tailwind CSS 4.x |
| Validation (frontend) | Yup 1.x with `@vee-validate/yup` |
| Database | MySQL / MariaDB (legacy schema preserved) |
| Auth | JWT (`php-open-source-saver/jwt-auth`) |
| Containers | Docker + Docker Compose |
| Tests | Pest or PHPUnit, Vitest, Playwright |

**Reason.**
Dave selected this stack.
`ARCHITECTURE.md` documents it.

**Effect.**

- No Inertia.
- No Sanctum as the primary auth system.
- No Breeze scaffolding.
- No PrimeVue. Replaced by shadcn-vue (see U1).
- The API and the SPA are separate builds.
- All frontend source is TypeScript in strict mode. Do not add plain JavaScript to `src/`.
- `vue-tsc --noEmit` runs in CI and must pass.
- Every dependency version is checked against the official release page before use.
- No pre-release version is used.

---

## U1 — shadcn-vue replaces PrimeVue

**Decision.**
Use **shadcn-vue 2.x** with **Reka UI 2.x** for UI components.
Do not use PrimeVue.

**Reason.**
Dave selected shadcn-vue on 2026-11-04.
PrimeVue 5 moved to the PrimeUI commercial license.
It requires a license key and eligibility checks.
A hospital helpdesk is unlikely to qualify for the free Community License.
shadcn-vue is MIT, free, and gives the project full control of the component source.

**What shadcn-vue is.**
The official docs say: "This is not a component library. It is how you build your component library."

- The `shadcn-vue` npm package is a CLI and a code distribution tool. It is not a runtime dependency.
- UI components are copied into `resources/js/components/ui/`. The project owns them.
- Runtime behavior comes from `reka-ui` (MIT).
- Styling comes from Tailwind CSS 4.
- Icons come from `@lucide/vue` `1.67.0`.
  - `lucide-vue-next` `1.0.0` is deprecated.
  - The current shadcn-vue registry (`new-york-v4`) imports from `@lucide/vue`.
- Toasts use `vue-sonner`.
- Desktop tables use `@tanstack/vue-table`.
- Forms use `vee-validate` with `@vee-validate/zod`.

**Effect.**

- No license key, no eligibility test, no vendor lock-in.
- Copied components are maintained by this project. Upstream fixes are not automatic.
- Reka UI security and behavior fixes arrive through npm.
- `DESIGN.md` maps to shadcn-vue components instead of PrimeVue.
- Do not add a second component library.

**Risks.**

- The CLI assumes a plain Vite `src/` layout. This project uses `resources/js/`. The alias settings in `components.json` must be verified. Fallback: create `components.json` by hand and add components one at a time.
- Copied components can drift from upstream.

---

## U2 — TypeScript version

**Decision.**
Use TypeScript `5.9.3`.
Do not use TypeScript 7.

**Reason.**
The plan allowed an attempt at TypeScript `7.0.2` with a fallback to `5.9.3`.
The attempt failed at install time.
`vue-tsc` `3.3.14` depends on `@volar/typescript` `2.4.30`.
`@volar/typescript` declares `peerOptional typescript "<7"`.
`npm` therefore rejects TypeScript 7.
`5.9.3` is the latest stable 5.x release.
The fallback condition in the approved plan is met.

**Effect.**

- `typescript` is pinned to `5.9.3` in `package.json`.
- The version and the reason are recorded in `CHANGELOG.md`.
- Re-check TypeScript 7 when `@volar/typescript` raises its peer range.

---

## U3 — Vue Router 5

**Decision.**
Use Vue Router `5.4.0`.
Correct the `4.x` references in the docs.

**Reason.**
`5.4.0` is the current stable.
It supports Vue 3.5 and Vite 8.
The earlier `4.x` note was out of date.

---

## U4 — Forms use vee-validate

**Decision.**
Use `vee-validate` for forms.

**Reason.**
The shadcn-vue `Form` components bind to vee-validate field state.
It provides form state, errors, touched state, and field registration.
The schema adapter is supplied by Yup (see U5).

**Effect.**

- `vee-validate` is a direct dependency.
- Form state, errors, and touched state come from vee-validate.
- Validation rules come from schemas. Do not duplicate rules by hand.
- Server 422 errors map onto the same field errors.

---

## U5 — Schema library is Yup

**Decision.**
Use **Yup `1.7.1`** with **`@vee-validate/yup` `4.15.1`**.
Do not use Zod.

**Reason.**
Dave chose Yup on 2026-11-04.
The shadcn-vue form registry pins `zod@3.25.76`.
`@vee-validate/zod` requires Zod 3.
Zod below `4.6.6` has a high-severity advisory: GHSA-mrhx-c7xc-m3w5 / CVE-2023-54404.
The advisory has no fix on the 3.x line.
Yup has no known advisories.
`@vee-validate/yup` is the official adapter and peer-accepts `yup ^1.3.2`.

**Verified facts.**

| Item | Value |
|---|---|
| `yup` latest stable | `1.7.1` |
| Published | 2025-09-21 |
| License | MIT |
| `@vee-validate/yup` | `4.15.1` |
| `npm audit` after the swap | 0 vulnerabilities |

**Effect.**

- Schemas use `y.object`, `y.string`, and `y.array`.
- Types use `InferType<typeof schema>`.
- `zod` and `@vee-validate/zod` are not dependencies.
- `ARCHITECTURE.md` already allowed Yup: "Zod (preferred) or Yup".
- shadcn-vue documentation examples use Zod. Translate them to Yup. The `Form` components do not change.

**Known limitation.**
Yup's TypeScript inference is weaker than Zod 4 for conditional rules and discriminated unions.
Current forms are plain objects, so this does not affect them.
Revisit if a future form needs a discriminated union.

---

## A3 — Mobile-first development

**Decision.**
Build mobile-first.

**Reason.**
Dave directed this on 2026-11-04.
Helpdesk staff use the system from phones and tablets as well as desktops.

**Effect.**

- Base styles target small screens.
- Larger screens add layout through `min-width` breakpoints.
- Touch targets are at least 44 x 44 px.
- Tables become stacked cards on small screens.
- No feature is desktop-only.
- The visual system comes from `DESIGN.md`.

---

## A4 — Additive migrations only

**Decision.**
Every schema change is additive.

**Reason.**
`PROMPT.md` §2.1 forbids deleting a table or a column.
The production database holds 107,043 tickets and 408,717 log rows.
Legacy code may still read the old columns during the dual run.

**Effect.**

- Allowed: add a column, add an index, add a table, widen a column.
- Not allowed without a separate approval: drop, narrow, rename, or change the meaning of a legacy column.
- New behavior uses new columns. Example: `units_count`, not a redefined `assigned_gid`.
- Every destructive step, such as the MyISAM to InnoDB conversion, needs a backup and a rollback plan.

---

## A5 — Production database is authoritative

**Decision.**
When `PROMPT.md` disagrees with the production database, the production database wins.
Report the conflict before you write code.

**Reason.**
The live data is the real behavior.
The document is a specification, and parts of it are out of date.

**Effect.**

- Every conflict is listed in `PLAN.md` §6.
- Each conflict is resolved in this file.
- No production row is changed to match the document.

---

## C1 — Protect the real acl-9 users

**Decision.**
The protected administrator set is the 8 users with `acl_id = 9`.
Do **not** protect `users.id = 1`.

**Reason.**
`PROMPT.md` assumes `users.id = 1` is the administrator.
Production says otherwise.

**Evidence (2026-11-04).**

- `users.id = 1` has `mail = admin`, `acl_id = 1` (End User), `location_id = 47`.
- The 8 users with `acl_id = 9` are ids 334, 2194, 2415, 3040, 4735, 4862, 4991, 4992.

**Effect.**

- The admin-edit guard protects the acl-9 set.
- The self-lockout guard protects the acl-9 set.
- User delete blocks self and any acl-9 user.
- `users.id = 1` is not special. It can be edited like any other end user.
- The user list still hides `id = 1`, as `PROMPT.md` requires for display. Hiding and protecting are separate rules.

---

## C2 — Protect and hide `categories.id = 1`

**Decision.**
`categories.id = 1` ("No Category") is protected.
It is hidden from the admin category list.
It cannot be deleted or renamed.

**Reason.**
The row is a sentinel. End-user tickets use category 1.
`PROMPT.md` §2.2 requires it.

**Evidence.**
Production row: `{"id":1,"category":"No Category","status":1}`.

**Effect.**

- The admin list starts at `id >= 2`.
- The ticket edit form rejects category 1.
- The database row stays.

---

## C6 — User identity and location come from `jblmrhpayroll`

**Decision.**
The application reads user identity, location, and state from the `jblmrhpayroll` database at runtime.
It does not store those fields locally.
The source tables are `jblmrhpayroll.user`, `jblmrhpayroll.payroll`, and `jblmrhpayroll.department`.
All three tables are **read-only**. The application never writes to them.

**Reason.**
Dave directed the change on 2026-11-04.
The `jblmrhpayroll` tables are the system of record for people and departments.

**Join chain.**

```
jblmrhpayroll.user.id
    = jblmrhpayroll.payroll.id
    = app user id (the helpdesk user id)

jblmrhpayroll.payroll.department
    = jblmrhpayroll.department.id
    = app location id
```

**Field mapping.**

| Field the app needs | Source |
|---|---|
| First name | `jblmrhpayroll.payroll.name` |
| Middle name | `jblmrhpayroll.payroll.mname` |
| Last name | `jblmrhpayroll.payroll.lname` |
| Username | `jblmrhpayroll.user.username` |
| Password (MD5) | `jblmrhpayroll.user.password` |
| Location | `jblmrhpayroll.payroll.department` |
| State | `jblmrhpayroll.payroll.status` — `A` = active, `I` = inactive |

**ACL rule.**

| Condition | `acl_id` |
|---|---|
| One of the 8 protected admins | 9 |
| Department id 15 (INTEGRATED HOSPITAL OPERATIONS AND MANAGEMENT SECTION) | 3 |
| Everyone else | 1 |

**Evidence.**

- `jblmrhpayroll.user` has 4,814 rows. `jblmrhpayroll.payroll` has 4,812 rows.
- 4,806 rows join on `id`.
- `payroll.department` has 98 distinct values. Only `0` is not a `department.id`.
- `jblmrhpayroll.department` has 139 rows, ids 1–143.
- `its.locations` has 100 rows. 41 department ids are not in `its.locations`.
- `payroll.status` has `A` (3,146 rows) and `I` (1,666 rows).
- `user.access` does not map to `its.users.acl_id`. It is not used.

**Effect.**

- The application has a second database connection for `jblmrhpayroll`.
- The sync is one explicit service. It is not a middleware side effect.
- The sync validates that the mapped department exists.
- The sync fails closed. It never recurses. See D97.

**Data risk.**

- Department `0` means "no department". Fail closed. See D97.
- 41 department ids are not in `its.locations`. Fail closed for those users.

---

## C8 — Retire `its.users` and `its.locations`

**Decision.**
Retire the local `its.users` and `its.locations` tables for identity, location, and state.
Read those fields from `jblmrhpayroll` at runtime.
Keep only the helpdesk-specific user attributes in a new local table.

**Retired columns.**

| Old column | Replacement |
|---|---|
| `its.users.fn` | `jblmrhpayroll.payroll.name` |
| `its.users.ln` | `jblmrhpayroll.payroll.lname` |
| `its.users.mail` | `jblmrhpayroll.user.username` |
| `its.users.password` | `jblmrhpayroll.user.password` |
| `its.users.location_id` | `jblmrhpayroll.payroll.department` |
| `its.users.state` | `jblmrhpayroll.payroll.status` |
| `its.users.profile_pic_path` | Retired. Not stored. |

**Retired table.** `its.locations` is replaced by `jblmrhpayroll.department`.

**New table.** `user_profile`.

| Column | Purpose |
|---|---|
| `user_id` (PK) | Same value as `jblmrhpayroll.user.id` |
| `acl_id` | Derived by the ACL rule in C6 |
| `group_id` | Default 1 |
| `fpc` | Default 1 |
| `login_attempts` | Helpdesk login tracking |
| `last_login_ip` | Helpdesk login tracking |
| `last_login_dt` | Helpdesk login tracking |
| `supervisor_id` | Helpdesk supervisor link |
| `deleted_at` | Soft delete |

**Reason.**
Dave directed the retirement on 2026-11-04.
The `jblmrhpayroll` tables are the system of record.
Keeping a second copy of identity data would cause drift.

**Effect.**

- The app adds no `fn`, `ln`, `mail`, `password`, `location_id`, `state`, or `profile_pic_path` columns to any new user table.
- Tickets, logs, and reports that show a user name read it from `jblmrhpayroll` through a join or a read model.
- Location dropdowns and filters read `jblmrhpayroll.department`.
- The 8 protected admins keep `acl_id = 9` in `user_profile`.
- `its.users` and `its.locations` are left in place and untouched. They are not dropped.

---

## C7 — Override the broken `change_password` module row

**Decision.**
The `mods` row named `change_password` is not used as a page.
The password change flow uses the real `?p=user_options` module.

**Reason.**
The row is mis-registered.

**Evidence.**
`change_password` has `parent = user_options`, `acl = 9`, and
`path = user_settings/user_options.php`.
That path is the self-service options page, not a password form.
The row also sits under a parent that already owns that page.

**Effect.**

- The `mods` row stays in the table. `PROMPT.md` §2.5 forbids silent deletion.
- The `?p=change_password` alias redirects to `?p=user_options`.
- Password change logic lives in one place: the user options flow.
- The change is recorded as a deviation in this file and in `CHANGELOG.md`.

---

## D27 — Staff-create tickets write `assigned_gid = 0`

**Decision.**
Option A.
A ticket created by staff writes `assigned_gid = 0`.
The create action does not write a group log row.

**Reason.**
Legacy code always writes `assigned_gid = 1` and logs "Assigned to group: 1".
The create form has no group field.
A value of 1 is fiction.

**Effect.**

- `assigned_gid = 0` means "no group".
- No group log is written on staff create.
- The assignee log still writes when an assignee is set: `Assigned to user: <fn ln>`.
- Legacy tickets keep their original `assigned_gid`.

---

## D53 — `assigned_gid` stays a legacy group ID; add `units_count`

**Decision.**
Keep `tickets.assigned_gid` as a legacy group reference.
Do not reuse or reinterpret it.
Add a new column `tickets.units_count` for the units value.
New writes never put units into `assigned_gid`.

**Reason.**
Dave corrected the first reading.
The production data proves `assigned_gid` is a group reference.

**Evidence.**

- `assigned_gid` matches an existing `groups.id` on 30,716 tickets.
- Only 3 distinct values are orphans: 66, 74, 100.
- 76,324 tickets have `assigned_gid = 0`.
- `users.group_id` has only 3 distinct values (1, 3, 0).

**Effect.**

- The ticket form shows units in a separate field that writes `units_count`.
- Legacy rows show units as empty until someone fills `units_count`.
- Reports state which column they read.
- Teams are retired, so no new screen reads `assigned_gid` as a team queue.
  See T1.
- The orphan values 66, 74, and 100 are left as they are. They are legacy data.

---

## D74 — In-app notification bell

**Decision.**
Option A.
Notifications are delivered in the application through a bell in the top menu.

**Reason.**
The legacy `notifications` table is a write-only outbox with no reader.
It holds 5 rows with plaintext passwords and malformed `http://http://` URLs.
Reading it as-is would leak secrets.

**Effect.**

- The bell shows the unread count for the session user.
- The list is scoped to the session user.
- `state` keeps its legacy meaning: 1 = new, 2 = read.
- No password, token, or secret is ever stored in a notification.
- The 5 legacy rows are redacted during migration.
- The bell is not a chat or a workflow engine. It is a message list.

---

## T1 — Teams are fully retired

**Decision.**
Option B.
The team concept is retired from the application.

**Reason.**
Dave directed this on 2026-11-04.
Teams no longer match how work is assigned.
Units replaced them.

**What is removed.**

1. The "Assigned To Group" list view (`?p=assigned_to_group`).
2. The "Assigned / To Group" dashboard card.
3. The Groups admin screen (`?p=groups`) and its child modules.
4. The group dropdown in the user add and edit forms.
5. All new reads of `users.group_id`.

**What is kept.**

- The `groups` table and all 59 rows.
- The `users.group_id` column.
- All 7 affected `mods` rows.
- `assigned_gid` as legacy data (see D53).

**Affected `mods` rows.**

| Module | Parent | ACL |
|---|---|---|
| `groups` | administration | 9 |
| `add_group` | groups | 9 |
| `edit_group` | groups | 9 |
| `process_add_group` | groups | 9 |
| `process_edit_group` | groups | 9 |
| `process_delete_group` | groups | 9 |
| `assigned_to_group` | tickets | 3 |

**Effect.**

- Each of the 7 modules returns a clear "This feature is retired" page.
- No `mods` row is deleted.
- No `groups` row is deleted.
- No `users.group_id` value is changed.
- No SQL query in new code filters or groups by `users.group_id`.
- The dashboard shows no replacement card for teams.

**Deviation record.**
This is a deliberate change of behavior.
`PROMPT.md` describes these screens as live.
The change is a Dave decision, not an implementation shortcut.

---

## T2 — User add/edit drops the group field

**Decision.**
The user add and edit forms have no group field.
On insert, the application writes `group_id = 1`.
On edit, the application does not change `group_id`.

**Reason.**
This follows from T1.
`users.group_id` is `NOT NULL` in the legacy schema, so an insert must supply a value.
Value 1 is the historical default. It is a placeholder, not a real assignment.

**Effect.**

- The column stays readable for legacy reports.
- No validation rule checks a group value.
- No audit entry records a group change, because no group can change.

---

## P1 — Dual-run password strategy

**Decision.**
During the dual run, keep the modern password hash in a new column `password_v2`.
Verify `password_v2` first, then the legacy MD5 `password`.
Promote `password_v2` into `password` at cutover.

**Reason.**
Users log in to the legacy app and the new app at the same time.
The legacy app cannot read an Argon2id hash.
Replacing `password` too early locks users out of the legacy app.

**Status.**
Proposed. Needs Dave's approval before Phase 2 migration work.
This is a data-migration strategy, which the working rules reserve for Dave.

**Effect if approved.**

- Login checks `password_v2` first.
- If `password_v2` is empty, login checks MD5 `password`.
- On a successful MD5 login, the application writes an Argon2id hash to `password_v2`.
- The legacy `password` column is not written until cutover.
- A `rehash-passwords` command can pre-fill `password_v2` offline.

---

## U6 — Local application port

**Decision.**
The local stack uses port 8080.
Port 8000 is held by another local project on this machine.
Do not use 8000 for this project.

**Reason.**
Port 8000 is in use by `/home/dave/hris-pis-workspace/apps/api`.
That project must not be displaced.

**Effect.**

- `APP_PORT` defaults to 8080 in `docker-compose.yml`.
- `APP_URL` and `ITS_BASE_URL` use `http://localhost:8080` in `.env` and `.env.example`.
- Use `php artisan serve --port=8080` for host-only development.

---

## U7 — Package manager is pnpm

**Decision.**
Use **pnpm** as the Node package manager.
Do not use npm.

**Reason.**
Dave chose pnpm on 2026-11-04.
pnpm is installed (10.22.0).
pnpm handles optional dependencies (rolldown native bindings) more reliably than npm.
pnpm uses a content-addressable store, which saves disk space.

**Effect.**

- `package-lock.json` is removed. `pnpm-lock.yaml` is committed.
- `pnpm install` is used in all scripts, Dockerfiles, and the dev container.
- `corepack enable` is used in containers to provide pnpm.
- The `concurrently` dependency is kept but unused; it will be removed in a later cleanup.

---

## Q1 — Open questions

These items need a decision from Dave. They do not block Phase 1.

| # | Question | Why it matters | Recommendation |
|---|---|---|---|
| Q1 | How to resolve duplicate usernames such as `admin` (3 rows) | Login is ambiguous. `PROMPT.md` D72 says reject. | Reject the login, return a generic error, and send the user to the help desk. Do not auto-merge. |
| Q2 | Keep or remove the root `reports.php` endpoint | Unknown external consumers | Ask before removal. Keep it behind auth until then. |
| Q3 | Cutover date and dual-run window | Sets the password promotion date | Decide before Phase 13 |
| Q4 | Data retention policy | Affects archives and soft deletes | Decide before Phase 13 |
| Q5 | Read-only database user for development | Reduces the risk of an accidental production write | Ask the DB owner for a SELECT-only account |
| Q6 | Local PHP has no `pdo_sqlite` | PHPUnit needs SQLite for the default test config | Install `php8.5-sqlite3`, or run tests in the Docker container, or switch `phpunit.xml` to MySQL |
| Q7 | ESLint dev dependency `minimatch` 10.2.6 has a moderate advisory (GHSA-g3hv-75wg-m249) | Audit noise in CI | Dev-only tool. No non-breaking fix exists. Report only. |

---

## Change log for this file

| Date | Change |
|---|---|
| 2026-11-04 | Initial record. Added A1–A5, C1, C2, C6, C7, D27, D53, D74, T1, T2, P1, Q1. |
| 2026-11-04 | Added U1 (shadcn-vue), U2 (TypeScript 5.9.3), U3 (Vue Router 5), U4 (vee-validate), U5 (Yup), U6 (port 8080). |
| 2026-11-04 | Revised C6. Identity, location, and state now come from `jblmrhpayroll`. Added C8: retire `its.users` and `its.locations`, add `user_profile`. |

## U8 — Retire forced password change (fpc)

* Date: 2026-11-05
* Decision: The forced-password-change feature is retired. The `user_profile.fpc` column is retained for legacy compatibility but is no longer enforced, written, or read by application code.
* Reason: User direction. The `ForcePasswordChange` middleware, the `?p=change_password` redirect, and the `fpc = 2` requirement for admin-created accounts are all dropped.
* Effect: D06 fix is superseded (no forced-change guard). Section 7.4 login redirect table no longer branches on `fpc`. AT-04 is retired. `PROMPT.md` sections 7.4, 7.5, 7.6, and D23 should be read as "retired" rather than "fixed".
* Voluntary self-service password change (`/auth/change-password`) remains available.
