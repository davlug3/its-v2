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
| C6 | PIS sync uses `jblmrhpayroll` | Approved |
| C7 | Override the broken `change_password` module row | Approved |
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
| UI components | PrimeVue 5.x |
| Styling | Tailwind CSS 4.x |
| Validation (frontend) | Zod |
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
- The API and the SPA are separate builds.
- All frontend source is TypeScript in strict mode. Do not add plain JavaScript to `src/`.
- `vue-tsc --noEmit` runs in CI and must pass.
- Every dependency version is checked against the official release page before use.
- No pre-release version is used.

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

## C6 — PIS sync uses `jblmrhpayroll`

**Decision.**
The PIS sync reads the `payroll` table in the `jblmrhpayroll` database.
The join key is `its.users.id = jblmrhpayroll.payroll.id`.
The column `payroll.department` maps to `its.locations.id`.

**Reason.**
`PROMPT.md` §7.3 says the source is `its.payroll`.
No such table exists in `its`.
Dave confirmed the source database and the shared credentials on 2026-11-04.

**Evidence.**

- `its` has 13 tables. None is `payroll`.
- `jblmrhpayroll.payroll` exists and has 4,812 rows.
- Sample join: users 200, 203, 209, 221, 229 match by id, and the names match.
- `payroll.department` has 97 distinct values greater than 0.
- All 97 values exist in `its.locations`.

**Effect.**

- The application has a second database connection named `payroll`.
- The sync is one explicit service. It is not a middleware side effect.
- The sync validates that the mapped location exists.
- The sync fails closed. It never recurses.

**Data risk.**
31 users have `location_id = 0`.

- 9 have `payroll.department > 0`. The sync can fix them.
- 22 have `payroll.department = 0`. The legacy code would loop on these.
  The new code must stop and report. See D97.

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

## Q1 — Open questions

These items need a decision from Dave. They do not block Phase 1.

| # | Question | Why it matters | Recommendation |
|---|---|---|---|
| Q1 | How to resolve duplicate usernames such as `admin` (3 rows) | Login is ambiguous. `PROMPT.md` D72 says reject. | Reject the login, return a generic error, and send the user to the help desk. Do not auto-merge. |
| Q2 | Keep or remove the root `reports.php` endpoint | Unknown external consumers | Ask before removal. Keep it behind auth until then. |
| Q3 | Cutover date and dual-run window | Sets the password promotion date | Decide before Phase 13 |
| Q4 | Data retention policy | Affects archives and soft deletes | Decide before Phase 13 |
| Q5 | Read-only database user for development | Reduces the risk of an accidental production write | Ask the DB owner for a SELECT-only account |

---

## Change log for this file

| Date | Change |
|---|---|
| 2026-11-04 | Initial record. Added A1–A5, C1, C2, C6, C7, D27, D53, D74, T1, T2, P1, Q1. |
