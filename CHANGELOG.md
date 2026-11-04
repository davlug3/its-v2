# Changelog — IHOMS Ticketing System Rebuild

All notable changes to this project are recorded here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
The project does not follow semantic versioning yet.
Versions start at `0.1.0` when the scaffold is ready.

---

## [Unreleased]

### Added

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
  - C6 — PIS sync reads `jblmrhpayroll.payroll`.
  - C7 — override the broken `change_password` module row.
  - D27 — staff-create tickets write `assigned_gid = 0`.
  - D53 — keep `assigned_gid` as legacy group ID; add `units_count`.
  - D74 — in-app notification bell.
  - T1 — teams fully retired.

### Changed

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
