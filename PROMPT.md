# Rebuild Prompt — ITS Helpdesk / Ticket System

**Audience:** a senior full-stack engineer or coding agent rebuilding the legacy ITS application on **[TARGET STACK]**.
**Source system:** PHP/MySQL helpdesk at `X:\its` (production host `192.163.10.131`, database `its`).
**Document status:** behavior specification and migration contract. Where this document and the legacy code disagree, the legacy database and the live `mods` table are authoritative; report the conflict before implementing.

This is not a greenfield helpdesk. It is a **re-platforming with data preservation**, plus a small, explicitly listed set of security and data-integrity repairs. Reproduce every workflow, label, restriction, and quirk marked **PRESERVE**. Apply every repair marked **FIX**, and record it as a documented deviation. Never silently “improve” a workflow.

**Conventions used below**

- **Legacy** = the current PHP application in this repository.
- **Target** = the application to be built on `[TARGET STACK]`.
- **PRESERVE** = observable behavior must match, including validation text, redirects, log entries, and empty states.
- **FIX** = intentional deviation required for security or data integrity; the deviation ID (for example `D07`) refers to §37.
- **MUST / MUST NOT** = acceptance criteria.
- `?p=<name>` means the legacy front-controller URL. The target MUST keep these URLs working as compatibility aliases.
- All timestamps are Unix epoch seconds in **Asia/Manila** unless stated otherwise.
- “Ticket” = one row in `tickets`. “Log” = one row in `tickets_log`. “Module” = one row in `mods`.

---

## 1. Mission and success criteria

Rebuild the application so that, on cut-over day:

1. Every existing row in the production `its` database remains readable with its original meaning, and the legacy application can still read rows written by the new application during a dual-run period.
2. Every role sees the same screens, ticket lists, actions, approval rules, reports, and messages it saw before, except for the documented repairs in §37.
3. An end user can submit a ticket and a technician can assign, edit, close, and have it approved exactly as before.
4. No legacy security defect survives: no unauthenticated data endpoints, no SQL injection, no CSRF, no stored XSS, no IDOR, no MD5-only authentication.
5. The new application is deployable, testable, documented, and does not depend on PHP, jQuery, Kendo UI, or MyISAM.

**Success is not** “the pages look similar.” Success is: identical data semantics, identical workflow outcomes, and a complete audit trail.

**Out of scope unless the data owner asks:** a native mobile app, an employee self-service portal beyond the existing end-user pages, payroll features, mileage features, and the abandoned Laravel scaffold under `rebuild/`. Do not copy code from `rebuild/`, `.history/`, `__MACOSX/`, or `node_modules/`.

---

## 2. Non-negotiable data contract

### 2.1 Schema rules

- **MUST keep every existing table name**, including the misspelled **`attachements`**. Do not rename it to `attachments`.
- **MUST keep every existing column name, type, nullability, default, character set, collation, and meaning.** This includes intentionally awkward choices such as `tickets.created_date` and `tickets.created_time` both being `int(11)`, and `attachements.file_name` being `int(11)`.
- **MAY add** new tables, new nullable/defaulted columns, and new indexes.
- **MUST NOT delete** an existing table or column.
- **MUST NOT narrow or repurpose** an existing column. New semantics go in new columns.
- **MUST NOT add** a `NOT NULL` column without a default to an existing table, because legacy inserts list only a subset of columns.
- **MUST keep `locations2`** exactly as a duplicate of `locations`; it is unused by the application but is part of the contract.
- **MUST preserve row IDs.** Do not renumber categories, groups, locations, users, or tickets. IDs are referenced by code, logs, and external links.
- **MUST preserve** `users.id = 1` as the protected system administrator account. The admin user list intentionally excludes it; the target may allow editing it only through a separate “system account” flow.
- **MUST preserve** `categories.id = 1` as the protected default category used by email and end-user tickets. It is intentionally hidden from the category admin list and rejected by ticket editing.

### 2.2 Allowed physical changes

The target MAY convert tables from MyISAM to InnoDB, add foreign keys for **new** tables, and use `utf8mb4` for **new** columns. Converting an existing table’s storage engine is allowed only after a backup and only if every legacy column, index, and default remains intact. Do not change existing column charsets as part of the rebuild; add new columns for new text requirements.

### 2.3 Data that must not be “cleaned up” silently

- Category IDs **23** (hardware), **24** (announcements), and **1** (default/protected) are business data, not enums. The same applies to location **15** (IHOMS) and group **1**. Make them configurable with the same defaults (§11).
- `tickets.assigned_gid` has a **dual legacy meaning**: it is the “assigned group” in some screens and the “No. of units/items/persons” value in others. Preserve the values; do not normalize them.
- `tickets.approved_by_supervisor` is a tri-state flag: `0` = pending/not reviewed, `1` = approved, `2` = disapproved.
- Closed tickets may have `resolved_uid = 0`, `resolved_date = 0`, or `resolved_time = 0` from legacy imports. Do not “repair” rows without an explicit migration decision.

### 2.4 Credentials and secrets

- **MUST NOT** carry the legacy test credentials (`jblmgh`, the seeded MD5 admin password, database passwords, IMAP placeholders) into production.
- Provide a one-time bootstrap command or environment-driven seed for the first administrator. Do not seed a default password in a migration.
- All secrets come from environment variables or a secret manager, never from committed files. The existing `.env` and `config/connect.php` fallbacks are development-only references, not production configuration.

### 2.5 Module registry is production data

The repository does **not** contain the live contents of `mods`. Before building:

1. Export `SELECT * FROM mods ORDER BY parent, weight, name` from production.
2. Treat that export as canonical. Preserve every row’s `name`, `display`, `path`, `acl`, `type`, `enabled`, `weight`, `has_icon`, and `icon`.
3. Seed the rows added by migrations (`supervisors`, `tickets_by_subordinates`, `tickets_by_supervisors`, and their endpoints) if they are missing.
4. Keep every extra module the export contains, even if this document does not mention it. Implement its path or return a clear “not migrated” page; do not silently drop it.

---

## 3. Vocabulary and invariants

| Term | Meaning |
|---|---|
| **ACL level** | Integer privilege level. Larger number = more privilege: `9` Administrator, `3` Help Desk, `2` Technician, `1` End User. Access test is `required_acl <= user_acl`. |
| **Module type** | `0`/`1` = screen or menu (top menu is shown when `type < 2`); `2` = screen without top-menu treatment; `3` = data getter (JSON); `4` = process endpoint (mutation). The legacy code only special-cases `< 2` and `< 4`. |
| **Creator** | `tickets.created_uid`: the logged-in user who submitted the ticket. |
| **End user** | `tickets.eu_uid`: the person the ticket is for. For end-user and email tickets this equals the creator; for staff-created tickets it may be someone else. |
| **Assignee** | `tickets.assigned_uid`: the staff member expected to handle the ticket; `0` = unassigned. |
| **Resolver** | `tickets.resolved_uid`: the user who closed the ticket. |
| **Group / units** | `tickets.assigned_gid`: either a `groups.id` selection or a “No. of units/items/persons” count, depending on the screen. `users.group_id` is the user’s team. |
| **Supervisor** | `users.supervisor_id`: a self-referencing link to another `users.id`. A user may have at most one direct supervisor. |
| **Open / closed** | `tickets.state_id` = `1` Open, `2` Closed (`states` table). |
| **Pending review** | A closed, self-created, self-assigned ticket of a subordinate with `approved_by_supervisor = 0`. |
| **Debounce** | A one-second server-side guard (`$_SESSION['DEBOUNCE_TIME'] = 1`) against rapid duplicate submissions. |

**Invariants the target MUST enforce**

- `tickets.state_id` is `1` or `2`.
- A closed ticket has `resolved_uid`, `resolved_date`, and `resolved_time` set by the closing action (legacy rows may violate this; do not rewrite them).
- `approved_by_supervisor` is only `0`, `1`, or `2`.
- A ticket’s `created_uid`, `eu_uid`, `location_id`, and `category_id` reference existing rows at write time. The legacy schema has no foreign keys; the target enforces this in application code and in new tables.
- Deleting a user, category, group, or location MUST NOT make historical tickets disappear from lists or reports. Use soft deletion (`deleted_at`) or block the delete.

---

## 4. Architecture and request lifecycle

### 4.1 Legacy shape

- `index.php` includes `header.php`, which:
  1. defines `INCLUDE_CHECK`, loads `config/connect.php` and `config/application_settings.php`;
  2. starts the session named `expressservicedesk`;
  3. handles `?p=logoff`;
  4. includes the login processor;
  5. redirects unauthenticated users to `?p=login`;
  6. applies role landing redirects;
  7. looks up the module in `mods` and redirects unknown modules through `logoff` then `login`;
  8. sets the timezone and computes `$date`, `$time`, `$int_date` (midnight epoch), and `$int_time` (today at the current clock time);
  9. renders the HTML head, loading Kendo/jQuery assets for modules with `type < 4`.
- `index.php` then re-reads the module, renders the top menu when `type < 2`, checks `acl <= $_SESSION['user_acl']`, and includes `mods/<path>`.
- Data getters (`type = 3`) and process endpoints (`type = 4`) are separate PHP files under `mods/**/data/`. They are normally called by AJAX or form posts, but they are also directly reachable by URL. In the legacy app they receive **no module ACL check**; most only check that a session exists.

### 4.2 Target shape

Build a single application with:

- one HTTP entry point (`/index.php` or the framework front controller);
- a route/module registry backed by `mods`, cached and invalidated when the table changes;
- a compatibility adapter that maps `?p=<name>` to the registered module path and preserves `#ticket=<id>` deep links;
- a central authorization middleware that resolves the module and enforces both module ACL and object-level rules;
- a separate CLI entry point for cron/email ingestion, with no web session;
- a storage abstraction for attachments outside the web root;
- structured application logging plus the legacy `tickets_log` writes.

### 4.3 Request lifecycle (target)

1. Start a secure session; load the authenticated user or reject.
2. Resolve `p` to a module row. Unknown/disabled modules return 404 or the legacy “unknown module” redirect; do not include arbitrary paths.
3. Enforce `module.acl <= user.acl` and the endpoint policy in §6.3.
4. Enforce CSRF for every state-changing request.
5. Parse and validate input with a typed schema; never use `extract()` on request data.
6. Execute the use case in a database transaction where more than one row changes.
7. Write the legacy `tickets_log` entry and any notification in the same transaction.
8. Render HTML (escaped by default) or JSON with an explicit content type.
9. Audit security-relevant events (login, permission change, delete, approval, close).

### 4.4 Path and route safety

- Legacy `index.php` concatenates `'mods/'.$path` from the database. The target MUST validate that the resolved path is inside the application’s module directory and is an allowed file. A compromised `mods` row must not become arbitrary file inclusion.
- Direct requests to legacy PHP file paths (for example `/mods/tickets/data/process_close_ticket.php`) MUST NOT bypass routing. Either return 404 or route them through the same middleware.
- Keep `?p=` aliases stable for bookmarks and email links. Do not require a new URL scheme to use the app.

---

## 5. Module registry, navigation, and URL contract

### 5.1 `mods` columns

| Column | Meaning |
|---|---|
| `name` | Primary key and URL value (`?p=name`). |
| `parent` | Parent module name, or `none` for a top-level item. |
| `display` | Menu label / screen title. |
| `path` | PHP file relative to `mods/` (legacy). In the target, map to a route/controller. |
| `has_icon` | `1` = render the legacy icon class, `2` = no icon. |
| `icon` | Legacy CSS icon class. |
| `acl` | Minimum ACL required. |
| `type` | `0`/`1` screen/menu, `2` screen, `3` data getter, `4` process. |
| `enabled` | `1` enabled, `2` disabled. |
| `weight` | Sort order ascending. |

### 5.2 Menu behavior (PRESERVE)

- Top-level menu query: `type = 1 AND parent = 'none' AND acl <= user_acl AND enabled = 1 AND name != 'end_user' ORDER BY weight`.
- A top-level item with at least one visible child renders as a dropdown; otherwise it renders as a direct link to `?p=<name>`.
- Child menu query: `parent = <name> AND type = 1 AND acl <= user_acl AND enabled = 1 ORDER BY weight`.
- Hide **Tickets by Subordinates** when the user has no active direct report (`users.supervisor_id = user_id AND state = 1`).
- Hide **Tickets by Supervisor** when the user has no supervisor (`supervisor_id IS NOT NULL AND supervisor_id > 0`).
- The pending-review badge appears only on **Tickets by Subordinates**; it is populated by `get_pending_review_count` (§19).
- Right menu always shows `"<ln>, <fn>"`, **Options** (`?p=user_options`), and **Log Out** (`?p=logoff`).
- The Tickets submenu (`mods/tickets/tickets_menu.php`) is rendered at the bottom of every ticket screen. It always shows **Create Ticket**, then every visible child of `parent = 'tickets'` ordered by weight, with the same supervisor-only hiding.
- **FIX (D60):** the Tickets submenu currently ignores `enabled`; the target must filter `enabled = 1`.

### 5.3 Required URL surface

Appendix B lists every module and endpoint found in the repository. The definitive list is the production `mods` export. The target MUST at minimum preserve:

- `?p=login`, `?p=logoff`
- `?p=end_user` with `v=new_ticket` and `v=view_tickets`
- `?p=dashboard` (redirects), `?p=dashboard&v=tickets`, `?p=monitoring`
- all ticket list pages by their registered names (`all_open_tickets`, `all_closed_tickets`, `all_closed_request`, `all_hardware_tickets`, `all_announcements`, `all_closed_tickets_by_you`, `created_by_user`, `assigned_to_you`, `assigned_to_group`, `unassigned_tickets`, `search_tickets`, `tickets_by_subordinates`, `tickets_by_supervisors`)
- `?p=create_ticket`, `?p=edit_ticket&id=<id>`
- all `process_*` endpoints used by forms
- `?p=user_options`, `?p=process_change_password`
- all `administration/*` modules and their data endpoints
- `counter/`, `counter/category.php`, `reports.php`

### 5.4 External links that must not break

- Ticket deep links use the URL fragment `#ticket=<id>`. A user opening a list page with that fragment sees the ticket opened automatically; the back button restores the list. Implement this in the target’s list/detail UI.
- The close action prints and stores a survey URL containing `ticketID=<id>` (§16). Make the base URL configurable; preserve the existing default until the data owner changes it.

---

## 6. Roles, authorization, and session

### 6.1 Role definitions

| ACL | Name | Intended use |
|---|---|---|
| 9 | Administrator | Full system administration, all tickets, reports, permissions, supervisors, users, categories, groups, locations. |
| 3 | Help Desk | All ticket views, create/edit/close, approval of subordinates, batch approval, reports/counter. |
| 2 | Technician | Ticket views, create/edit/close tickets they are allowed to work. |
| 1 | End User | Create tickets, view own tickets, change own password. |

The numeric values are fixed by data. Do not renumber them. The labels come from `acls`.

### 6.2 Module ACL

The legacy check is exactly `module.acl <= session.user_acl`. Keep that semantic. Module ACL values are data-driven and editable in **Administration → Permissions**. The target MUST cache/reload them and MUST NOT hardcode the per-module ACL in code except as a fallback when the row is missing.

### 6.3 Endpoint authorization policy (FIX D10, D11, D13, D14)

The legacy data/process files are directly reachable and mostly check only `isset($_SESSION['user_id'])`. The target MUST enforce the following on **every** request, including AJAX and direct file-style URLs:

| Capability | Allowed actors |
|---|---|
| View a ticket list / search all tickets | ACL >= 2 |
| View a specific ticket | ACL >= 2, or ACL 1 when the user is `created_uid` or `eu_uid` |
| Create a staff ticket | ACL >= 2 |
| Create an end-user ticket | any authenticated user (normally ACL 1) |
| Edit ticket metadata / add a note | ACL >= 2, and the user is the assignee, creator, a member of the assigned group, or ACL >= 3 |
| Close a ticket | ACL >= 2 and (assignee, creator, assigned group member, or ACL >= 3), or ACL 1 when the user is the creator |
| Approve / disapprove / undo | ACL 9, or the actor is the ticket creator’s direct/indirect supervisor; ticket must be closed, self-created, and self-assigned |
| Batch approve / disapprove | same as individual approval, for every selected ticket |
| View `tickets_by_subordinates` | ACL >= 3 and the actor has at least one active subordinate (menu hides it otherwise) |
| View `tickets_by_supervisors` | ACL >= 3 and the actor has a supervisor |
| Admin CRUD (users, categories, groups, locations, permissions, supervisors) | ACL 9 |
| Counter and `reports.php` | ACL >= 3 (legacy was unauthenticated; FIX D12) |
| Data getters | same policy as their parent screen, never “any session” |
| Process endpoints | same policy as the action they perform, plus CSRF |

**Object-level checks are mandatory.** A valid session plus ACL 2 must not be able to read or mutate an arbitrary ticket by changing `id`. For end users, scope every ticket query to `created_uid = session.user_id OR eu_uid = session.user_id`.

### 6.4 Session keys (PRESERVE names where practical)

Legacy stores: `user_id`, `user_acl`, `fn`, `ln`, `login_attempts`, `fpc`, `state`, `group_id`, `location_id`, `is_supervisor`. The target may store a richer object, but any code that reads these keys (including third-party integrations) must keep working through a compatibility layer.

### 6.5 Session security (FIX D07)

- Regenerate the session ID on login and privilege change.
- Cookie flags: `HttpOnly`, `SameSite=Lax` (or `Strict` if compatible), `Secure` in production.
- Idle timeout configurable; default 8 hours. Absolute timeout configurable.
- Destroy the session and rotate the CSRF token on logout.
- Do not put secrets or password hashes in the session.
- Use a server-side session store or signed cookie; never trust client-side role data.

---

## 7. Authentication and password lifecycle

### 7.1 Login form (PRESERVE)

- Route: `?p=login`, rendered only when no session exists. If an authenticated staff user visits it, redirect to `?p=all_open_tickets`; an authenticated end user redirects to `?p=end_user&v=new_ticket`.
- Fields: `mail` (text, label/placeholder “Username (Use PIS account)”) and `password`; submit button value **`Login!`**.
- Username matching is case-insensitive because the legacy column collation is `latin1_swedish_ci`. Preserve case-insensitive login.
- Password is posted in clear over HTTPS (required) and hashed server-side.

### 7.2 Legacy verification (PRESERVE during migration, FIX D01)

1. Look up `users` by `mail` and `password = MD5(posted_password)`.
2. If a row matches, log the user in and immediately rehash the password with Argon2id (or the target’s strongest available algorithm) and update `users.password`.
3. If the stored value is already a modern hash, verify it normally.
4. Never write a new MD5 hash. After the migration window, optionally force a password reset for accounts still on MD5.

**Dual-run hazard:** the legacy login compares `password = MD5(posted_password)`. If the target updates `users.password` to Argon2id while the legacy app still shares the database, that user can no longer log in through legacy. During the dual-run period, either keep the modern hash in an additive column (for example `password_v2`), verify it first and fall back to MD5, and promote it into `password` only at cutover; or restrict target access to users who do not need legacy login and document the restriction. Do not silently lock users out of the legacy URL.

### 7.3 PIS synchronization (PRESERVE, FIX D08)

Legacy behavior on a successful credential match:

- If `users.location_id = 0`, query `payroll` by `users.id` for `department`. The legacy code passes the **main** `$connits` connection into the sync function, so the lookup runs against the main schema even though a separate payroll connection exists; verify in production whether `its.payroll` is a table/view or whether the intended `jblmrhpayroll.payroll` is never reached.
- Update the main `users` row: `location_id = <department>`, `acl_id = 1`, `group_id = 1`, `fpc = 1`, `state = 1`.
- Recursively call the whole login function again.
- If the user update fails, throw an exception (“Unable to sync user account data from PIS”). A failed payroll lookup or an empty result is **not** checked; the sync is skipped or writes `0` and recurses.
- **Legacy bugs to fix:** the recursion can loop indefinitely when `department` is `0`/empty, and after the inner call returns, the outer call overwrites the refreshed `$_SESSION` values with the stale pre-sync row. Load the updated row once, never recurse.

Target requirements:

- Implement this as an explicit `syncFromPayroll(user)` service, called at most once per login.
- Validate that the department maps to an existing `locations.id`; if not, fail with a clear account-configuration error rather than writing an invalid FK-like value.
- Do not demote an existing administrator/help-desk account merely because `location_id` was 0; **document this deviation** and require an explicit data-owner decision. The safe default is: only apply the legacy defaults when the account has never been configured (`location_id = 0 AND acl_id = 0`), otherwise preserve the existing ACL/group.
- If payroll is unavailable, allow a configured fallback or fail closed; never create a half-updated account and never recurse.

### 7.4 Login outcomes and redirects (PRESERVE)

| Condition | Result |
|---|---|
| Bad username/password | Remain on the login screen with a generic “Invalid username or password.” (legacy was silent; FIX D05). Do not reveal whether the username exists. |
| `state = 2` (disabled) | Refuse login with a generic message (legacy ignored `state`; FIX D02). |
| Too many failed attempts | Temporary lockout with a generic message (FIX D03). Default: 5 failures / 15 minutes, configurable. |
| Successful staff login (`acl > 1`), `fpc != 2` | `?p=dashboard` → header immediately redirects to `?p=all_open_tickets`. |
| Successful staff login, `fpc = 2` | Force password change screen (`?p=user_options` in the target; legacy pointed at unregistered `?p=change_password`, FIX D06). |
| Successful end-user login (`acl = 1`), `fpc != 2` | `?p=end_user&v=new_ticket`. |
| Successful end-user login, `fpc = 2` | Force password change screen. |
| Authenticated user opens `?p=dashboard` without `v` | Redirect to `?p=all_open_tickets` (legacy comment: “direto na daw sa tickets”). |
| Authenticated user opens `?p=login` | Staff → `?p=all_open_tickets`; end user → `?p=end_user&v=new_ticket`. |
| `?p=logoff` | Destroy session, redirect to `?p=login`. |
| No session and `p != login` | Redirect to `?p=login`. |
| Unknown module name | Legacy redirects through `?p=logoff` to `?p=login`. Target: 404 for authenticated users, or the same redirect if compatibility requires it. |

### 7.5 Forced password change (FIX D06)

- `users.fpc`: `1` = no change required, `2` = must change.
- The legacy `header.php` guard is commented out and the redirect target `?p=change_password` is not a registered module. Implement a real middleware guard:
  - If `fpc = 2`, block every route except `?p=user_options`, `?p=process_change_password`, `?p=logoff`, and static assets.
  - Show the legacy warning: “You are required to change your password before you can continue.”
  - On success set `fpc = 1` and update the session.
- New accounts created by an administrator MUST start with `fpc = 2`.

### 7.6 Password change (FIX D95, D96)

- Self-service route: `?p=user_options` → form posts to `?p=process_change_password&id=<session user>`.
- Fields: new password, confirm password. **FIX:** require the current password as well.
- Legacy minimum was 4 characters (self) and 5 (admin reset). Target minimum is 12 characters, configurable, with a breached-password check if available.
- On mismatch: “The passwords did not match, please try again!” (preserve text if the UX owner prefers).
- On success: update `password`, set `fpc = 1`, update the session, and show “Your password has been changed!”.
- Admin reset screen: `?p=reset_user_password&id=<id>` posts to `?p=process_reset_user_password&id=<id>`; fields password/confirm and a “Force Password Change?” checkbox (checked by default). The legacy form pre-fills the password and confirm inputs with `$fn`/`$ln` when those variables happen to be defined (normally they are not). **FIX:** never pre-populate password fields. Target must require ACL 9, CSRF, and a minimum length.
- Never log or email plaintext passwords. If a temporary password must be delivered, show it once to the administrator and/or send a one-time reset link.

### 7.7 Login audit (FIX D04)

- Update `users.last_login_dt` with the current epoch and `users.last_login_ip` with `ip2long(IPv4)`; for IPv6 write `0` to the legacy int column and store the text form in a new `last_login_ip_text varchar(45)` column.
- Reset `login_attempts` to `0` on success; increment on failure; reset after a successful password reset.
- Add an append-only `auth_audit` table (new) for success/failure/lockout events, including username attempted, IP, user agent, and timestamp. Never store passwords.

---

## 8. Security baseline for the target

These are mandatory regardless of stack. Each maps to a defect in §37.

1. **SQL injection:** every query uses parameter binding. No string interpolation of request values, including `id`, dates, `employee`, `cat`, `from`, `to`, `q`, and array IDs. `counter/request.php` must be rewritten entirely; its current `extract($data)` and interpolated SQL are forbidden.
2. **CSRF:** synchronizer token per session, validated on every POST/PUT/PATCH/DELETE. AJAX sends the token in a header or form field. Login and logout are protected too. Do not use GET for mutations.
3. **XSS:** escape all output by context. Ticket `issue`, notes, and `tickets_log.data` contain rich HTML by design; sanitize on write with an allowlist (p, br, strong, b, em, i, u, s, ul, ol, li, blockquote, a[href], span[style limited], div, font[color/face/size], table basics) and escape on read unless the stored value is known-sanitized. Remove `on*` attributes, `javascript:` URLs, `<script>`, `<iframe>`, `<object>`, `<embed>`, and CSS expressions. Apply the same rule to subject, place, contact, names, category/group/location names, and log remarks.
4. **IDOR:** authorize every object access, not just the screen.
5. **Authentication:** Argon2id/bcrypt, disabled-account check, rate limiting, session regeneration, secure cookies.
6. **File uploads:** validate size, MIME type, extension, and magic bytes; generate a random storage name; store outside the web root; serve through an authorized controller; never trust `file_name_org`.
7. **Path traversal:** never include a file path from the database or request without an allowlist.
8. **SSRF:** the survey URL and IMAP host come from configuration, not user input.
9. **Headers:** `Content-Security-Policy` (at least `default-src 'self'` with explicit exceptions), `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `X-Frame-Options: DENY` or CSP `frame-ancestors`, HSTS in production.
10. **Secrets:** environment/secret manager; rotate the legacy DB and IMAP passwords at cutover.
11. **Logging:** structured logs for auth, authorization failures, admin mutations, approvals, closes, deletes, and email ingestion. Do not log passwords or full email bodies.
12. **Cron:** the email job runs from CLI with a dedicated OS user, no web session, and an advisory lock to prevent concurrent runs.
13. **Dependencies:** pin versions, run a vulnerability scan, and do not ship Kendo/jQuery unless the target stack deliberately chooses them.

---

## 9. Database contract

The authoritative DDL is reproduced in Appendix A. This section defines the meaning the target must preserve.

### 9.1 `users`

- `id` auto-increment. `id = 1` is the protected system administrator (`admin` in the seed).
- `fn`, `ln`: first/last name. `mail`: login username, case-insensitive in practice; add a unique index only after a duplicate preflight.
- `password`: legacy MD5 (32 hex) or target hash. Never expose.
- `acl_id`: `acls.id` (9/3/2/1). Defaults are not declared; always write explicitly.
- `location_id`: `locations.id`; `0` triggers PIS sync on login.
- `group_id`: `groups.id`; `1` is the default used by PIS sync and staff ticket creation fallback.
- `fpc`: `1` no forced change, `2` forced change.
- `state`: `1` enabled, `2` disabled. Legacy add-user omitted it, so many rows may be `0`. See §39 for the migration decision.
- `login_attempts`, `last_login_ip`, `last_login_dt`: legacy int fields; legacy never maintained them. Target maintains them.
- `profile_pic_path`: text, unused by the legacy UI except storage. Preserve.
- `supervisor_id`: nullable self-reference; `NULL` or `0` means none.

### 9.2 `tickets`

| Column | Contract |
|---|---|
| `id` | Auto-increment, referenced everywhere. Never reuse. |
| `subject` | `text`; legacy allows blank; target requires 5–255 characters (FIX D26). Store plain text, not HTML. |
| `issue` | `text`; rich HTML from the editor or email body. Sanitize on write. |
| `preview` | Plain-text excerpt. Legacy staff create uses `strip_tags(html_entity_decode(stripslashes(issue)))`; email uses the body minus its last 50 characters. Preserve the column, improve the excerpt (FIX D31). |
| `resolution` | Nullable, **never written by the legacy app**. Preserve unused unless the data owner explicitly asks to capture resolution text; if used, do not change the column meaning. |
| `created_uid` | Submitter. |
| `created_date` | Midnight epoch of the creation calendar date. |
| `created_time` | Epoch of “today at the submission clock time,” normally the full creation timestamp. See §10. |
| `category_id` | `categories.id`; `1` is the protected default; `23` hardware; `24` announcements. |
| `location_id` | `locations.id`. |
| `place`, `contact` | Free text from end-user tickets; nullable. |
| `assigned_gid` | Group/units field; default `0`; legacy staff create effectively writes `1`. |
| `assigned_uid`, `assigned_date`, `assigned_time` | Assignee and assignment timestamps; timestamps default `0` and are not actively maintained by the legacy edit flow. |
| `state_id` | `1` open, `2` closed. |
| `resolved_uid`, `resolved_date`, `resolved_time` | Resolver and close timestamps; defaults `0`. |
| `eu_uid` | End user. Email/unknown senders may be `0`. |
| `approved_by_supervisor` | `0` pending, `1` approved, `2` disapproved. |

### 9.3 `tickets_log`

- One row per action/note. `ticket_id`, `user_id`, `data` (HTML/text), `date` (midnight epoch), `time` (today-at-clock epoch).
- Display order in the ticket detail is `id ASC` (oldest first).
- The target MUST keep writing this table for every legacy action so reports and history remain compatible.
- Optional additive columns: `action` (enum), `meta` (JSON), `created_at` (full timestamp). Never change existing meanings.
- `INNER JOIN users` on display hides logs whose author was deleted. Target uses `LEFT JOIN` and shows “Deleted user” (FIX D63).

### 9.4 `attachements` (misspelled)

- `file_name_org varchar(255)`: original filename (legacy stores a sanitized version).
- `file_name int(11)`: **legacy defect** — the cron inserts a path like `2026_10/123report.pdf`, which MySQL truncates to its numeric prefix (usually the year) under non-strict mode. Do not change the column type. Add `storage_path varchar(500) NULL` for new rows and backfill where possible.
- `ticket_id`: owning ticket; no foreign key in legacy.
- No upload, download, or display UI exists in the legacy app; only the IMAP cron writes rows. See §33 for the target decision.

### 9.5 `categories`, `groups`, `locations`, `locations2`

- `categories.status`: `1` active, anything else inactive. The create/edit forms only list `status = 1`. The admin list hides `id = 1`; preserve that.
- `groups.name` is unique. Group `1` is the default.
- `locations.type`: `1` global, `2` mileage-only. Only type `1` appears in ticket/location/user dropdowns.
- `locations.address` is `NOT NULL` with no default in the dump; legacy add-location first tries `INSERT (name)` and retries with `address = ''`. Target always writes a value.
- `locations2` is an exact duplicate and is unused. Preserve it; do not point the app at it.

### 9.6 `acls`, `states`, `application_settings`, `notifications`, `mods`

- `acls`: seed rows 9/3/2/1. No CRUD UI; treat as reference data.
- `states`: 1 Open, 2 Closed. No CRUD UI.
- `application_settings`: `title` is read and displayed in the browser title and top menu. `version` is unused. New settings may use namespaced keys, but do not repurpose existing ones.
- `notifications`: `user_id`, `subject`, `data`, `dt` (epoch), `state` (`1` new, `2` sent). Legacy only writes `state = 1`. See §32.
- `mods`: see §5.

### 9.7 Recommended additive schema

Apply as new migrations; keep every legacy column untouched.

```sql
ALTER TABLE users
  ADD COLUMN deleted_at DATETIME NULL,
  ADD COLUMN last_login_ip_text VARCHAR(45) NULL,
  ADD INDEX idx_users_state_acl (state, acl_id),
  ADD INDEX idx_users_mail (mail);

ALTER TABLE categories ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE `groups`   ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE locations  ADD COLUMN deleted_at DATETIME NULL;

ALTER TABLE tickets
  ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'web',
  ADD COLUMN email_message_id VARCHAR(255) NULL,
  ADD INDEX idx_tickets_state_category_created (state_id, category_id, created_time),
  ADD INDEX idx_tickets_creator_created (created_uid, created_time),
  ADD INDEX idx_tickets_resolver_resolved (resolved_uid, resolved_date),
  ADD INDEX idx_tickets_assigned_state (assigned_uid, state_id),
  ADD UNIQUE KEY uq_tickets_email_message (email_message_id);

ALTER TABLE tickets_log
  ADD COLUMN action VARCHAR(40) NULL,
  ADD COLUMN meta JSON NULL,
  ADD INDEX idx_tickets_log_ticket_id (ticket_id, id);

ALTER TABLE attachements
  ADD COLUMN storage_path VARCHAR(500) NULL,
  ADD COLUMN mime_type VARCHAR(150) NULL,
  ADD COLUMN size_bytes BIGINT NULL,
  ADD COLUMN sha256 CHAR(64) NULL,
  ADD COLUMN uploaded_by INT NULL,
  ADD COLUMN created_at INT NULL,
  ADD INDEX idx_attachements_ticket (ticket_id);

ALTER TABLE notifications
  ADD INDEX idx_notifications_user_state (user_id, state, dt);

CREATE TABLE auth_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  username_attempted VARCHAR(255) NULL,
  event VARCHAR(40) NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_auth_audit_user_time (user_id, created_at)
);

CREATE TABLE email_ingest_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  message_id VARCHAR(255) NULL,
  from_email VARCHAR(255) NULL,
  subject VARCHAR(500) NULL,
  ticket_id INT NULL,
  status VARCHAR(40) NOT NULL,
  error TEXT NULL,
  received_at DATETIME NULL,
  processed_at DATETIME NOT NULL,
  UNIQUE KEY uq_email_message_id (message_id),
  INDEX idx_email_status (status, processed_at)
);
```

`email_ingest_log` replaces the legacy `emailtodb_log`/`emailtodb_email` calls; those tables do not exist in `config/sts.sql`, so the legacy inserts fail silently. Do not rely on them.

---

## 10. Time and date encoding (critical)

The legacy application stores **two** values for every event:

- `created_date` / `resolved_date` = `strtotime(date('Y-m-d'))` → **midnight** of the calendar date.
- `created_time` / `resolved_time` = `strtotime(date('H:i:s'))` → **today’s calendar date at the current clock time**.

Normally the two dates agree, so `created_time` behaves like a full creation timestamp. The date fields are used for “today” comparisons (`resolved_date = $int_date`) and the time fields for ordering and display.

**Backdating quirk (PRESERVE the input contract, FIX the encoding — D29):**

- The staff create form no longer renders date/time inputs. Backdating is reachable only by a crafted POST with `ticket_date` in `n/j/Y` format and `ticket_time` in `g:i A` format.
- Legacy parses `ticket_date . " 12:00 AM"` and `ticket_time` separately. The date field becomes midnight of the selected date; the time field keeps **today’s** date at the selected clock time. This produces mismatched timestamps across days.
- If only one of the two fields is posted, the other parse fails; both variables are still “set,” so the failed value is stored as `0`. A one-sided POST therefore produces a ticket with `created_date = 0` or `created_time = 0`.
- Target MUST continue to accept the two fields when present (for API compatibility), but MUST store consistent values: `created_date` = midnight of the selected date; `created_time` = selected date at the selected time. Log the deviation.
- Invalid dates/times: legacy silently writes `0`. Target MUST reject them with a validation error.

**Display formats to preserve**

- Lists: `FROM_UNIXTIME(created_time, '%b %e, %l:%i %p')` → e.g. `Nov 4, 3:05 PM`.
- Ticket detail: `m/d/Y` for the date and `H:i:s` for the time.
- Reports: `Y-m-d H:i:s`.
- Dashboard “today” comparisons use `resolved_date = midnight today`.

**When writing new rows:** use the same encoding. Do not “modernize” `created_time` to midnight or `created_date` to a full timestamp. Do not add timezone offsets into the stored epochs; store UTC epochs that correspond to Asia/Manila wall time as the legacy app does.

---

## 11. Configuration and magic constants

Every legacy hardcode below MUST become a named configuration value with the **same default**. Do not bury it in code.

| Key | Legacy default | Where it appears | Notes |
|---|---|---|---|
| `categories.hardware_id` | `23` | open-list exclusion, hardware views, closed-request view | Business data. |
| `categories.announcements_id` | `24` | open-list exclusion, announcements view | Business data. |
| `categories.default_id` | `1` | email/EU ticket creation, edit rejection, admin list exclusion | Protected. |
| `locations.ihoms_id` | `15` | supervisor “IHOMS Only” filter and options | Business data. |
| `groups.default_id` | `1` | staff create fallback, PIS sync | Business data. |
| `approval.pending_cutoff` | `1784553600` | pending badge query | 2026-07-20 21:20 Asia/Manila. |
| `approval.hide_old_cutoff` | `1784692800` | “Hide tickets older than Jul 22, 2026” | 2026-07-22 12:00 Asia/Manila. |
| `counter.employee_ids` | `[334,566,335,1555,336,2243,2415]` | REG quick-select; JO is its complement | Preserve as a configurable list; do not merge the two buttons. |
| `counter.units_cutoff` | `2025-12-19 15:00:00` | per-ticket units calculation | Tickets after this use `assigned_gid`. |
| `survey.url_template` | `http://192.163.8.7/jblforms/form.php?use=V6U6202564133510372T1QA&ticketID={ticket_id}` | close action | Keep until changed. |
| `attachments.path` | `mods/tickets/attachments/` | cron storage | Target: outside web root. |
| `tickets.closed_list_limit` | `4000` | all-closed view | Preserve as a cap. |
| `tickets.search_limit` | `500` | search view | Preserve as a cap. |
| `dashboard.refresh_ms` | `5000` | monitoring grid | Preserve interval. |
| `debounce.seconds` | `1` | create/edit duplicate guard | Preserve. |
| `password.min_self` | `4` → target `12` | self password change | FIX D95. |
| `password.min_admin_reset` | `5` → target `12` | admin reset | FIX D95. |
| `email.imap_host`, `email.imap_port`, `email.imap_user`, `email.imap_password` | placeholders `SERVER`, `:PORT`, `USER`, `password` | `config/cron/process_inbox.php` | Must be configured; legacy file is not runnable as committed. |
| `application.base_url` | derived `http://<host-ip>:90/its/index.php` | notifications | Replace with explicit config; never derive from `gethostbyname`. |
| `application.title` | DB `application_settings.title` (`PHP Ticket System`) | title/menu | Preserve and allow override. |

Also configurable: approved category/group/location labels, ticket list page size (legacy 40), grid height (700), subject truncation length (80), and the email attachment folder pattern `YYYY_MM`.

---

## 12. Ticket lifecycle and state machine

### 12.1 States

- **Open** (`state_id = 1`): visible in open lists, editable, closable.
- **Closed** (`state_id = 2`): visible in closed lists, read-only once `approved_by_supervisor != 0`; may still be approved/disapproved/undone.
- **No reopen workflow exists.** Do not add one unless the data owner explicitly requests it.

### 12.2 Transitions

| From | Action | To | Conditions | Log |
|---|---|---|---|---|
| — | Staff create | Open, approval 0 | ACL >= 2; required fields valid | `No. of units/items/persons: <group>` (if group > 0), `Assigned to user: <name>` (if assignee > 0) |
| — | End-user create | Open, approval 0 | ACL 1 (or any authenticated user using the EU form); required fields valid; not a duplicate within debounce | No log |
| — | Email create | Open, approval 0 | Known sender; see §31 | No log |
| Open | Edit note/metadata | Open | Assignee required; category != 1; not closed+approved | Note text and one log per changed field |
| Open | Close | Closed | Authorized; id exists | `Ticket Closed.` + survey link |
| Closed, approval 0/2 | Approve | Closed, approval 1 | Actor is supervisor/admin; ticket is closed self-assigned | `Supervisor approved ticket.` + optional remarks |
| Closed, approval 0/1 | Disapprove | Closed, approval 2 | Actor is supervisor/admin; remarks required (FIX D41) | `Supervisor disapproved ticket. Remarks: <remarks>` |
| Closed, approval 1 | Undo | Closed, approval 0 | Actor is supervisor/admin | `Supervisor undid approval.` |
| Any | Batch approve | approval 1 | Every selected ticket passes individual checks | One log per changed ticket |
| Any | Batch disapprove | approval 2 | Remarks required; every selected ticket passes checks | One log per changed ticket |

### 12.3 Approval semantics (PRESERVE)

- Approval is meaningful for **closed tickets that the creator assigned to themselves** (`created_uid = assigned_uid`). The list and badge only show those.
- Closing a ticket auto-approves it when `assigned_uid != 0 AND assigned_uid != created_uid AND approved_by_supervisor = 0` (legacy close code). Note: migration `20260721_004` used `assigned_uid != eu_uid`, which differs when staff create for another end user. Preserve the **close-code** condition; document the historical difference.
- A ticket approved by close auto-approval may still be disapproved or undone by a supervisor; legacy allows it. Preserve, but audit.
- Disapproval remarks are required in the UI for individual and batch actions; the individual endpoint did not enforce it. Target enforces it everywhere.

### 12.4 Visibility rules

- End users see only tickets where they are creator or end user.
- Staff see all ticket lists, subject to module ACL.
- `all_open_tickets` hides categories 23 and 24.
- `all_hardware_tickets` shows **open** category 23 only.
- `all_announcements` shows **open** category 24 only; there is no closed-announcements view.
- `all_closed_request` shows **closed** category 23 only.
- `all_closed_tickets` shows every closed ticket, ordered by `resolved_time DESC`, capped at 4000.
- “Tickets by Subordinates” and “Tickets by Supervisor” are specialized approval views; see §23.
- Email tickets from unknown senders may have `eu_uid = 0`; target displays them as “Unknown sender” instead of hiding them (FIX D56).

---

## 13. Staff ticket creation (`?p=create_ticket` → `process_create_ticket`)

### Purpose

Let a Help Desk or Technician user record a request on behalf of an end user, set the department, category, and assignee, and optionally backdate it.

### Entry points and visibility

- **Create Ticket** link in the Tickets submenu opens a Kendo modal (`?p=create_ticket`) from any ticket screen.
- The modal is also opened by the **Create Ticket** button on list pages; the iframe/modal URL is `?p=create_ticket`.
- Module ACL: staff (`>= 2`). End users use the separate end-user form.

### Fields and defaults

| Field | Type | Behavior |
|---|---|---|
| `subject` | text | Free text. Legacy does not validate it. |
| `issue` | rich text (Kendo Editor) | Stores HTML. Prompt text asks for the user’s Output Messenger account, patient name, and location where applicable. |
| `eu_id` | combo | All users except `id = 1` and `group_id = 0`, ordered by full name. Placeholder `null`. |
| `location_id` | combo | `locations.type = 1`, ordered by name. Placeholder `null`. |
| `assigned_uid` | combo | If the actor’s ACL `>= 3`, the field is rendered disabled and locked to the actor’s own ID with a hidden input. Otherwise it lists users with `acl_id >= 2 AND id != 1`, plus `0 = No User Assignment`. |
| `category_id` | combo | `categories.status = 1`, ordered by name. Placeholder `null`. Includes category 1 and 23/24 if active. |
| `group_id` | none | No group field is rendered. The processor defaults it to `1`. |
| `ticket_date` / `ticket_time` | none in current UI | Not rendered; reachable only by crafted POST. Formats `n/j/Y` and `g:i A`. |

### Validation (legacy, then target)

Legacy checks, in order:

1. Debounce: if `time() - $_SESSION['last_write'] < 1`, add **“Too many queries.”**
2. `strlen($issue) < 5` → **“The subject was too short or left blank”** (this is the subject check bug: it tests `$issue`).
3. `strlen($issue) < 5` → **“The issue was too short or left blank”**.
4. `location_id == 'null'` → **“A location is required”**.
5. `category_id == 'null'` → **“A category is required”**.
6. `eu_id == 'null'` → **“A end user is required”**.
7. No validation of `subject`, `assigned_uid`, date/time validity, or category existence.
8. No role check inside the processor; only the session check.

**Target (FIX D26):**

- Validate `subject` as required, 5–255 characters after trim; reject HTML-only/whitespace-only values.
- Validate `issue` as required, at least 5 visible characters after stripping tags; cap the stored size (for example 200 KB) and reject oversized payloads.
- Validate `location_id`, `category_id`, and `eu_id` as existing, active rows. Category 1 is allowed for staff creation (only editing rejects it).
- Validate `assigned_uid` is 0 or an existing active staff user. For ACL >= 3, force the actor’s ID server-side; ignore a posted value.
- Accept backdate fields only when both are present; otherwise reject one-sided input.
- Keep the debounce message if the UX owner wants it; the recommended message is “Please wait a moment before submitting again.”

### Database effects

Legacy inserts one `tickets` row with:

- `subject`, `issue` (escaped), `preview` (plain text derived from `issue`), `location_id`, `category_id`, `state_id = 1`, `created_uid = session user`, `created_date`, `created_time`, `eu_uid`, `assigned_uid`, `assigned_gid = group_id` (default 1).
- `assigned_date`/`assigned_time` remain `0`; `resolved_*` remain `0`; `approved_by_supervisor` defaults `0`.
- No `place`/`contact` (staff form has no fields).
- `preview` = `strip_tags(htmlspecialchars_decode(stripslashes($issue)))`, then SQL-escaped.

Then, in the same request:

- If `group_id > 0` (always true because of the default), insert a log row `No. of units/items/persons: <groups.name>`.
- If `assigned_uid > 0`, insert a log row `Assigned to user: <fn ln>` using the assignee’s display name.
- There is **no** “Ticket Created” log row and **no** notification row.

The legacy code calls `mysqli_begin_transaction`/`commit`/`rollback`, but the tables are MyISAM, so these are no-ops. The target MUST use a real transaction so a ticket is never created without its logs.

### Logs and notifications

- Exact strings: `No. of units/items/persons: <group name>`, `Assigned to user: <full name>`.
- Timestamps on creation logs use the backdated `$ins_date/$ins_time` when backdating, otherwise the request’s `$int_date/$int_time`.
- No notification is created for staff-created tickets.
- **FIX D27:** do not write the group log when no group was explicitly chosen. The target should set `assigned_gid = 0` and skip the log if the form has no group field; if the data owner wants group selection, add a real selector and validate it. Document the changed `assigned_gid` values for new rows.
- **FIX D28:** the success message is currently `The ticket was created. <epoch>`. Use `The ticket was created.` and do not leak the server epoch.

### Edge cases

- A subject may be empty in legacy; the ticket is still created. Target rejects it.
- An assignee of `0` is allowed for ACL 2; the ticket is created unassigned. ACL >= 3 is always self-assigned.
- A crafted `group_id` can be any `groups.id`; there is no existence check. Target validates it.
- A crafted `assigned_uid` can be any ID, including an end user or a deleted user. Target validates active staff.
- A backdated ticket with both fields stores the selected date in `created_date` but today’s date at the selected clock time in `created_time` (see §10); a one-sided POST stores `0` in the missing half. Target fixes both.
- A single session `last_write` is shared by staff-create submissions, so two different staff-create forms submitted within one second collide. Preserve the one-second guard, but scope the target's idempotency key per form/action.

### Rebuild decision

PRESERVE the form, fields, self-assignment rule, category list, and log strings. FIX subject validation, group default/logging, transactionality, authorization, backdate consistency, and the success message. Add an idempotency key so a double-click cannot create two tickets.

---

## 14. End-user ticket creation (`?p=end_user&v=new_ticket` → `process_eu_ticket`)

### Purpose

Give non-staff users a minimal form that creates a ticket for themselves with no assignment.

### Entry points and visibility

- End users land here after login (`?p=end_user&v=new_ticket`).
- The end-user page shows a left menu with **New Ticket** and **View Tickets**, and a right pane with the form or list.
- Staff can technically open `?p=end_user` if the module ACL permits; keep it accessible but do not link it in the staff menu.

### Fields

| Field | Required | Validation |
|---|---|---|
| `subject` | yes | At least 5 characters (byte length in legacy). |
| `place` | yes | Truthy non-empty string; message “Please indicate your location/office/department/section.” |
| `contact` | yes | Truthy non-empty string; message “Please indicate your Output Messenger/Telephone number.” |
| `issue` | yes | Rich text; at least 5 characters; stored as HTML. |
| `location_id` | implicit | Taken from `$_SESSION['location_id']`; not posted. |
| `category_id` | implicit | Always `1`. |
| `assigned_uid` | implicit | `0` (unassigned). |
| `eu_uid` | implicit | Session user. |

The form labels include red asterisks and the issue prompt asks for the Output Messenger account, patient name, and location where applicable.

### Validation and duplicate guard (legacy)

1. `place` and `contact` must be truthy; otherwise the two messages above.
2. `strlen($subject) < 5` → **“The subject was too short.”**
3. `strlen($issue) < 5` → **“The issue was too short.”**
4. If no errors, query for an existing ticket by the same `created_uid`, exact `subject`, exact `issue`, and `created_time > $int_time - 1` (the session debounce). If found → **“Ticket is already created.”**
5. No role check in the processor and no session guard at the top of the file. It relies on `header.php` having redirected unauthenticated users.

**Target (FIX D32, D33):**

- Require an authenticated session and the end-user form permission.
- Trim and normalize input; validate lengths and HTML payload size.
- Keep the exact duplicate guard for the debounce window, and add a per-session idempotency key so retries do not create duplicates.
- Validate `place`/`contact` as non-empty after trim, with sensible maximum lengths (for example 1000/45, matching the columns).

### Database effects

Legacy inserts one row:

```text
subject, issue, preview, created_uid = session user,
created_date = $int_date, created_time = $int_time,
location_id = session location, state_id = 1, eu_uid = session user,
category_id = 1, place, contact
```

- `assigned_uid` remains `0`, `assigned_gid` remains `0`.
- No log row, no notification row, no assignment.
- `preview` is the stripped plain text of `issue`.
- The transaction/commit calls are no-ops on MyISAM.

### Success output

Legacy prints:

- `Ticket #<id> has been created.`
- Then it counts all open tickets excluding category 23, subtracts one (the ticket just created), and if the result is nonzero prints:
  - `There are currently <n> open tickets. We will attend your concern as soon as we can.`
  - `Thank you for your patience.`

Preserve these strings and the count semantics; fix the count to exclude the new ticket explicitly rather than subtracting one, and do not expose internal errors.

### Edge cases

- `place` or `contact` equal to `"0"` is falsy in PHP and rejected. Target treats `"0"` as a valid value if the data owner wants; document the choice.
- An end user with `location_id = 0` creates a ticket with `location_id = 0`, which is then hidden by INNER JOINs. Target must either require a valid location or store/display “Unspecified” without hiding the ticket.
- The duplicate guard only catches an exact subject+issue pair within one second. A user can submit the same request twice after one second.
- The success count uses `state_id = 1 AND category_id != 23` and only counts tickets with an existing location (`INNER JOIN locations`); it includes category 24 announcements. Preserve unless the data owner says otherwise.
- No notification is written despite the commented-out code.

### Rebuild decision

PRESERVE the minimal form, implicit category/location/assignee, validation messages, duplicate guard, and success text. FIX authentication, idempotency, HTML sanitization, location validity, and the open-ticket count. Do not add category or assignee fields to the end-user form.

---

## 15. Ticket editing and notes (`?p=edit_ticket&id=<id>` → `process_edit_ticket`)

### Purpose

Let staff review a ticket’s full history, add a note, and change its end user, location, category, units/group value, or assignee. Subject and issue are intentionally read-only after creation.

### Entry points and visibility

- Double-click a ticket row in any list, or open a `#ticket=<id>` deep link, to load the detail.
- The detail is rendered as a Kendo tab or modal by refreshing `?p=edit_ticket&id=<id>`.
- The edit form is rendered only when `session.user_acl != 1` **and** `(state_id = 1 OR (state_id = 2 AND approved_by_supervisor = 0))`.
- End users see the ticket and its history read-only; they cannot edit or close from this screen (the close button is inside the form block).
- There is no server-side ACL or ownership check in `process_edit_ticket`; only the session check.

### Displayed fields

Read-only: ticket ID, subject, issue (rendered HTML), creator name, creation date/time, place/contact cards, approval status, and the full log timeline (oldest first, joined to `users`).

Editable (when the form is shown):

| Field | Default | Notes |
|---|---|---|
| `update_ticket` | empty rich text | “Add Note / Update Description”. This is the only free-text change; it is appended to the log, not written to `tickets.issue`. |
| `eu_id` | current `eu_uid` | All users except `id = 1`, ordered by name. |
| `location_id` | current | `locations.type = 1`, current first. |
| `category_id` | current | `categories.id != current AND status = 1`; category 1 is rejected server-side. |
| `group_id` | current `assigned_gid` | All `groups`, ordered by ID, labeled **“No. of units/items/persons”**. Always renders, including group 1. |
| `assigned_uid` | current | `0` = No User Assignment plus staff users (`acl_id >= 2 AND id != 1`). Server requires a nonzero value. |

### Validation (legacy order)

1. `!$assigned_uid` → **“Please assign a user.”**
2. Debounce: look for a `tickets_log` row by the same user, `time > $int_time - 1`, and `data = $update_ticket`; if found → **“Too many queries.”**
3. `state_id != 1 && approved_by_supervisor != 0` → **“Ticket already closed or reviewed.”**
4. `location_id == 'null'` → **“A location is required”**.
5. `category_id == 'null' || category_id == 1` → **“A category is required”**.
6. `eu_id == 'null'` → **“A end user is required”**.
7. No validation that the ticket exists, that the actor may edit it, that the category/location/user exists, or that the note is non-empty.

**Target (FIX D34):**

- Load the ticket with `FOR UPDATE` inside a transaction; 404 if missing.
- Enforce the policy in §6.3 and the state/approval lock.
- Validate all IDs as existing/active; keep the category-1 rejection.
- Require an assignee for edits, preserving the legacy message.
- Require the note to be non-empty if the request only adds a note; allow metadata-only edits.
- Add optimistic concurrency (`updated_at` or version) to prevent lost updates; add a nullable `updated_at` column if needed.
- Sanitize the note and store sanitized HTML.

### Database effects and logs

For each changed field, legacy updates one column and inserts one log row. It does **not** write subject, issue, place, or contact.

- Note text (if length >= 1) → log row with the note HTML.
- End user changed → `UPDATE tickets SET eu_uid = ...`; log `End User Changed: <fn ln>.`
- Location changed → `UPDATE tickets SET location_id = ...`; log `Location changed to: <name>.`
- Category changed → `UPDATE tickets SET category_id = ...`; log `Category changed to: <category>.`
- Group/units changed → `UPDATE tickets SET assigned_gid = ...`; log `No of units/items/persons assigned was changed from <old> to <new>` (no trailing period).
- Assignee changed → `UPDATE tickets SET assigned_uid = ...`; log `User assignment has changed to <strong><fn ln></strong>`.
- No update to `assigned_date`/`assigned_time`; they stay `0` unless set elsewhere.
- No notification.
- On success, the endpoint re-renders the ticket’s issue/log fragment into `#server_message<id>`; the surrounding page updates via AJAX.

### Edge cases

- A closed ticket with `approved_by_supervisor = 0` still shows the edit form and is **accepted** by the server; only closed + approved is locked. Preserve this behavior because it lets staff correct a pending ticket before review, and enforce the same rule server-side.
- The same note submitted twice within one second is blocked by the log debounce; a different note is not.
- Changing the end user does not change `created_uid`; the creator remains the original submitter.
- Changing the group/units value is unrelated to team assignment; see §3.
- Deleting a category/location/user referenced by a ticket makes the edit dropdown lose the current label. Target uses soft deletes and shows the archived label.
- The `INNER JOIN users` on the log timeline hides entries by deleted users. Target uses `LEFT JOIN`.
- The response fragment includes raw log HTML and can execute injected scripts. Target sanitizes.

### Rebuild decision

PRESERVE the read-only subject/issue, note-only text update, metadata fields, category-1 rejection, assignee requirement, and the exact close/approval lock (open and closed-unapproved editable, closed-approved locked). FIX authorization, existence checks, transactions, concurrency, and sanitization.

---

## 16. Ticket closing (`?p=process_close_ticket&id=<id>`)

### Purpose

Mark a ticket resolved, record who closed it and when, log the closure, and give the closer the survey link to send to the requester.

### Entry point and visibility

- The **Close Ticket** button is rendered only when `state_id = 1` and only inside the staff edit form.
- The button sets a hidden `btn_mode = close`, submits the whole edit form to `?p=process_close_ticket&id=<id>`, and asks “The ticket will be closed. Are you sure?”.
- The endpoint accepts any POST with `submit` set and any logged-in session. It ignores all other form fields, including the note.
- End users have no close button; direct access is still possible in legacy (FIX D37).

### Fields

None are read except `$_GET['id']` and the presence of `$_POST['submit']`. There is no resolution text field, no reassignment, and no confirmation token.

### Database effects

1. Look up `tickets.id`, `tickets.assigned_uid`, and the assignee’s `users.location_id` with a `LEFT JOIN`.
2. Compute fallback session user/location values for the unused survey parameters; the survey URL itself uses only the ticket ID, so these values have no visible effect.
3. `UPDATE tickets SET state_id = 2, resolved_uid = session user, resolved_date = $int_date, resolved_time = $int_time WHERE id = <id>`.
4. Auto-approve: `UPDATE tickets SET approved_by_supervisor = 1 WHERE id = <id> AND assigned_uid != 0 AND assigned_uid != created_uid AND approved_by_supervisor = 0`.
5. Insert a log row unless the same user/ticket/data already exists:
   `Ticket Closed. <br> <a href='<survey_url>' target='_blank'><survey_url></a></p>`
   where `<survey_url>` = `http://192.163.8.7/jblforms/form.php?use=V6U6202564133510372T1QA&ticketID=<id>` by default.
6. On success show “The ticket has been closed.” plus “How well did you do? Ask the user to answer a short survey!” and a clickable link that copies the URL to the clipboard.
7. On a repeat close, show “The ticket is already closed.” (the update still runs; only the duplicate log is skipped).
8. If `submit` is absent, print `invalid request`.

The `resolution` column is **never** written. `with_survey` is hardcoded `false`, so the survey prompt always shows.

### Edge cases

- Closing a nonexistent ticket updates 0 rows but still inserts a log row with the supplied ID and shows success. Target returns 404.
- Closing an already-closed ticket overwrites `resolved_uid`, `resolved_date`, and `resolved_time`. Target must be idempotent: if already closed, keep the original resolver/timestamps and return the “already closed” message.
- Closing without an assignee leaves `approved_by_supervisor` at 0 and does not create a pending-review item (pending requires `created_uid = assigned_uid`).
- Closing a ticket assigned to someone else auto-approves it even if the creator is an end user. Preserve.
- The log contains a stray `</p>` and stores the survey URL as HTML. Target should keep the visible text and link but may fix the malformed tag; use an idempotency key instead of exact-string matching.
- The survey URL is an internal HTTP address; make it configurable and do not expose it to unauthorized users.

### Rebuild decision

PRESERVE the close action, timestamps, resolver, auto-approval condition, survey text, clipboard behavior, and “already closed” outcome. FIX authorization, existence, idempotency, transactions, and the malformed log HTML. Do **not** start using `resolution` unless the data owner requests it.

---

## 17. Individual approval (`process_approve_ticket`, `process_disapprove_ticket`, `process_undo_approve_ticket`)

### Purpose

Allow a supervisor to review a subordinate’s closed, self-assigned ticket and mark it approved, disapproved, or pending again.

### Entry points and visibility

- Opened from **Tickets by Subordinates** (§23): double-click a ticket, then use the action buttons in the right pane.
- The list uses direct reports by default and indirect reports when `hierarchy=1`; the badge counts direct reports only. Both require `created_uid = assigned_uid` and `state_id = 2`.
- Approve/Disapprove are shown when the current value is not `1`; Undo is shown when it is `1`.
- The module `tickets_by_subordinates` has ACL 3 in the migration. The menu is hidden when the user has no active direct report.
- Endpoints check only `isset($_SESSION['user_id'])`. They do not verify that the actor is a supervisor, that the ticket belongs to a subordinate, or that the ticket is in a valid state.

### Inputs

- `id` in the query string, cast to `(int)` in the individual endpoints.
- `remarks` in POST, trimmed; optional for approve, optional at the endpoint for disapprove (the UI requires it), ignored by undo.
- No CSRF token.

### Behavior and exact output

**Approve**

1. If the ticket does not exist → `<div class="error">Ticket not found.</div>`, exit.
2. If `approved_by_supervisor == 1` → `<div class="info">Ticket already approved.</div>`, exit.
3. `UPDATE tickets SET approved_by_supervisor = 1 WHERE id = <id>`.
4. Log `Supervisor approved ticket.` plus ` Remarks: <remarks>` when remarks are non-empty.
5. Output `<div class="success">Ticket approved.</div>`.

This means a disapproved (`2`) ticket can be approved directly, and an open ticket can be approved if the endpoint is called directly.

**Disapprove**

1. Same existence check.
2. If `approved_by_supervisor == 2` → `Ticket already disapproved.`
3. `UPDATE ... = 2`.
4. Log `Supervisor disapproved ticket.` plus ` Remarks: <remarks>` when remarks are non-empty.
5. Output `Ticket disapproved.`

The individual UI blocks an empty remark, but the endpoint does not.

**Undo**

1. Same existence check.
2. If `approved_by_supervisor != 1` → `Ticket is not currently approved.`
3. `UPDATE ... = 0`.
4. Log `Supervisor undid approval.`
5. Output `Approval undone.`

### Target rules (FIX D13, D41)

- Authorize: ACL 9 or the actor is the creator’s direct/indirect supervisor.
- Require the ticket to be `state_id = 2` and `created_uid = assigned_uid`. Reject others with a clear message.
- Preserve the 0→1, 0/1→2, and 1→0 transitions; allow 2→1 (legacy does).
- Require non-empty remarks for disapproval; cap length (for example 2000 characters) and sanitize.
- Make each action idempotent under retry: if the requested state already holds, return the legacy “already” message without a second log.
- Write the ticket update and log in one transaction.
- Add CSRF.
- Do not allow approving your own ticket unless you are ACL 9 (legacy would allow it if you are your own subordinate only through a self-link; cycle prevention in §23 makes self-supervision impossible).
- Keep the exact log strings; they are part of the audit history.

### Rebuild decision

PRESERVE the tri-state transitions, UI placement, messages, optional approve remarks, and log strings. FIX authorization, state checks, required disapproval remarks, CSRF, transactions, and retry idempotency.

---

## 18. Batch approval and disapproval

### Purpose

Clear a backlog of subordinate tickets in one action.

### UI behavior (PRESERVE)

- The **Tickets by Subordinates** grid adds a checkbox column and a toolbar: **Check All**, **Approve Selected**, **Disapprove Selected**, and `<n> selected`.
- Selection is held in a JavaScript `Set`, so it survives grid re-renders and virtual scrolling within the page session.
- **Approve Selected** confirms “Approve <n> selected ticket(s)?” and posts `ids[]` to `process_batch_approve_tickets.php`.
- **Disapprove Selected** opens a modal with a required remarks textarea; empty remarks show “Please enter remarks.” and do not post.
- On success, the UI alerts the JSON `message`, clears the selection when `success > 0`, and refreshes the grid.
- Batch endpoints return JSON, unlike the individual endpoints which return HTML.

### Endpoint behavior (legacy)

**Batch approve**

1. Require a session; otherwise `{"success":0,"message":"Unauthorized"}`.
2. Read `ids` (array) and `remarks`; cast IDs to int, drop `<= 0`.
3. If empty → `{"success":0,"message":"No tickets selected."}`.
4. `UPDATE tickets SET approved_by_supervisor = 1 WHERE id IN (<ids>) AND approved_by_supervisor != 1`.
5. `affected = mysqli_affected_rows`.
6. For **every** submitted ID, insert a log row `Supervisor approved ticket.` plus ` Remarks: <remarks>` if present — including IDs that did not exist or were already approved.
7. Return `{"success":<affected>,"message":"<affected> ticket(s) approved."}`.

**Batch disapprove**

1. Require a session; otherwise Unauthorized.
2. Require non-empty `remarks` → `{"success":0,"message":"Remarks are required for disapproval."}`.
3. Cast/filter IDs; empty → `No tickets selected.`
4. `UPDATE ... = 2 WHERE id IN (...) AND approved_by_supervisor != 2`.
5. Log `Supervisor disapproved ticket. Remarks: <remarks>` for every submitted ID, even unaffected ones.
6. Return `{"success":<affected>,"message":"<affected> ticket(s) disapproved."}`.

No relationship, state, existence, or ownership checks. No transaction. A malicious actor can change any ticket by ID.

### Target rules (FIX D14, D43)

- Validate the entire selection before changing anything: every ID must exist, be closed, be self-created/self-assigned, and belong to the actor’s subordinate tree (or actor ACL 9). If any ID fails, reject the whole batch with a per-ID error list; do not partially apply.
- Use one transaction.
- Log only the tickets whose value actually changed.
- Preserve the JSON shape and the `success` count (number actually changed).
- Require remarks for disapproval (already enforced) and sanitize them.
- Cap the batch size (for example 500) and require CSRF.
- Return the legacy messages for empty selection and missing remarks.

### Rebuild decision

PRESERVE the toolbar, selection model, confirmation, JSON contract, and messages. FIX authorization, validation, atomicity, and over-logging.

---

## 19. Pending-review badge and approval cutoffs

### Purpose

Show a supervisor how many subordinate tickets are awaiting review, without scanning the list.

### Behavior (PRESERVE)

- The badge is a red pill inside the **Tickets by Subordinates** menu item; it is hidden at zero.
- It fetches `mods/tickets/data/get_pending_review_count.php` once on page load (no polling).
- Query: count tickets where:
  - `created_uid IN (<active direct subordinates>)`
  - `created_uid = assigned_uid`
  - `state_id = 2`
  - `approved_by_supervisor = 0`
  - `created_time >= 1784553600` (2026-07-20 21:20 Asia/Manila)
- If the user has no subordinates, the count is `0`.
- The response is `{"count":<int>}` with `Content-Type: application/json`.

The subordinate list view uses a **different** cutoff:

- `approval.hide_old_cutoff = 1784692800` (2026-07-22 12:00 Asia/Manila), labeled in the UI as **“Hide tickets older than Jul 22, 2026”**.
- The checkbox **Hide old tickets** defaults to **checked**, so older pending tickets are hidden from the list but still counted by the badge if they are after the badge cutoff.
- Rows older than the hide cutoff are rendered at 40% opacity when the filter is off.

### Target rules (FIX D44, D45)

- Keep both cutoffs configurable with the legacy defaults.
- Compute the label from the cutoff (`Hide tickets older than <formatted date>`), not a hardcoded string.
- Keep the direct-subordinate scope for the badge; the list’s “Include indirect subordinates” option affects only the list, not the badge.
- Use the same `state = 1` active-user filter for subordinates.
- Cache the count briefly (for example 30 seconds) and invalidate on approval changes; do not add polling unless the UX owner asks.

### Rebuild decision

PRESERVE the badge, cutoff values, default hide-old behavior, opacity, and direct-vs-indirect distinction. FIX hardcoded labels and make the values configurable.

---

## 20. Ticket list views

### Shared presentation (PRESERVE)

- Each list page is a full-width section with a left grid and a right detail pane (or a single grid for some views).
- The Tickets submenu is rendered at the bottom.
- Grids use Kendo UI: page size 40, virtual scrolling, height 700, filterable, sortable, single select, no pager.
- Double-clicking a row opens the ticket detail (tab or modal); `#ticket=<id>` deep links auto-open it.
- The row template shows the creation date/time, the end user’s full name in bold (joined by `eu_uid`, despite the JSON field being called `mail`), category, `#<id>`, subject (truncated to 80 characters), the location name (JSON field `name`), state, and “Attended By: <assignee or none>”.
- The list refreshes after closing the detail window; use a short delay (about 300 ms) so the close animation finishes.
- No server-side pagination in legacy; all rows are sent to the browser. The target may paginate server-side but must preserve visible ordering, filters, page size, and deep-link behavior.

### View catalogue (legacy SQL semantics)

| View (module) | Data endpoint | Filter | Order / cap |
|---|---|---|---|
| All Open Tickets (`all_open_tickets`) | `get_all_open_tickets.php` | `state_id = 1 AND category_id NOT IN (23,24)` | `id DESC` |
| All Closed Tickets (`all_closed_tickets`) | `get_all_closed_tickets.php` | `state_id = 2` | `resolved_time DESC`, `LIMIT 4000` |
| All Hardware Tickets (`all_hardware_tickets`) | `get_all_open_hardware_tickets.php` | `state_id = 1 AND category_id = 23` | `id DESC` |
| All Announcements (`all_announcements`) | `get_all_open_announcements.php` | `state_id = 1 AND category_id = 24` | `id DESC` |
| Closed Request (`all_closed_request`) | `get_all_closed_request.php` | `state_id = 2 AND category_id = 23` | `resolved_time DESC` |
| Closed Tickets By You (`all_closed_tickets_by_you`) | `get_all_tickets_closed_by_user.php` | intended `resolved_uid = current` and closed; actual legacy call omits `id` and defaults to `state_id = 1` | `id DESC` |
| Created By User (`created_by_user`) | `get_all_created_by_user.php?id=<current>` | `created_uid = user OR resolved_uid = user OR assigned_uid = user`; all states | `id DESC` |
| Assigned To You (`assigned_to_you`) | `get_all_tickets_assigned_to_user.php?id=<current>` | `assigned_uid = current AND state_id = 1` | `id DESC` |
| Assigned To Group (`assigned_to_group`) | `get_all_tickets_assigned_to_group.php?id=<session group_id>` | `assigned_gid = session group_id AND state_id = 1` | `id DESC` |
| Unassigned Tickets (`unassigned_tickets`) | `get_all_unassigned_tickets.php` | `state_id = 1 AND assigned_uid = 0` | `id DESC` |
| Search Tickets (`search_tickets`) | `get_all_tickets.php?p=search_tickets` | dynamic filters; all states | `id DESC`, `LIMIT 500` |
| Tickets by Subordinates (`tickets_by_subordinates`) | `get_all_tickets_by_subordinates.php` | `created_uid IN (subordinates) AND created_uid = assigned_uid AND state_id = 2` plus optional hide-old/search/personnel | `id DESC` |
| Tickets by Supervisor (`tickets_by_supervisors`) | `get_all_tickets_by_supervisors.php` | `created_uid = current user’s supervisor AND created_uid = assigned_uid AND state_id = 2` | `id DESC` |
| End User → View Tickets (`end_user&v=view_tickets`) | `get_all_tickets_created_by_user.php?p=end_user&q=all` | `(created_uid = current OR resolved_uid = current)`; `q=all` removes the default open filter | `id DESC` |

### Known legacy defects in these views

- **Closed Tickets By You is broken.** The page calls the endpoint without `id`; the endpoint falls back to `$_GET['id']` → `0`, and because `q != all` it filters `state_id = 1`. Result: an empty/wrong list. **FIX D52:** pass the current user ID and filter `state_id = 2, resolved_uid = current`.
- **Assigned To Group conflates concepts.** It compares `tickets.assigned_gid` to `users.group_id`. Because `assigned_gid` is also the units field, the result is not a real team queue. **FIX/decision D53:** preserve the legacy query until the data owner confirms the intended meaning; label the view accurately (“Assigned group/units value”) or implement a real team queue using `users.group_id` and `assigned_uid IN (team members)`. Do not silently change results.
- **Ambiguous row fields.** The legacy queries alias `locations.name` as `name` and the end-user full name as `mail`; the template renders the person in the bold line and the location in the `.ticket_eu` slot with no explicit labels. **FIX D61:** render explicit “Requester” and “Location” values in their correct places while preserving the visual hierarchy.
- **INNER JOINs hide data.** Closed lists use `INNER JOIN users ON users.id = tickets.eu_uid`; tickets with `eu_uid = 0` (unknown email sender) disappear. Category/location/user deletions also hide tickets. **FIX D56:** use `LEFT JOIN` and render “Unknown/Archived” instead of dropping rows.
- **Created By User is really “tickets involving user.”** It includes assigned and resolved tickets, not only created ones. **FIX D62:** either rename the view to “Tickets Involving User” or restrict it to `created_uid`; document the choice.
- **Open list hides announcements/hardware.** This is intentional; preserve.
- **No closed-announcements view.** Preserve (do not invent one unless requested).
- **Closed list cap.** 4000 rows; make configurable but preserve the default.
- **Search cap.** 500 rows; make configurable but preserve the default.
- **No pagination.** Large lists rely on virtual scrolling. Target may paginate but must keep the default page size and filters.

### Rebuild decision

PRESERVE every view, its filter, its ordering, the 4000/500 caps, and the row layout. FIX the broken “Closed By You” view, the label swap, INNER JOIN data loss, and the “Created By User” naming. Treat the group/units conflation as a data-owner decision, not a unilateral cleanup.

---

## 21. Ticket search (`?p=search_tickets`)

### Fields (PRESERVE)

- Ticket ID (`id` input, sent as `ticket_id`)
- End User (`eu_id`; all users with `group_id != 0`, labeled by full name)
- Location (`location_id`; `locations.type = 1`)
- Category (`category_id`; all categories, including inactive)
- Subject (`subject`; `LIKE %value%`)
- Issue (`issue`; `LIKE %value%`)
- Closed By (`closed_by`; users with `acl_id <> 1`, labeled by full name)

### Behavior

- **Search** posts all fields as query parameters to `get_all_tickets.php?p=search_tickets`.
- Each non-empty, non-`null` field becomes an `AND` equality or `LIKE` filter. Empty values are ignored.
- Results are ordered `id DESC` and capped at 500.
- The grid uses the same row template as other lists, but its column title is incorrectly **“No. of units/items/persons”**. **FIX D60:** use “Search Results”.
- **Clear Results** clears only the client-side Kendo filter; it does not clear the form or rerun the server query. **Print Results** calls the same `clear_filter()`; it does not print. **FIX D59:** implement a real print/export of the current filtered result set and a real reset that clears the form and reloads.

### Target rules (FIX D17)

- Parameterize every filter.
- Validate dates/IDs.
- Preserve the 500-row cap and the field list.
- Keep the `null` placeholder semantics.
- Scope results by the viewer’s permissions: end users may not use this screen; staff see all results.

### Rebuild decision

PRESERVE fields, filter semantics, ordering, and cap. FIX the title, print/reset behavior, SQL injection, and authorization.

---

## 22. Ticket detail, history, and deep links

### Layout (PRESERVE)

- The detail shows a header card with the creator’s name and creation date/time, a `#<id>` badge, the subject, and the issue HTML.
- Below it is the activity timeline: every `tickets_log` row for the ticket, ordered `id ASC`, with the author’s name, the log date/time, and the log HTML.
- Staff see the edit/close controls when allowed; end users see a read-only view.
- The detail can be a Kendo tab (list pages) or a modal window (search/other pages). The close action refreshes the underlying grid.

### Data rules

- The detail query joins `users` (creator), `categories`, and `locations` with INNER JOINs. A missing category/location/user makes the ticket impossible to open. **FIX:** use `LEFT JOIN` and show archived/unknown labels.
- The timeline joins `tickets_log` to `users` with INNER JOIN. Deleted authors hide their logs. **FIX:** `LEFT JOIN`, show “Deleted user.”
- The issue and logs are echoed as raw HTML. **FIX:** sanitize on write and escape on read.

### Deep links (PRESERVE)

- `#ticket=<id>` on a list page auto-opens that ticket.
- The back button restores the previous list/selection state.
- Closing the detail clears the fragment and refreshes the list.
- The target’s router must not treat the fragment as a server route.

### Attachments

- Legacy shows no attachments on the detail. **Target decision:** show a read-only attachment list with authorized download links for rows that exist; see §33. Upload is optional and disabled by default.

### Rebuild decision

PRESERVE the information hierarchy, timeline order, action placement, and deep-link behavior. FIX data loss from INNER JOINs, XSS, and missing attachment visibility.

---

## 23. Supervisors and supervisor-specific views

### 23.1 Administration → Supervisors (`?p=supervisors`)

**Purpose:** assign or remove a user’s direct supervisor and review the reporting hierarchy.

**Entry/visibility:** ACL 9. Left menu with the supervisor screen; grid on the right; double-click a row to open the edit window.

**List (`get_all_users.php`):**

- Active users only (`u.state = 1`), `acl_id != 0`.
- Optional filters: name, email, supervisor name, location, and **IHOMS Only** (`location_id = 15`).
- The IHOMS checkbox is **checked by default** in the UI, so the initial list is IHOMS-only.
- Columns: name, email, location, supervisor. Ordered by last name, first name.

**Edit (`edit_supervisor.php`):**

- Shows the selected user and a Kendo combo of possible supervisors.
- Supervisor options: active users, excluding the user themselves; when IHOMS-only is on, only `location_id = 15`.
- **Set Supervisor** posts to `?p=process_set_supervisor&id=<user>`; **Remove Supervisor** posts to `?p=process_remove_supervisor&id=<user>`.
- Set validation: `supervisor_id >= 1` (“Please select a supervisor.”), not self (“A user cannot be their own supervisor.”), and the supervisor must exist and be active (“Selected supervisor does not exist or is inactive.”). Success: “Supervisor has been assigned.”
- Remove sets `supervisor_id = NULL`; success: “Supervisor has been removed.”
- No cycle detection: A can supervise B and B supervise A. **FIX D70:** reject cycles (walk the supervisor chain) and enforce a configurable maximum depth.

**Target rules:** ACL 9, CSRF, transactions, audit log. Preserve the IHOMS default and the exact messages. Allow clearing a supervisor (equivalent to remove).

### 23.2 Tickets by Subordinates (`?p=tickets_by_subordinates`)

- ACL 3, menu hidden without an active direct report.
- Lists **closed, self-created/self-assigned** tickets of the actor’s direct subordinates.
- **Include indirect subordinates** performs a breadth-first walk of `supervisor_id`, with cycle protection, and includes all descendants.
- **Personnel** combo filters to one subordinate (only if the ID is in the allowed set).
- **Hide old tickets** defaults on and uses `approval.hide_old_cutoff`; rows older than the cutoff are dimmed to 40% when shown.
- Search filters `subject LIKE %q% OR issue LIKE %q%`.
- Row status: `Approved` (green), `Disapproved` (red), `Pending` (orange).
- Double-click opens the detail; the right pane appends Approve/Disapprove (or Undo) actions and a remarks box.
- The list has no pagination; it loads all matching rows.

### 23.3 Tickets by Supervisor (`?p=tickets_by_supervisors`)

- ACL 3; menu hidden when the logged-in user has no supervisor.
- Shows **closed, self-created/self-assigned** tickets created by the logged-in user’s **own supervisor**.
- Header displays “Supervisor: <name>” or `N/A`.
- Same hide-old cutoff and search semantics; no approve actions (these are the supervisor’s own tickets, not the user’s subordinates).
- If the user has no supervisor, the endpoint returns `[]`.

### Rebuild decision

PRESERVE the reporting hierarchy, IHOMS filter, direct/indirect distinction, list semantics, status labels, and action placement. FIX cycle creation, authorization, N+1 queries, and hardcoded cutoff labels.

---

## 24. User administration (`?p=users`)

### 24.1 List

- ACL 9. Grid shows the user’s last name and first name; the endpoint also returns `id` and `last_login_dt`, but the grid does not display them. Excludes `id = 1`, ordered by last name, first name.
- Double-click opens the edit window with two tabs: **User Details** and **Reset Password**.
- No pagination; page size 40, virtual scroll.

### 24.2 Add user (`?p=add_user` → `process_add_user`)

**Fields:** first name, last name, email/username, location (`locations.type = 1`), group (all groups), access level (all ACLs).

**Validation (legacy):**

- `strlen(fn) < 3` → “The first name used was too short.”
- `strlen(ln) < 3` → “The last name used was too short.”
- `strlen(mail) < 1` → “The username used was too short.”
- Duplicate `mail` → “A user with that username already exists.”
- No validation that location/group/acl exist, no email format check, no password field, no state field.

**Database effects and defects:**

- Inserts `fn, ln, mail, password, location_id, group_id, acl_id, fpc = 1`.
- **Omits** `state`, `login_attempts`, `last_login_ip`, `last_login_dt`, `profile_pic_path`. Under non-strict MySQL these become `0`/empty, so the account is not explicitly enabled. **FIX D22:** write `state = 1`, `login_attempts = 0`, `last_login_ip = 0`, `last_login_dt = 0`, `profile_pic_path = ''`.
- Temporary password = `fn . ln`, MD5-hashed, and `fpc = 1` (no forced change). **FIX D23:** generate a cryptographically random temporary password, set `fpc = 2`, and never store or notify it in plaintext beyond the one-time admin display.
- Inserts a notification with **no subject** and data containing the plaintext password and a malformed URL (`http://` + `$application_root`, which already starts with `http://`). **FIX:** set an explicit subject, use the configured base URL, and do not put the password in `notifications.data`.
- The location dropdown is empty because of a typo: `mysqli_query($connits, $sqli)` instead of `$sql`. **FIX D24.**
- Success: “The user has been added to the database.”

**Target:** require a unique, valid email/username; require location, group, and ACL to exist; optionally send an invitation/reset link. Do not auto-generate a guessable password.

### 24.3 Edit user (`?p=edit_user&id=<id>` → `process_edit_user`)

- Tabs: **User Details** and **Reset Password**.
- Fields: first name, last name, username, location, group, access level. No state, supervisor, or password field here.
- Validation: same name/username minimums and duplicate-username check.
- Update: `fn, ln, mail, location_id, group_id, acl_id`.
- Dead code computes `$password = fn.ln` and `$db_password = md5($password)` but never uses it. Remove it; do not reset the password on edit.
- **FIX:** prevent changing the protected admin (`id = 1`) through this screen unless a separate system-account flow is used; require ACL 9 and CSRF.

### 24.4 Reset password (`?p=reset_user_password&id=<id>`)

- Fields: new password, confirm, and a “force change” checkbox.
- Validation: mismatch → “The password did not match.”; `strlen < 5` → “The password was too shoort.” [sic].
- `force_change` sets `fpc = 2`; otherwise `fpc = 1`.
- Success: “The Users password has been reset.”
- Target: ACL 9, CSRF, minimum 12, no plaintext logging, optional “notify user” action.

### 24.5 Delete user (`?p=process_delete_user&id=<id>`)

- Hard `DELETE FROM users WHERE id = <id>`, no checks.
- Any tickets, logs, supervisor links, or assignments referencing the user become orphaned; INNER JOIN views hide them.
- **FIX D20:** soft-delete (`deleted_at`, `state = 2`), block self-delete and `id = 1`, reassign or null supervisor links, and keep historical tickets visible. Provide an admin “archived users” filter.

### 24.6 User details

- The **User Details** tab edits first name, last name, username, location, group, and access level. It does not show last-login information or the password hash.
- The legacy last-login fields are usually zero because login never writes them. After FIX D04, if last-login information is displayed anywhere, show the real value or “Never.”

### Rebuild decision

PRESERVE the two-tab edit flow, field set, validation messages, and admin-only visibility. FIX explicit account state, secure temporary passwords, the location dropdown typo, hard deletes, and missing CSRF/authorization. Do not add role escalation paths outside the ACL dropdown.

---

## 25. Category, group, and location administration

### 25.1 Categories (`?p=categories`)

- ACL 9. Grid lists all categories **except `id = 1`**, ordered by name.
- Add: name only; duplicate check → “A Category with that name already exists in the database.”; `strlen < 3` → “The Cateogry field was left blank or was too short!” [sic]; success “The Category has been added to the database.”
- Edit: name only; duplicate check excluding self → “A category with that name already exists in the database.”; short → “The category field was left blank or was too short!”; success “The category has been updated in the database.”
- Delete: hard delete, no reference check; success “The category has been deleted.”
- `status` exists (default 1) but the admin UI never shows or changes it; ticket forms only list `status = 1`.
- **FIX D21:** soft-delete/disable instead of hard delete; block deletion of categories referenced by tickets or reassign them; expose an Active/Inactive toggle (the column already exists) while preserving the active-only dropdown behavior.
- **PRESERVE** the exclusion of category 1 and the exact text (or correct the typos with a documented deviation).

### 25.2 Groups (`?p=groups`)

- ACL 9. Grid lists all groups.
- Add: name only; duplicate → “A group with that name already exists in the database.”; short → “The group field was left blank or was too short!”; success “The group has been added to the database.”
- Edit: name; duplicate excluding self → “A group with that name already exists in the database.”; short → “The group field was left blank or was too short!”; success “The group has been updated in the database.”
- Delete: hard delete; success “The group has been deleted.”
- Group 1 is the default and is used as the staff-create fallback and PIS sync value.
- **FIX D21:** soft-delete; block deletion when referenced by `tickets.assigned_gid` or `users.group_id`, or require reassignment.

### 25.3 Locations (`?p=locations`)

- ACL 9. Grid lists all locations.
- Add: **name only**. The legacy insert first tries `INSERT INTO locations (name)`; on error it retries with `address = ''`. `type` defaults to 1. Success “The location has been added to the database.”
- Edit: **name only**. Duplicate → “A location with that name already exists in the database.”; short → “The location field was left blank or was too short!”; success “The location has been updated in the database.”
- Delete: hard delete; success “The location has been deleted.”
- `address` and `type` are never editable in the UI, despite being part of the schema and README. **FIX D68:** add address and type fields (type 1 global, 2 mileage-only) while preserving existing values and defaults. Do not change existing rows.
- **FIX D21:** soft-delete/block deletion when users or tickets reference the location.

### 25.4 `locations2`

Do not expose it. Do not migrate it. Leave it byte-for-byte equivalent to `locations` as a historical duplicate.

### Rebuild decision

PRESERVE all CRUD screens, grid behavior, messages, and the category/group/location ID semantics. FIX hard deletes, missing referential protection, and the invisible location fields. Do not “fix” the spelling in messages unless the UX owner approves; if you do, list it in §37.

---

## 26. Permissions administration (`?p=permissions`)

### Behavior (PRESERVE)

- ACL 9. The screen lists every `mods` row with `type != 3 AND type != 4 AND enabled = 1`, joined to `acls` by the module’s current `acl`, ordered by module name.
- For each module, a dropdown lets the administrator choose an ACL level. The End User level (`id = 1`) is offered only when it is already the module’s current level; otherwise it is excluded from the choices.
- On submit, the process endpoint re-queries a **different** set: `FROM mods, acls WHERE mods.acl = acls.id AND type != '3'`, with no `enabled` filter and no `type != '4'` exclusion, ordered by module name. This is a superset of the rendered rows for type-4 and disabled modules **whose ACL matches a real `acls` row**; modules whose ACL has no `acls` row (for example `acl = 0`) are excluded by the inner join.
- For every row in that superset:
  - Read `$_POST["<name>_acl"]` (missing for the extra rows) and `UPDATE mods SET acl = <posted value> WHERE name = <name>`.
  - If the module’s `parent != 'none'`, also update every child (`WHERE parent = <name>`) to the same ACL, overwriting the child’s individual choice.
- For a module that was rendered, the update is applied **twice**: once as itself and once as a child of its parent. The final value depends on alphabetical processing order: if the parent sorts after the child, the parent’s cascade overwrites the child’s own selection; if the parent sorts first, the child’s own update wins.
- Missing values become empty strings, which MySQL converts to `0` on the `int` column outside strict mode. This silently grants every authenticated user access to those modules (`0 <= user_acl`), rather than denying it. **FIX D69:** never write an ACL for a module that was not rendered, and resolve the parent/child conflict deterministically.
- There is no CSRF, no validation that the posted ACL exists, no audit log, and no protection against locking out the permissions screen itself.

### Target rules (FIX D69)

- ACL 9, CSRF, POST only.
- Validate every posted ACL against the `acls` table.
- Keep the cascade behavior, but do it in a transaction and log before/after values.
- Prevent an administrator from removing their own access to the permissions module unless another ACL 9 user exists; show a clear warning.
- Preserve the module ordering and the “parent cascades to children” behavior.

### Rebuild decision

PRESERVE the data-driven permission matrix and cascade. FIX injection/validation, CSRF, auditability, and lockout protection.

---

## 27. User self-service (`?p=user_options`)

- ACL 1+ (all logged-in users). Left menu with **Change Password**; right pane with the form.
- If `fpc = 2`, show the warning “You are required to change your password before you can continue.” Otherwise show “To change your password complete the form below. When you are ready click the ‘Change Password’ button.”
- Form posts to `?p=process_change_password&id=<session user>` with `new_password` and `confirm_password`.
- Legacy validation: minimum 4 characters (“The new password is too short...”) and mismatch (“The passwords did not match, please try again!”). It does not require the current password.
- Success updates `password` and `fpc = 1`, updates `$_SESSION['fpc'] = 1`, and shows “Your password has been changed!”.
- **FIX:** require the current password, raise the minimum to 12, reject reuse of the current password, rate-limit attempts, and keep the same success text.
- The menu may also expose profile information; do not add editable profile fields unless requested. `profile_pic_path` is storage-only in legacy.

---

## 28. Dashboard and live monitoring

### 28.1 Route behavior (PRESERVE)

- `?p=dashboard` without `v` is redirected by `header.php` to `?p=all_open_tickets`. Staff therefore land on the open-ticket list, not the cards.
- `?p=dashboard&v=tickets` renders the dashboard cards.
- `?p=dashboard&v=tech_status` is linked in the dashboard menu but has no implementation. **FIX D49:** remove the dead link or implement a real technician-status page; do not leave a dead menu item.
- `?p=monitoring` renders the live open-ticket grid.

### 28.2 Dashboard cards (legacy formulas)

| Card | Legacy value | Notes |
|---|---|---|
| Assigned / To You | `COUNT(*) FROM tickets WHERE assigned_uid = session AND state_id = 1` | Correct. |
| Assigned / To Group | `COUNT(*) FROM tickets WHERE assigned_gid = session.group_id AND state_id = 1` | Uses the group/units field; see §3. |
| Closed by You / This Year | `COUNT(*) FROM tickets WHERE resolved_uid = session AND state_id = 2 AND resolved_date = $int_date` | Label says “This Year” but counts **today only**. **FIX D47:** either compute year-to-date or change the label to “Today.” |
| Closed by / You Today | Same query/value as the previous card | Duplicate card. **FIX D48:** show distinct metrics (for example “Closed by You / This Year” YTD and “Closed by You / Today” today). |
| Closed by / Group Today | Loops every user in the session user’s group and assigns `$group_closed_today =+ count` | `=+` is assignment, not addition, so the card shows the **last user’s** count. **FIX D46:** use `+=` or a single aggregate query. |
| Your Average / Resolution Time | Average of `resolved_time - created_time` over closed tickets resolved by the session user | `secsToFormat` renders `h m s`. Preserve the formula for normal rows; note that backdated/zero rows can skew it. |
| Average Close Time / Entire System | Average of `resolved_time - created_time` over all closed tickets | Same caveat. |
| All Open Tickets / Entire System | `COUNT(*) FROM tickets WHERE state_id = 1` | Correct. |

- The page also defines unused `dateDiff`, `timeDiff`, and `timeAdd` helpers; do not port dead code.
- No charts, no filters, no date range.
- The dashboard queries are executed on every page load; target should use aggregate SQL, not row loops.

### 28.3 Live monitoring (`?p=monitoring`)

- Grid of all open tickets, refreshed every **5000 ms**.
- Legacy recreates the entire Kendo grid on each tick (`$('#gridWrapper').empty().html('')` then `kendoGrid(...)`), which loses scroll position, selection, filters, and sort.
- Columns: **Ticket #**, **Date/Time**, **Subject** (uppercased in SQL), **Name** (location name), **Mail** (the end user’s full name, despite the label), **Category**, **Assigned Personnel**. There is no state column.
- The grid uses the same open-ticket getter as **All Open Tickets**, so categories 23 and 24 are excluded; it is not literally every open ticket.
- **FIX D51:** keep the grid instance and call `dataSource.read()`; preserve the 5-second interval (configurable) and the open-ticket filter. Pause polling when the tab is hidden; resume on focus.

### Rebuild decision

PRESERVE the card set, labels, formulas, landing redirect, and 5-second monitoring cadence. FIX the group sum bug, the mislabeled/duplicated cards, the dead Tech Status link, and the full-grid rebuild. Do not add dashboard features that were never requested.

---

## 29. Counter and reporting tool (`counter/`)

### Purpose

Standalone reporting UI used by Help Desk and Administrators to produce per-employee and per-category Excel workbooks for a date range. It is not a ticketing screen and does not appear in the `mods` menu.

### Entry points and visibility

- `counter/index.php` — main report page. Administration → Reports (`?p=reports`) redirects here.
- `counter/category.php` — per-category pivot page. Administration → Reports by Category (`?p=reports_by_category`) redirects here.
- `counter/request.php` — JSON API used by both pages.
- Legacy pages have **no authentication gate**; `request.php` starts the session but never checks `user_id`, and `index.php` does not start a session at all. **FIX D12:** require ACL >= 3 and a valid session on every counter route.

### Main screen (`counter/index.php`)

- Header: **ITS Tickets Report**.
- Filters:
  - **Select employee** dropdown, populated from `getEmployees` (`acl_id IN (3,9)`), plus **Select Multiple** which opens a checkbox modal.
  - **JO** and **REG** quick-select buttons in the modal. There is one hardcoded ID list, `reg = [334, 566, 335, 1555, 336, 2243, 2415]`: **REG** checks exactly those employees; **JO** checks every employee **not** in the list. Treat the list as configuration (§11) and preserve the complement behavior.
  - **Date From** and **Date To** (`type=date`); defaults are today and today + 30 days.
  - **Category** dropdown, populated from `getCategories` (all categories, including category 1).
  - **Categorize Excel** checkbox, checked by default.
  - **Generate Per Ticket** button.
- Results are rendered in a DataTables grid and exported client-side with ExcelJS + FileSaver.
- The main page’s **Enter-to-submit** path is dead: the handler reads `document.querySelector("#type")` and then `el_type.value`, but no `#type` element exists, so submitting the form throws before posting. The visible “Generate Per Ticket” button works. `counter/category.php` is a separate page with a **Generate Report** button that calls `getByCategory`; it is also unauthenticated, ignores its employee/category inputs, and always returns an empty result because the multi-statement helper reads the wrong result-set index. **FIX D54:** implement both reports deterministically (or remove the dead form path), and do not leave a control that throws or silently returns nothing.

### API contract (`counter/request.php`)

The request body is JSON, decoded with `json_decode(file_get_contents("php://input"), true)` and expanded with `extract($data)`. The `method` key selects the operation. All responses are JSON unless a SQL error prints text.

| Method | Inputs | Legacy query semantics | Result shape |
|---|---|---|---|
| `getCurrentUser` | none | `SELECT id FROM users WHERE id = <session user>` | `{data:[{id}]}` |
| `getEmployees` | none | `SELECT id, fn, ln, mail FROM users WHERE acl_id IN (3,9)` | `{data:[...]}` |
| `getCategories` | none | `SELECT * FROM categories` | `{data:[...]}` |
| `getData` | `employee`, `from`, `to`, `cat` | Counts by `resolved_uid` + `category_id`; filters `resolved_uid IN (<employee>) OR <employee> = ''`; `resolved_time BETWEEN unix_timestamp(from) AND unix_timestamp(to)`; `category_id = cat OR cat = '0'`; `approved_by_supervisor != 0`. **No `state_id` filter.** | Object keyed by resolver, then `cat_<id>: count` |
| `getDataTagged` | `employee`, `from`, `to`, `cat` | Counts by `assigned_uid` + `category_id`; same date/category filters; adds `resolved_uid <> assigned_uid`, `state_id = 2`, `approved_by_supervisor != 0` | Object keyed by assignee, then `cat_<id>: count` |
| `getDataX` | `employee`, `from`, `to` | Per resolver: closed-ticket count plus activity-log count (logs excluding `data LIKE 'Ticket Closed.%'`, grouped by user/time/ticket then summed), joined on resolver = log author; returns `closed.count + logs.count AS total_ticket_count` | `{data:[...]}` |
| `getByTickets` | `employee`, `from`, `to`, `cat` | Detailed rows where the employee is creator, assignee, or resolver; `resolved_time BETWEEN unix_timestamp(from) AND unix_timestamp('to 11:59:59')`; optional category; ordered by `created_datetime ASC` | `{data:[...]}` |
| `getByCategory` | `from`, `to` only; posted `employee`/`cat` are ignored | Dynamic pivot: one column per category, `SUM(IF(category_id = c.id, IF(assigned_gid = 0, 1, assigned_gid), 0))`, grouped by assignee. The helper is asked for result set index **5**, but the `EXECUTE` result is index **4** and index 5 is `DEALLOCATE`, so the endpoint always returns `{data:[]}`. | `{data:[...]}` (always empty because of the off-by-one) |

### Per-ticket columns (preserve)

`subject`, `created` (`ln, fn`), `assigned` (`ln, fn`), `resolved` (`ln, fn`), `units_items_persons`, `issue` with tags stripped, `category`, `department` (`locations.name`), `created_datetime`, `resolved_datetime`, `created_month`.

**Units rule (critical):**

- Per-ticket export: if `created_time > UNIX_TIMESTAMP('2025-12-19 15:00:00')`, use `assigned_gid`; otherwise `1`.
- Per-category pivot: use `IF(assigned_gid = 0, 1, assigned_gid)` with **no** cutoff.
- Both rules exist because `assigned_gid` doubles as “No. of units/items/persons.” Make the cutoff configurable (`counter.units_cutoff`, §11) and document which rule each report uses.

### Date semantics

- `from` and `to` are `YYYY-MM-DD` strings.
- `getByTickets` includes the whole `to` day by appending `11:59:59`; the other methods compare against midnight of `to`, so they silently exclude the final day’s tickets.
- `unix_timestamp()` evaluates in the MySQL server timezone, which must remain Asia/Manila.
- **FIX:** normalize every report to inclusive `[from 00:00:00, to 23:59:59]` in Asia/Manila and validate that `from <= to`.

### Excel export

- The browser builds the workbook; there is no server-side export, no job queue, and no row cap.
- **Categorize Excel** checked: one worksheet per distinct category label; there is no summary sheet.
- Unchecked: one worksheet named **Sheet 1**.
- The download is always named `file.xlsx`. Sheet names are lightly sanitized to `[a-zA-Z0-9 ]`; an empty category group can throw when the code reads `dx[0]`.
- **FIX:** move export server-side or stream it, enforce a maximum row count, set a meaningful MIME type and filename, handle empty groups, and sanitize Excel’s forbidden characters (`[]:*?/\\`) plus the 31-character sheet-name limit.

### Legacy defects

- **Unauthenticated data access** to every employee, category, ticket subject, and ticket issue body (D12).
- **SQL injection** through `extract()` and direct interpolation of `employee`, `from`, `to`, and `cat` (D17).
- `getData` counts approved tickets without checking `state_id`, so an open ticket that was somehow approved is counted.
- `getDataTagged` excludes self-resolved tickets (`resolved_uid <> assigned_uid`), which is intentional for “tagged” work but must be documented.
- `getByTickets` filters only on `resolved_time`, so open tickets (resolved_time = 0) never appear even when created in range.
- The main report’s Enter-submit handler throws a JavaScript error because `#type` is missing (D54).
- The per-category report always shows “No data found” because `queryMultipleFromString(..., 5)` reads the `DEALLOCATE` statement instead of the `EXECUTE` result (index 4); it also ignores the employee and category inputs.
- Hardcoded JO/REG IDs and cutoff date (§11).
- `mb_convert_encoding($value, "ISO-8859-1", "UTF-8")` corrupts non-Latin characters; target must return UTF-8.
- SQL errors are printed into the JSON response, exposing schema details (D67).
- No CSRF concern for reads, but the endpoint accepts POST bodies from any origin; add same-origin checks if cookies are used.

### Rebuild decision

Reimplement the report as an authenticated module (ACL >= 3), with parameterized queries, validated dates, explicit inclusion rules, and server-generated XLSX/CSV. Preserve the per-ticket columns, the two units rules, the category pivot, and the JO/REG quick-select behavior as configuration. Fix the dead pivot control, the final-day exclusion, encoding, and unauthenticated access. Do not reproduce the `extract()` pattern.

---

## 30. Root JSON report endpoint (`reports.php`)

### Purpose

A small machine-readable export used by external reporting consumers. It is not linked from the menu but is reachable at the application root.

### Request

```text
GET /its/reports.php?date=YYYY-MM[&include-end-user=true]
```

- `date` is required and is matched against `FROM_UNIXTIME(tickets.created_time, '%Y-%m')`.
- `include-end-user=true` adds `LEFT JOIN users ON tickets.eu_uid = users.id` and a `CONCAT(users.fn,' ',users.ln) AS end_user` column.
- Response: `application/json`, an array of row objects.

### Query (preserve semantics)

```sql
SELECT tickets.id,
       categories.category,
       tickets.subject,
       tickets.issue,
       FROM_UNIXTIME(tickets.created_time, '%Y-%m-%d %H:%i:%s') AS created,
       FROM_UNIXTIME(tickets.resolved_time, '%Y-%m-%d %H:%i:%s') AS closed,
       SEC_TO_TIME(ABS(tickets.resolved_time - tickets.created_time)) AS `duration (minutes)`,
       CASE WHEN assigned_gid = 0 THEN 1 ELSE assigned_gid END AS no_of_units_items_persons
       [, CONCAT(users.fn,' ',users.ln) AS end_user]
FROM tickets
INNER JOIN categories ON categories.id = tickets.category_id
[LEFT JOIN users ON tickets.eu_uid = users.id]
WHERE FROM_UNIXTIME(tickets.created_time, '%Y-%m') = '<date>'
  AND (tickets.state_id = 1 OR tickets.approved_by_supervisor != 0)
```

### Legacy defects

- **No authentication** (D12): anyone who can reach the URL can download every ticket’s subject and full issue body for a month.
- **SQL injection** in `date` (D17).
- The column is labeled `duration (minutes)` but `SEC_TO_TIME` returns `HH:MM:SS`; consumers may parse it as minutes incorrectly (D67).
- `ABS()` hides negative durations caused by bad timestamps.
- No pagination, row cap, or content negotiation.
- `INNER JOIN categories` drops tickets whose category was deleted (D56).
- The endpoint exposes `issue` HTML without sanitization; JSON consumers may render it unsafely.

### Rebuild decision

Keep the endpoint only if an external consumer depends on it. If kept: require ACL >= 3 (or a scoped API token), parameterize, validate `date` as `YYYY-MM`, cap the range, rename the duration field or return seconds plus a formatted string, use `LEFT JOIN`, and document the schema in Appendix D. If no consumer exists, remove it and record the removal in §37. Never leave it unauthenticated.

---

## 31. Email-to-ticket ingestion (cron)

### Purpose

Poll an IMAP inbox and turn each incoming message into a ticket for the sender, with attachments stored on disk.

### Entry point

- `config/cron/process_inbox.php` — invoked by cron. The file starts with `<?` rather than `<?php`; if `short_open_tag` is disabled, PHP serves the source as text and nothing runs.
- It includes `../../header.php` (the web bootstrap), then `class.emailtodb.php`, constructs `EMAIL_TO_DB`, and calls:

```php
$edb->connect('SERVER', ':PORT', 'USER', 'password');
$edb->do_action();
```

- The host, port, user, and password are **placeholders** in the repository. The job is not runnable until configured (D19).
- Because it includes `header.php`, it inherits the web session bootstrap, HTML head, and redirects. The target MUST provide a separate CLI entry point with no web session or HTML output (D58).

### Processing flow

1. `do_action()` checks `num_message() >= 1`; if there are no messages, it does nothing.
2. It selects **one** message: `msgid = 1` when no `msgid` query parameter, otherwise `$_GET['msgid'] + 1`. The cron therefore processes **one message per run**.
3. `email_get()` parses headers and MIME parts into `partsarray`.
4. `db_add_message($email)`:
   - Looks up `users` by exact `mail = '<From address>'`.
   - Uses the matched `users.id` for both `eu_uid` and `created_uid`, and the matched `users.location_id`.
   - Inserts a ticket with `subject = <email subject>`, `state_id = 1`, `category_id = 1`, `created_date = midnight today`, `created_time = today at the current clock time`.
   - The insert omits `issue`, `preview`, `place`, `contact`, `assigned_uid`, `assigned_gid`, and approval fields, so they take column defaults (`0`/empty).
   - Inserts a notification: `user_id = eu_uid`, `subject = 'Ticket Created - <id>'`, `data = 'This is a confirmation email letting you know that your ticket was successfully created.<br /><br />Ticket ID: <id>'`, `dt = midnight today`, `state = 1`.
5. For each MIME part:
   - HTML part → `UPDATE tickets SET issue = '<html>'` and `preview = '<stripped text minus last 50 chars>'`.
   - Plain part → same update with the plain text.
   - Attachment/image → sanitize the filename with `preg_replace('/[^a-z0-9_\-\.]/i', '_', ...)`, save as `<ticket_id><filename>` under `mods/tickets/attachments/YYYY_MM/`, and insert into `attachements` with the relative path `YYYY_MM/<ticket_id><sanitized name>` in `file_name` and the sanitized original name in `file_name_org`.
6. Mark the message `\Seen`, delete it, and expunge the mailbox.
7. If `mode == 'html'`, print a meta refresh back to `process_inbox.php?msgid=0`; otherwise continue.

### Sender matching and orphan tickets

- Matching is exact equality on the `From` address. Because the column collation is `latin1_swedish_ci`, the comparison is case-insensitive, but a `+tag`, a display-name form, or a different address will not match.
- If no user matches, `$result` is `null`; `eu_uid` and `location_id` become `0`/empty. The ticket is still inserted, creating an orphan ticket that most legacy lists hide because of `INNER JOIN users`/`locations` (D57).
- **FIX:** never create a ticket with an unknown end user. Either reject the message into a quarantine mailbox, create a ticket under a configured “Unknown sender” account, or create it with `eu_uid = 0` but teach every list/detail view to render “Unknown sender” and “Unspecified location.”

### Subject, issue, and preview

- The email subject is used verbatim. Empty subjects are allowed.
- `issue` is the last HTML or plain part processed; multipart messages can overwrite earlier parts.
- `preview` is derived by stripping tags, decoding entities, escaping, then removing the **last 50 characters** — a bug that truncates the end of every preview (D31).
- The target MUST sanitize HTML before storing, derive a deterministic plain-text preview (for example first 200 characters), and never remove the tail.

### Attachments

- The on-disk path is `mods/tickets/attachments/YYYY_MM/<ticket_id><sanitized name>`.
- The `attachements.file_name` column is `int(11)`, so MySQL truncates the path to its leading numeric prefix (usually the year). The real path is effectively lost (D73). Preserve the column, add `storage_path` (§9.7), and backfill where possible.
- `mkdir(..., 0777)` is world-writable; the target must use restrictive permissions and a directory outside the web root.
- There is no extension/MIME/size validation and no antivirus scan. **FIX D16:** allowlist document/image types, cap size, verify magic bytes, generate a random storage name, and scan if the environment provides a scanner.

### Failure behavior

- The message is deleted and expunged even if the ticket insert, attachment save, or notification insert fails.
- `emailtodb_log` and `emailtodb_email` inserts target tables that are **absent** from `config/sts.sql`; they fail silently (D75).
- There is no retry, dead-letter queue, or idempotency key. A failed run loses the email; a double run can create duplicates if deletion fails.
- SQL errors are not checked consistently; some `mysqli_query` results are ignored.

### Target design (FIX D57, D58, D73, D75)

- Run from CLI only, under a dedicated OS user, with an advisory lock so two cron instances cannot process the same mailbox.
- Use a real IMAP library with TLS, configurable mailbox/folder, and a UID-based cursor instead of `msgid`.
- Process all messages in a batch with a per-message transaction and an `email_ingest_log` row (`message_id`, sender, subject, ticket_id, status, error, timestamps).
- Use `Message-ID` as the idempotency key; add `tickets.email_message_id` (§9.7) and a unique index.
- Quarantine unknown senders; never create orphan tickets silently.
- Store attachments outside the web root, record `storage_path`, MIME, size, hash, and uploader; strip dangerous filenames; serve downloads through an authorized controller.
- Mark the message seen/deleted only after the ticket and attachments are committed; otherwise leave it for retry.
- Do not include `header.php`; log to the application log, not HTML.
- Preserve the notification text and the category-1 default for known senders.

### Rebuild decision

Rebuild ingestion as a tested CLI service with the same user-visible outcome (a category-1 open ticket for the sender, a confirmation notification, stored attachments). Fix the one-message-per-run limit, orphan tickets, destructive failure handling, preview truncation, missing log tables, and unsafe file handling.

---

## 32. Notifications

### Schema (preserve)

`notifications`: `id`, `user_id`, `subject` (`varchar(255) NOT NULL`), `data` (`text NOT NULL`), `dt` (`int` epoch), `state` (`1` = new, `2` = sent).

### Legacy writers

1. **Add user** (`process_add_user.php`) inserts `(user_id, data, dt, state)` and **omits `subject`**. `data` contains a greeting and the **plaintext temporary password**, plus a malformed URL (`http://` concatenated with `$application_root`, which already starts with `http://`).
2. **Email ingestion** inserts a confirmation with subject `Ticket Created - <id>`, HTML data, `dt` = midnight today, `state = 1`.

### Legacy readers

There are **none**. No screen, cron, or mailer consumes `notifications`; `state` is never advanced to `2`. The table is effectively an outbox that nobody drains (D74).

### Target decision

Choose one and document it:

- **Option A (recommended): implement an in-app notification center.** Add a bell in the top menu, a list scoped to the session user, mark-as-read, and an unread count. Keep `state` semantics (`1` unread/new, `2` read/sent). Do not use it for password delivery.
- **Option B: turn it into a real email outbox.** A worker sends rows with `state = 1`, sets `state = 2`, records attempts and errors, and never stores plaintext secrets.
- **Option C: drop the table from the UI but keep writing rows for compatibility.** Not recommended; it leaves a table full of plaintext passwords.

Regardless of option:

- Add an index on `(user_id, state, dt)`.
- Add `subject` to the add-user notification or stop writing that notification.
- Never put a password, hash, or reset token in `data`.
- Preserve existing rows; do not delete them. If they contain plaintext passwords, purge/redact them during migration and record it as a security remediation.
- Add a delivery log (`notification_delivery`) if email is implemented.

### Edge cases

- `user_id = 0` rows from unknown email senders are undeliverable; render or purge them.
- `dt` is a midnight epoch for both writers; sort by `id` for stable ordering.
- The `subject` column has no default; legacy inserts without it rely on non-strict mode. Target must always write a subject.
- There is no unique constraint, so a retry can duplicate a notification; add an idempotency key for email confirmations.

### Rebuild decision

PRESERVE the table and the confirmation text. FIX the missing subject, plaintext password leak, malformed URL, and missing consumer by choosing Option A or B. If the data owner does not want notifications in the target, stop writing them and document the removal.

---

## 33. Attachments

### Purpose

Store files received by the email-to-ticket cron. The legacy application has **no upload control, no attachment list, no download route, and no deletion flow**; only the cron writes rows and files.

### Storage contract

- Directory: `mods/tickets/attachments/YYYY_MM/`.
- Filename on disk: `<ticket_id><sanitized original filename>`.
- `attachements.file_name_org`: sanitized original filename.
- `attachements.file_name`: intended to hold the path, but the column is `int(11)`, so MySQL stores only the leading numeric prefix. This is a legacy defect, not a contract to reproduce for new rows.
- `ticket_id`: owning ticket.

### Target requirements (FIX D73)

- Keep the table name `attachements` and the existing columns unchanged.
- Add `storage_path varchar(500) NULL`, `mime_type varchar(150) NULL`, `size_bytes BIGINT NULL`, `sha256 CHAR(64) NULL`, `uploaded_by INT NULL`, `created_at INT NULL` (§9.7).
- Store new files outside the web root with a random, non-guessable name; keep the original name only as metadata.
- Serve downloads through an authorized controller that checks ticket visibility (§6.3), sets `Content-Disposition: attachment`, and sends `X-Content-Type-Options: nosniff`.
- Do not guess MIME type from the extension; verify magic bytes and allowlist: PDF, PNG, JPEG, GIF, WEBP, TXT, CSV, DOC/DOCX, XLS/XLSX, PPT/PPTX, ZIP (configurable). Reject executables, scripts, HTML, SVG, and macro-enabled Office files unless explicitly allowed.
- Enforce a per-file size limit (for example 25 MB) and a per-ticket total limit; make both configurable.
- Compute SHA-256, optionally deduplicate, and scan with antivirus if available.
- Backfill `storage_path` by matching existing rows to files under the legacy directory. If the numeric `file_name` lost the path, reconstruct candidates from `file_name_org` and the ticket ID; mark unmatched rows as `missing` rather than deleting them.
- If uploads are enabled, add CSRF, rate limiting, and a quarantine step. Uploads are **optional and disabled by default** because the legacy UI never had them.
- Show a read-only attachment count/list on the ticket detail when rows exist; do not break the layout when there are none.
- Never expose the storage path, internal filesystem path, or direct file URL in HTML or JSON.

### Legacy edge cases

- A message with multiple attachments writes one row per file; duplicate sanitized names overwrite on disk (`<ticket_id><name>`), leaving two DB rows pointing at one file.
- Messages with inline images create attachment rows for signature logos and tracking pixels.
- There is no cleanup when a ticket is deleted (tickets are never hard-deleted by the app, but a manual DB delete leaves orphans).
- Non-ASCII filenames are sanitized to underscores and may collide.
- Attachments from unknown senders are stored even when the ticket is an orphan.

### Rebuild decision

PRESERVE the table, the year-month folder convention for historical files, and the ticket association. FIX the integer path column, unsafe storage, missing authorization, missing validation, and absent UI. Do not rename the table or the legacy columns.

---

## 34. UI, layout, and interaction conventions

### Application shell (PRESERVE the information architecture)

- Single-page-feel PHP shell: one HTML document with a fixed top menu, a left menu for the current area, and a content region.
- Browser title and top-menu application name come from `application_settings.title` (default `PHP Ticket System`).
- Top menu:
  - application name on the left;
  - module-driven navigation (`mods` rows with `type = 1`, `parent = 'none'`, `acl <= user_acl`, `enabled = 1`, excluding `end_user`), ordered by `weight`;
  - a dropdown when a top-level item has visible children, otherwise a direct link;
  - right side: `"<ln>, <fn>"`, **Options**, **Log Out**.
- Left menu: the current area’s child modules, same ACL/enabled/weight rules. Administration, Users, Tickets, Dashboard, and User Options each have their own menu include.
- Content widths are fixed around 960 px in legacy (`section_960`, `section_300_l`, `section_600_r`, `section_960_b`). The target MUST keep the same visual hierarchy but SHOULD be responsive; do not copy fixed pixel widths.

### Ticket list layout (PRESERVE)

- Left column: Kendo grid, roughly 300 px, page size 40, virtual scrolling, height 700, filterable, sortable, single-select, no pager.
- Right column: a tab strip. Before a selection it shows an instructional empty state (“Double click a ticket from the list to the left to open it here.”). After selection it loads the ticket detail.
- Bottom: the Tickets submenu (`Create Ticket` plus visible children).
- Row template: creation date/time, the end user’s full name in bold (the JSON field is called `mail`), category, `#<id>`, truncated subject (80 characters), the location name (JSON field `name`), state, and “Attended By: <assignee or none>”.
- Double-click opens the detail. Single-click only selects.
- A `#ticket=<id>` fragment opens that ticket automatically.
- Closing the detail clears the selection/fragment and refreshes the grid.

### Ticket detail layout (PRESERVE)

- Header: creator name, creation date (`m/d/Y`) and time (`H:i:s`), `#<id>` badge, subject, issue HTML.
- Metadata cards: department/location/place and Output Messenger/phone/contact.
- “Issue & Discussion History”: timeline of `tickets_log` rows, oldest first, each with author, date/time, and log HTML.
- Staff-only edit form and **Close Ticket** button according to §15–§16.
- Approval views add Approve/Disapprove (or Undo) buttons and a remarks box in the right pane.

### Modals and overlays

- Create Ticket and several admin editors open in a KendoWindow (about 650×650, modal, draggable, not resizable) that loads a module URL.
- Admin edit screens use modal windows with two tabs where noted.
- Implementation notes if the target chooses a similar widget library (from the legacy codebase’s known issues):
  - store widget references in variables, not only in the library’s data cache;
  - remove modal overlays explicitly after close;
  - defer grid refresh by about 300 ms after the close animation;
  - avoid stacking overlays when a modal opens another modal.
- The target should use one consistent dialog component. Do not reproduce overlay leaks or “undefined widget” bugs.

### Feedback and confirmation

- Server responses use three CSS classes:
  - `.error` — red error box, messages joined with `<br />`;
  - `.success` — green success box;
  - `.info` — neutral informational box.
- Destructive actions use browser `confirm()` or a modal:
  - close ticket: “The ticket will be closed. Are you sure?”;
  - batch approve: “Approve <n> selected ticket(s)?”;
  - delete admin records: confirm before posting.
- Validation errors are returned as HTML fragments and injected into the modal or `#server_message`; they are not field-level annotations. The target SHOULD add field-level errors while preserving the summary text.
- The counter uses Toastify for notifications; the main app does not.

### Status and color semantics (PRESERVE)

- Approval: `Approved` green, `Disapproved` red, `Pending` orange.
- Old subordinate tickets: 40% opacity when “Hide old tickets” is off.
- Buttons: primary neutral, danger red for close/delete, success green for approve.
- Do not rely on color alone; add text labels (accessibility).

### Dates, numbers, and text

- Lists: `Nov 4, 3:05 PM` (`%b %e, %l:%i %p`).
- Detail: `11/04/2026` and `15:05:00`.
- Reports: `2026-11-04 15:05:00`.
- Subject truncation: 80 characters in list rows; preserve the full subject in the detail and tooltips.
- Names: `"<ln>, <fn>"` for people lists and the top menu; `"<fn> <ln>"` in assignment logs and “Attended By”.
- Text is English. Preserve the legacy labels and capitalization, including inconsistent ones, unless corrected with a documented deviation.

### Accessibility and responsiveness (target requirements)

- WCAG 2.1 AA: keyboard-operable grids/dialogs, visible focus, labels tied to inputs, error announcements (`role="alert"`), sufficient contrast, no color-only status.
- The fixed 960 px layout must not force horizontal scrolling on a 1366×768 laptop. The grid may scroll horizontally, but primary actions must remain reachable.
- Support current Chrome, Edge, and Firefox. Do not depend on Internet Explorer, Flash, or NPAPI.
- Print: a ticket detail and a search result set must be printable without the navigation chrome. Legacy “Print Results” does not print (D59).

### Legacy UI defects to fix

- Broken “Print Results” and “Clear Results” (D59).
- Search grid titled “No. of units/items/persons” (D60).
- Row template puts the requester and location in ambiguous, unlabeled slots (D61).
- Dead “Tech Status” menu item (D49).
- Kendo grid fully rebuilt every 5 seconds on monitoring (D51).
- Modals leave overlays behind; widget references become stale.
- No loading indicators on several AJAX calls; a failed request looks like an empty grid.
- No consistent 404/error page for missing tickets; the endpoint may render a blank fragment.

### Rebuild decision

PRESERVE the shell, menu behavior, split list/detail layout, row content, timeline order, status colors, labels, confirmations, and message classes. FIX the broken controls, label swaps, accessibility, responsiveness, overlay leaks, and empty/error states. The target does not need to look pixel-identical, but a user must recognize every screen and find every action in the same place.

---

## 35. Validation, message, and log catalogue

### Rendering contract

- Validation errors are collected in `$err` and rendered as `<div class="error">message<br />message</div>`.
- Successes are collected in `$suc` and rendered as `<div class="success">...</div>`.
- Informational outcomes use `<div class="info">...</div>`.
- JSON endpoints return `{"success":<int>,"message":"..."}` or `{"count":<int>}`.
- The target MUST return the same visible text for the same outcome unless a row below is marked **correct**. Corrected text is a documented deviation.

### Authentication and password messages

| Context | Exact legacy text | Notes |
|---|---|---|
| Login failure | none (silent return) | FIX D05: generic “Invalid username or password.” |
| Disabled account | none (login succeeds) | FIX D02: generic refusal. |
| Forced change warning | “You are required to change your password before you can continue.” | In `user_options.php`. |
| Self password too short | “The new password is too short...” | Minimum 4; target 12 (D95). |
| Self password mismatch | “The passwords did not match, please try again!” | Preserve. |
| Self password success | “Your password has been changed!” | Preserve. |
| Admin reset mismatch | “The password did not match.” | Preserve or align with self text. |
| Admin reset too short | “The password was too shoort.” | Typo; correct to “short” with a documented deviation. |
| Admin reset success | “The Users password has been reset.” | Awkward; correct to “The user’s password has been reset.” with a documented deviation. |

### Staff ticket creation

| Condition | Exact legacy text |
|---|---|
| Debounce | “Too many queries.” |
| Subject empty/short (actually tests `issue`) | “The subject was too short or left blank” |
| Issue empty/short | “The issue was too short or left blank” |
| Location missing | “A location is required” |
| Category missing | “A category is required” |
| End user missing | “A end user is required” |
| Success | “The ticket was created. <epoch>” |

Target: keep the texts, add the missing periods only if the UX owner approves, fix the subject/issue checks (D26), and remove the epoch (D28).

### End-user ticket creation

| Condition | Exact legacy text |
|---|---|
| Place missing | “Please indicate your location/office/department/section.” |
| Contact missing | “Please indicate your Output Messenger/Telephone number.” |
| Subject short | “The subject was too short.” |
| Issue short | “The issue was too short.” |
| Duplicate within debounce | “Ticket is already created.” |
| Success | “Ticket #<id> has been created.” |
| Open-ticket count | “There are currently <n> open tickets. We will attend your concern as soon as we can.” |
| Follow-up | “Thank you for your patience.” |

### Ticket edit and close

| Condition | Exact legacy text |
|---|---|
| No assignee | “Please assign a user.” |
| Duplicate note within debounce | “Too many queries.” |
| Closed/reviewed | “Ticket already closed or reviewed.” |
| Location missing | “A location is required” |
| Category missing or 1 | “A category is required” |
| End user missing | “A end user is required” |
| Close success | “The ticket has been closed.” |
| Close repeat | “The ticket is already closed.” |
| Close without submit | “invalid request” |
| Survey prompt | “How well did you do? Ask the user to answer a short survey!” |

### Approval

| Condition | Exact legacy text |
|---|---|
| Ticket not found | “Ticket not found.” |
| Already approved | “Ticket already approved.” |
| Already disapproved | “Ticket already disapproved.” |
| Not approved (undo) | “Ticket is not currently approved.” |
| Approve success | “Ticket approved.” |
| Disapprove success | “Ticket disapproved.” |
| Undo success | “Approval undone.” |
| Batch empty | “No tickets selected.” |
| Batch disapprove no remarks | “Remarks are required for disapproval.” |
| Batch approve result | “<n> ticket(s) approved.” |
| Batch disapprove result | “<n> ticket(s) disapproved.” |
| Batch unauthorized | “Unauthorized” (JSON) |

### Admin CRUD

| Context | Exact legacy text |
|---|---|
| Category add duplicate | “A Category with that name already exists in the database.” |
| Category add short | “The Cateogry field was left blank or was too short!” |
| Category add success | “The Category has been added to the database.” |
| Category edit duplicate | “A category with that name already exists in the database.” |
| Category edit short | “The category field was left blank or was too short!” |
| Category edit success | “The category has been updated in the database.” |
| Category delete success | “The category has been deleted.” |
| Group add duplicate | “A group with that name already exists in the database.” |
| Group short | “The group field was left blank or was too short!” |
| Group add success | “The group has been added to the database.” |
| Group edit success | “The group has been updated in the database.” |
| Group delete success | “The group has been deleted.” |
| Location add duplicate | “A location with that name already exists in the database.” |
| Location short | “The location field was left blank or was too short!” |
| Location add success | “The location has been added to the database.” |
| Location edit success | “The location has been updated in the database.” |
| Location delete success | “The location has been deleted.” |
| User name short | “The first name used was too short.” / “The last name used was too short.” |
| Username short | “The username used was too short.” |
| Username duplicate | “A user with that username already exists.” |
| User add success | “The user has been added to the database.” |
| User edit success | “The user has been updated in the database.” |
| Supervisor missing | “Please select a supervisor.” |
| Supervisor self | “A user cannot be their own supervisor.” |
| Supervisor invalid | “Selected supervisor does not exist or is inactive.” |
| Supervisor set success | “Supervisor has been assigned.” |
| Supervisor removed | “Supervisor has been removed.” |

### Log catalogue (write exactly, preserve order)

| Action | Exact `tickets_log.data` | When |
|---|---|---|
| Staff create, group > 0 | `No. of units/items/persons: <group name>` | Immediately after insert. |
| Staff create, assignee > 0 | `Assigned to user: <fn ln>` | Immediately after insert. |
| Edit note | the sanitized note HTML | When `update_ticket` length ≥ 1. |
| End user changed | `End User Changed: <fn ln>.` | When `eu_uid` changes. |
| Location changed | `Location changed to: <location name>.` | When `location_id` changes. |
| Category changed | `Category changed to: <category name>.` | When `category_id` changes. |
| Units/group changed | `No of units/items/persons assigned was changed from <old group name> to <new group name>` | When `assigned_gid` changes. No trailing period. |
| Assignee changed | `User assignment has changed to <strong><fn ln></strong>` | When `assigned_uid` changes. |
| Close | `Ticket Closed. <br> <a href='<survey_url>' target='_blank'><survey_url></a></p>` | Once per closer/ticket. |
| Approve | `Supervisor approved ticket.` + ` Remarks: <remarks>` if present | On 0/2 → 1. |
| Disapprove | `Supervisor disapproved ticket.` + ` Remarks: <remarks>` | On 0/1 → 2. |
| Undo | `Supervisor undid approval.` | On 1 → 0. |

The target MUST keep these strings for audit-history compatibility. Sanitize the dynamic values, but do not change the static prefixes. If the malformed `</p>` in the close log is fixed, record it in §37.

### Typos and inconsistencies

Preserve or correct with a documented deviation:

- “Cateogry” (category add).
- “shoort” (admin password reset).
- “The Users password...” (admin reset).
- “A end user” (create/edit).
- “No of units/items/persons...” (edit log, missing period and “No.”).
- “Supervisor by Supervisors” was renamed by migration to “Tickets by Supervisor.”
- Category add messages capitalize “Category”; edit messages do not.
- Date formats differ between lists, detail, and reports.

### Target validation rules (summary)

- Trim all text; reject whitespace-only values.
- Validate lengths and HTML payload size.
- Validate every foreign ID against an active row.
- Validate dates as real dates and time ranges.
- Reject unknown enum values.
- Return field-level errors plus the legacy summary.
- Never echo raw SQL errors (D67).
- Rate-limit and CSRF-protect every mutation.

---

## 36. Data integrity, normalization, and legacy-row handling

### Principles

- Historical rows are evidence. Do not rewrite ticket content, timestamps, approvals, or logs to make reports look better.
- Normalize only where a value is impossible to interpret or blocks the target from functioning, and record every change in a migration with a reversible backup.
- Additive schema only (§2). New constraints apply to new writes; existing rows are validated by audit queries, not by destructive updates.
- Every normalization decision is approved by the data owner and listed in §41 if ambiguous.

### Preflight audit (run before migration)

```sql
-- Counts by state and ACL
SELECT state, acl_id, COUNT(*) FROM users GROUP BY state, acl_id;
SELECT state_id, COUNT(*) FROM tickets GROUP BY state_id;
SELECT approved_by_supervisor, COUNT(*) FROM tickets GROUP BY approved_by_supervisor;

-- Users with no explicit enabled state
SELECT COUNT(*) FROM users WHERE state NOT IN (1,2) OR state IS NULL;

-- Duplicate usernames (login ambiguity)
SELECT mail, COUNT(*) c FROM users GROUP BY mail HAVING c > 1;

-- Users pointing at missing locations/groups/supervisors
SELECT u.id, u.mail FROM users u
LEFT JOIN locations l ON l.id = u.location_id
WHERE u.location_id <> 0 AND l.id IS NULL;
SELECT u.id, u.mail FROM users u
LEFT JOIN `groups` g ON g.id = u.group_id
WHERE u.group_id <> 0 AND g.id IS NULL;
SELECT u.id, u.supervisor_id FROM users u
LEFT JOIN users s ON s.id = u.supervisor_id
WHERE u.supervisor_id IS NOT NULL AND u.supervisor_id > 0 AND s.id IS NULL;

-- Tickets with broken references (these disappear from INNER JOIN views)
SELECT t.id, t.eu_uid, t.created_uid, t.location_id, t.category_id
FROM tickets t
LEFT JOIN users eu ON eu.id = t.eu_uid
LEFT JOIN users cu ON cu.id = t.created_uid
LEFT JOIN locations l ON l.id = t.location_id
LEFT JOIN categories c ON c.id = t.category_id
WHERE eu.id IS NULL OR cu.id IS NULL OR l.id IS NULL OR c.id IS NULL;

-- Closed tickets with missing resolver/timestamps
SELECT COUNT(*) FROM tickets
WHERE state_id = 2 AND (resolved_uid = 0 OR resolved_date = 0 OR resolved_time = 0);

-- Impossible time order / negative durations
SELECT COUNT(*) FROM tickets WHERE resolved_time > 0 AND resolved_time < created_time;

-- Orphan logs and attachments
SELECT COUNT(*) FROM tickets_log tl LEFT JOIN tickets t ON t.id = tl.ticket_id WHERE t.id IS NULL;
SELECT COUNT(*) FROM attachements a LEFT JOIN tickets t ON t.id = a.ticket_id WHERE t.id IS NULL;

-- Duplicate/orphan notifications
SELECT COUNT(*) FROM notifications n LEFT JOIN users u ON u.id = n.user_id WHERE u.id IS NULL;

-- Attachment rows whose numeric file_name cannot identify a file
SELECT COUNT(*) FROM attachements WHERE file_name = 0 OR file_name IS NULL;

-- MD5 passwords still in use
SELECT COUNT(*) FROM users WHERE CHAR_LENGTH(password) = 32 AND password REGEXP '^[0-9a-f]{32}$';
```

### Normalization decisions

| Case | Decision | Rationale |
|---|---|---|
| `users.state` is `0`/`NULL` | Set to `1` (enabled) for rows created by the legacy add-user flow, except explicitly disabled rows (`2`). Log the change. | Legacy inserts omitted `state`, so `0` means “never set,” not “disabled.” D71. |
| `users.state = 2` | Keep disabled. | Explicit intent. |
| `users.acl_id` missing/invalid | Quarantine the account and require an administrator to set a valid ACL. Do not default to 9 or 3. | Prevents privilege escalation. |
| `users.location_id = 0` | Keep, but block login until configured **or** use the PIS sync path (§7.3). Do not silently assign IHOMS. | Location 0 hides tickets and breaks dropdowns. |
| `users.group_id = 0` | Keep for end users; for staff, require a valid group before ticket assignment. | Group 0 is used as “none” in user dropdowns. |
| Duplicate `users.mail` | Do not auto-merge. List them for the data owner. Until resolved, login must reject ambiguous matches. | D72. |
| Password is 32 hex chars | Treat as MD5; verify once, then rehash to Argon2id. | D01. |
| Password is empty | Force a reset; do not allow blank login. | Security. |
| `tickets.approved_by_supervisor` not in 0/1/2 | Map to `0` (pending) and flag the row. | Preserves reviewability. |
| `tickets.state_id` not 1/2 | Quarantine/report; do not guess. | Unknown state would be invisible. |
| Closed ticket with `resolved_uid = 0` | Keep; display “Unknown” and exclude from per-user closed counts. Offer a one-time admin repair only with owner approval. | Historical import defect. |
| Closed ticket with `resolved_time = 0` | Keep; display “Unknown” and exclude from duration averages. | Avoid fake 1970 dates. |
| `resolved_time < created_time` | Keep; flag in audit; do not use `ABS()` in new reports. | Data quality. |
| Ticket references missing user/location/category | Keep; display “Archived/Unknown” via LEFT JOIN. Do not delete the ticket. | D56. |
| `tickets.assigned_gid` | Preserve exactly. Do not convert to a group ID or a count. | Dual meaning (D53). |
| `tickets.resolution` | Preserve; the legacy app never wrote it. Do not repurpose. | §9.2. |
| `attachements.file_name` | Preserve; add `storage_path` for new rows. Backfill by scanning; mark unmatched as missing. | D73. |
| `notifications` with plaintext passwords | Redact `data` during migration, keep the row for audit count, or delete the row with owner approval. Never display it. | Security. |
| `mods` rows not in the repository | Import the production export verbatim; do not prune “unknown” modules. | §2.5. |
| `locations2` | Preserve as-is. Do not sync it automatically. | §2.1. |

### Referential integrity

- The legacy schema has **no foreign keys** (D66). The target MUST enforce references in application code and transactions.
- Add real foreign keys only to new tables. For existing tables, add indexes and validation; do not add cascading deletes.
- Soft-delete users, categories, groups, and locations (`deleted_at`, plus `state = 2` for users). Never hard-delete a row referenced by a ticket or log.
- When an administrator tries to delete a referenced row, show the reference counts and require deactivation instead.
- Reassign or null `supervisor_id` when a supervisor is deactivated; keep the historical relationship in an audit table if needed.
- Tickets are never hard-deleted by the legacy app. The target MUST NOT add a hard-delete ticket action without an explicit retention policy.

### Concurrency and consistency

- Wrap multi-row writes in a transaction: ticket + logs; approval + log; close + log; batch approval + logs; user + notification; permissions cascade.
- Use `SELECT ... FOR UPDATE` for ticket edit/close/approval.
- Add an `updated_at`/version column to `tickets` for optimistic concurrency if the target stack does not provide row locking.
- Make close, approve, disapprove, undo, and batch actions idempotent. A retry must not duplicate logs or overwrite timestamps.
- Use a per-form idempotency key (server-side nonce) for create and close.
- Do not rely on MyISAM table locks; convert to InnoDB after backup if the target requires transactions (§2.2).

### Audit requirements

- New append-only tables: `auth_audit` (§9.7) and `admin_audit` (actor, action, entity, entity_id, before JSON, after JSON, ip, created_at).
- Record login success/failure/lockout, password changes/resets, permission changes, supervisor changes, user/category/group/location create/update/deactivate, ticket close, approval/disapproval/undo, batch actions, email ingestion, attachment upload/download of restricted files.
- Do not record passwords, hashes, tokens, full email bodies, or attachment contents.
- Retain audit rows according to the data owner’s policy; default at least 1 year.

### Character encoding and collation

- Legacy tables mix `utf8_general_ci` (table default) with `latin1_swedish_ci` columns. The target connection MUST use UTF-8, and the migration MUST verify round-trip encoding for names, subjects, issues, and logs.
- Do not change existing column charsets during the rebuild unless a backup and full regression test are complete.
- New text columns use `utf8mb4`. Existing `latin1` columns may reject emoji; the target must validate or convert at the application boundary, not silently truncate.
- Login matching is case-insensitive today; preserve it explicitly (normalize the lookup or use a case-insensitive collation) rather than relying on server defaults.

---

## 37. Defects register and required deviations

Every row is a deliberate rebuild decision. “FIX” means the target MUST deviate from legacy. “DECIDE” means the target MUST ask the data owner before changing behavior. “PRESERVE” means reproduce the behavior and document any cosmetic correction.

Severity reflects security and data-integrity impact, not how often the defect is hit.

| ID | Severity | Area | Legacy behavior | Target requirement | Decision |
|---|---|---|---|---|---|
| D01 | Critical | Auth | Passwords stored and compared as unsalted MD5. | Verify legacy MD5 once, then rehash with Argon2id/bcrypt; never write MD5. | FIX |
| D02 | High | Auth | `state` is never checked at login, so disabled users can sign in. | Refuse `state = 2` with a generic message. | FIX |
| D03 | High | Auth | No rate limit or lockout. | Configurable failure limit and cooldown; audit events. | FIX |
| D04 | Medium | Auth | `login_attempts`, `last_login_ip`, `last_login_dt` are never maintained. | Update on success/failure; add IP text column for IPv6. | FIX |
| D05 | Medium | Auth | Failed login is silent and reveals nothing; no guidance. | Generic “Invalid username or password.”; no user enumeration. | FIX |
| D06 | High | Auth | Forced-password-change guard is commented out; redirect target `?p=change_password` is not a registered module. | Real middleware guard; force change via `?p=user_options` until `fpc = 1`. | FIX |
| D07 | High | Session | Default PHP session behavior; no regeneration, no secure cookie flags, no timeout. | Regenerate on login, HttpOnly/SameSite/Secure, idle and absolute timeouts, destroy on logout. | FIX |
| D08 | High | Auth / PIS | Login recursively retries after writing `acl_id = 1`, `group_id = 1`, `state = 1` whenever `location_id = 0`. | One explicit sync; only default unconfigured accounts; validate department; no recursion; never demote an existing role. | FIX |
| D09 | High | CSRF | No CSRF tokens on any form or AJAX mutation. | Synchronizer token on every POST/PUT/PATCH/DELETE, including login/logout. | FIX |
| D10 | Critical | Authorization | Data getters check only `isset($_SESSION['user_id'])`, if anything. | Enforce parent module ACL and object-level scope on every getter. | FIX |
| D11 | Critical | Authorization | Process endpoints check only a session; some have no check at all. | Enforce capability policy (§6.3) plus CSRF and object ownership. | FIX |
| D12 | Critical | Authorization | `counter/` and root `reports.php` are effectively unauthenticated. | Require ACL >= 3 (or a scoped token) and audit access. | FIX |
| D13 | Critical | Approval | Individual approve/disapprove/undo do not verify supervisor relationship, state, or self-assignment. | Authorize actor, require closed + self-created/self-assigned, keep tri-state transitions. | FIX |
| D14 | Critical | Approval | Batch endpoints trust any IDs; they do not validate ownership, state, or existence and are not atomic. | Validate the whole selection, authorize each ticket, apply atomically, log only changes. | FIX |
| D15 | High | XSS | Ticket issue, notes, log data, names, and remarks are echoed as raw HTML. | Sanitize on write with an allowlist; escape on read unless known-sanitized. | FIX |
| D16 | High | Uploads | Email attachments have no type/size check, use a world-writable directory, and are stored under the web root. | Allowlist, size caps, magic-byte check, random names, storage outside web root, authorized download. | FIX |
| D17 | Critical | SQL injection | Request values are interpolated into SQL across tickets, admin, counter, and reports. | Parameterize every query; no string concatenation of request data. | FIX |
| D18 | High | Filesystem | Attachment filenames are only regex-sanitized; no storage abstraction or traversal defense beyond that. | Generate storage names, resolve paths under a fixed root, reject traversal, never trust original names. | FIX |
| D19 | Critical | Secrets | Committed DB/IMAP fallbacks and placeholders; test credentials documented. | Environment/secret manager only; rotate at cutover; no default passwords. | FIX |
| D20 | High | Admin | User delete is a hard `DELETE` with no checks. | Soft-delete/deactivate, block self and `id = 1`, preserve references. | FIX |
| D21 | High | Admin | Category/group/location delete is a hard `DELETE` with no reference checks. | Soft-delete or block when referenced; keep historical tickets visible. | FIX |
| D22 | Medium | Admin | Add user omits `state`, login counters, and profile fields. | Write explicit `state = 1`, zeroed counters, empty profile path. | FIX |
| D23 | High | Admin | Temporary password is `fn + ln`, MD5-hashed; `fpc = 1`; plaintext is stored in a notification. | Random one-time secret or invite link; `fpc = 2`; never store plaintext. | FIX |
| D24 | Medium | Admin | Add-user location dropdown queries `$sqli` instead of `$sql`, so it is empty. | Fix the query and add a regression test. | FIX |
| D25 | Medium | Admin | No validation that location/group/ACL exist; username is not checked as an email. | Validate foreign IDs and username format. | FIX |
| D26 | Medium | Tickets | Create validates `issue` twice and never validates `subject`; messages claim otherwise. | Validate subject and issue independently; keep the legacy text. | FIX |
| D27 | Medium | Tickets | Staff create always writes `assigned_gid = 1` and logs group 1 even though no group field exists. | Default `assigned_gid = 0`, skip the group log unless a group was explicitly chosen; or add a real group selector. | DECIDE + FIX |
| D28 | Low | Tickets | Success message includes `time()`: “The ticket was created. 1762...”. | Remove the epoch. | FIX |
| D29 | Medium | Time | Backdating stores midnight in `created_date` but today’s date in `created_time`. | Store consistent selected date/time; reject invalid dates. | FIX |
| D30 | Low | Debounce | One session-wide `last_write` blocks a second staff-create submission within one second. | Scope idempotency per form/action; keep a short duplicate guard. | FIX |
| D31 | Low | Email | Preview strips the last 50 characters of the body. | Deterministic plain-text excerpt, no tail removal. | FIX |
| D32 | High | End user | EU create endpoint has no session guard or role check; duplicate guard is a one-second exact match. | Require authentication and an idempotency key; keep the duplicate message. | FIX |
| D33 | Medium | End user | Minimal validation; `place`/`contact` accept “0” as empty; huge HTML accepted. | Trim, normalize, cap lengths and payload size; document the “0” choice. | FIX |
| D34 | High | Edit | No ticket-existence check, no ownership check, no lock; concurrent edits overwrite each other. | 404 unknown, authorize, lock/version, transactional update. | FIX |
| D35 | Medium | Edit | `assigned_date`/`assigned_time` are never maintained. | Set them when the assignee changes; backfill only with owner approval. | FIX |
| D36 | Medium | Close | Re-closing overwrites resolver and timestamps. | Idempotent close: keep the first resolver/timestamps, return “already closed.” | FIX |
| D37 | High | Close | Any logged-in user can close any ticket by ID. | Enforce §6.3 close permission and object scope. | FIX |
| D38 | Medium | Close | Survey URL is hardcoded to an internal HTTP host. | Configuration value with the legacy default; validate scheme/host. | FIX |
| D39 | Medium | Close | Closing a nonexistent ticket updates nothing but still logs and reports success. | Return 404; never log an action that did not occur. | FIX |
| D40 | Low | Close | Duplicate-log guard compares exact HTML, so a changed survey URL creates duplicates. | Use an idempotency key/action type. | FIX |
| D41 | Medium | Approval | Individual disapproval accepts empty remarks even though the UI requires them. | Require remarks server-side for every disapproval. | FIX |
| D42 | High | Approval | Open or non-self-assigned tickets can be approved by direct endpoint call. | Enforce closed + `created_uid = assigned_uid`. | FIX |
| D43 | Medium | Approval | Batch logs every submitted ID, including nonexistent/unchanged tickets. | Log only rows actually changed. | FIX |
| D44 | Low | Approval | Cutoffs and the “Hide tickets older than Jul 22, 2026” label are hardcoded. | Configure both cutoffs; derive the label. | FIX |
| D45 | Medium | Approval | Badge cutoff and list cutoff differ with no explanation, and the badge ignores indirect reports. | Document the intended scope; configure both; make indirect inclusion explicit. | DECIDE |
| D46 | Medium | Dashboard | `$group_closed_today =+ $count` assigns instead of adding, showing the last user’s count. | Use `+=` or one aggregate query. | FIX |
| D47 | Medium | Dashboard | Card labeled “This Year” counts only today. | Compute year-to-date or rename to “Today.” | FIX |
| D48 | Low | Dashboard | Two cards show the same metric. | Make them distinct or remove one. | FIX |
| D49 | Low | Dashboard | “Tech Status” menu links to an unimplemented view. | Implement it or remove the link. | FIX |
| D50 | Low | Dashboard | Per-page row loops and repeated queries. | Use aggregate SQL and cache briefly. | FIX |
| D51 | Medium | Monitoring | Recreates the entire grid every 5 seconds, losing scroll/selection/filter/sort. | Keep the grid and refresh its data source; pause when hidden. | FIX |
| D52 | Medium | Lists | “Closed Tickets By You” calls the endpoint without `id` and defaults to open tickets, so it is empty/wrong. | Pass the session user ID; filter `state_id = 2 AND resolved_uid = user`. | FIX |
| D53 | High | Data semantics | `tickets.assigned_gid` is both a group ID and a units count. | Preserve values; do not normalize; label each screen accurately. | DECIDE |
| D54 | Medium | Counter | Main report's Enter-submit handler throws because `#type` is missing; the per-category page always returns empty (wrong result-set index) and ignores its filters; other reports exclude the final day. | Implement both reports, normalize date ranges, remove dead controls. | FIX |
| D55 | Medium | End user | A user with `location_id = 0` creates a ticket hidden by INNER JOINs. | Require a valid location or render “Unspecified” without hiding the ticket. | FIX |
| D56 | High | Lists/detail | INNER JOINs hide tickets with unknown users/locations/categories and logs by deleted users. | LEFT JOIN everywhere; render archived/unknown labels. | FIX |
| D57 | High | Email | Unknown senders create orphan tickets (`eu_uid = 0`, location 0). | Quarantine or use a configured unknown-sender account; never silently orphan. | FIX |
| D58 | High | Email | Cron includes the web bootstrap, uses placeholders, processes one message, and deletes mail even on failure. | CLI-only service, TLS config, UID cursor, per-message transaction, delete after commit, ingest log. | FIX |
| D59 | Low | Search | “Print Results” and “Clear Results” both only clear the client filter; print does not print. | Real print/export and real reset. | FIX |
| D60 | Low | UI | Tickets submenu ignores `enabled`; search grid is titled “No. of units/items/persons.” | Filter enabled modules; correct the title. | FIX |
| D61 | Low | UI | Row template puts the person and location in ambiguous, unlabeled slots. | Render explicit requester and location values. | FIX |
| D62 | Low | Lists | “Created By User” also includes assigned/resolved tickets. | Rename to “Tickets Involving User” or restrict to `created_uid`. | DECIDE |
| D63 | Medium | Logs | INNER JOIN users hides logs whose author was deleted. | LEFT JOIN and show “Deleted user.” | FIX |
| D64 | Medium | Audit | No audit trail for admin mutations, approvals, deletes, or permission changes. | Append-only `admin_audit` plus structured logs. | FIX |
| D65 | High | Routing | `index.php` includes `mods/<path>` directly from the database. | Allowlist resolved paths; reject traversal and unregistered files. | FIX |
| D66 | High | Data | No foreign keys; deletes and typos create dangling references. | Application validation, soft deletes, indexes; FKs on new tables only. | FIX |
| D67 | Medium | Errors | Raw `mysqli_error` output and mislabeled duration fields leak schema/format details. | Log details server-side; return safe messages; correct labels. | FIX |
| D68 | Low | Admin | Location `address` and `type` are never editable despite being schema fields. | Add address/type fields with validation; preserve existing values. | FIX |
| D69 | High | Permissions | Permission updates accept any value, re-query a superset of the rendered modules (including unposted type-4/disabled rows with a real ACL), cascade in alphabetical order so parent/child choices conflict, and silently set unposted modules to ACL 0 (open to every authenticated user). | Validate ACLs, update only rendered modules, define cascade precedence, transact, audit, prevent self-lockout. | FIX |
| D70 | Medium | Supervisors | No cycle detection; A can supervise B and B supervise A. | Reject cycles and cap depth. | FIX |
| D71 | High | Migration | Add-user rows often have `state = 0`, which means “never set.” | Normalize `0` to enabled during migration; keep `2` disabled. | FIX |
| D72 | High | Migration | Duplicate usernames are possible; login returns the first match. | Detect duplicates; require resolution; reject ambiguous login. | FIX |
| D73 | High | Attachments | Path stored in an `int` column; no authorization, MIME, size, or download route. | Add `storage_path` and metadata; authorized downloads; keep legacy column. | FIX |
| D74 | Medium | Notifications | Rows are written but never read; `state` never advances. | Implement in-app notifications or a real outbox; never store passwords. | DECIDE |
| D75 | Medium | Email | `emailtodb_log`/`emailtodb_email` tables do not exist, so logging inserts fail silently. | Replace with `email_ingest_log`; do not call missing tables. | FIX |
| D95 | High | Password | Minimum length 4 (self) and 5 (admin); no current-password check. | Minimum 12 configurable; require current password; reject reuse. | FIX |
| D96 | High | Password | Self-service change does not require the current password. | Require and verify it; rate-limit attempts. | FIX |
| D97 | High | Auth / PIS | PIS sync recurses; a department of `0` can loop indefinitely, and the outer call overwrites the refreshed session with the stale pre-sync row. | Load the updated row once; validate the department; fail closed; never recurse. | FIX |

**Reserved:** D76–D94 are unused. D95–D96 retain their legacy references from earlier drafts of this document. Add new defects as D98+ and update this table; do not renumber existing entries, because other sections cite them.

### Deviation log template

For each FIX the implementation must record:

```text
ID: Dxx
Title:
Legacy behavior:
Target behavior:
User-visible change: yes/no
Data change: none/additive/backfill (describe)
Owner approval: name/date or “not required (security)”
Test: AT-xx
Rollback: how to restore the legacy behavior if needed
```

Do not ship a FIX without a test and a rollback note.

---

## 38. Acceptance tests

These are minimum end-to-end checks. Each test must run against a copy of production data with the legacy application available for comparison. “Same as legacy” means the visible outcome and stored rows match, except for listed deviations.

### Authentication and session

- **AT-01 Legacy login:** an MD5 user logs in with the correct password; the row is rehashed; the next login uses the new hash. Wrong password returns the generic message.
- **AT-02 Disabled account:** `state = 2` cannot log in; `state = 1` can. A legacy `state = 0` row is enabled by the migration and can log in.
- **AT-03 Lockout:** after the configured number of failures, the account is temporarily locked; a success after cooldown resets the counter.
- **AT-04 Forced change:** a user with `fpc = 2` is blocked from every route except user options/logout; changing the password sets `fpc = 1` and restores navigation.
- **AT-05 Session:** login regenerates the ID; logout destroys the session; cookies carry HttpOnly/SameSite/Secure in production; idle timeout works.
- **AT-06 PIS sync:** a user with `location_id = 0` and a valid payroll department gets that location once; an existing administrator with `location_id = 0` is not demoted. Missing payroll fails closed with a clear message.

### Ticket creation

- **AT-07 Staff create:** ACL 2 creates a ticket with subject, issue, EU, location, category, assignee; the row has `state_id = 1`, `assigned_gid = 0` (D27), `created_date` midnight, `created_time` full timestamp; logs match the catalogue.
- **AT-08 Self-assignment:** ACL 3 cannot override the assignee; the row is assigned to the actor even if a different `assigned_uid` is posted.
- **AT-09 Validation:** empty subject is rejected though `issue` is valid; empty issue is rejected; invalid location/category/EU are rejected; oversized HTML is rejected.
- **AT-10 Backdate:** a valid `ticket_date`/`ticket_time` pair stores consistent values; an invalid pair is rejected; a one-sided pair is rejected.
- **AT-11 EU create:** an end user creates a ticket with category 1, location from session, `assigned_uid = 0`; duplicate within the guard returns “Ticket is already created.”; success text and open count match.
- **AT-12 EU location 0:** the target either blocks creation with a clear message or stores/renders “Unspecified” without hiding the ticket (D55).

### Editing and closing

- **AT-13 Note:** adding a note writes one log row and does not change subject/issue; a duplicate within the debounce is blocked.
- **AT-14 Metadata:** changing EU, location, category, units, and assignee writes the exact legacy log strings and updates only those columns.
- **AT-15 Category 1:** editing a ticket to category 1 is rejected with “A category is required.”
- **AT-16 Lock:** a closed, approved ticket is read-only. A closed, unapproved ticket remains editable, matching legacy. An end user cannot edit either.
- **AT-17 Close:** closing sets `state_id = 2`, resolver, and timestamps; the log contains the survey link; the survey prompt appears.
- **AT-18 Close idempotency:** a second close returns “The ticket is already closed.” and does not change the resolver/timestamps or duplicate the log.
- **AT-19 Close authorization:** an unrelated ACL 2 user cannot close someone else’s ticket; the assignee/creator/ACL 3 can.
- **AT-20 Survey:** changing the configured URL changes the stored log and copy link; no hardcoded host remains.

### Approval

- **AT-21 Individual:** a supervisor approves a subordinate’s closed self-assigned ticket; the flag becomes 1 and the log matches. Disapprove requires remarks; undo returns it to 0.
- **AT-22 Unauthorized approval:** a non-supervisor, a supervisor of a different team, or an ACL 2 user cannot approve by direct URL.
- **AT-23 Invalid state:** an open ticket or a ticket assigned to someone else cannot be approved; the endpoint returns a clear error and changes nothing.
- **AT-24 Batch:** mixed valid/invalid selection is rejected atomically with per-ID errors; a valid selection updates all, logs only changed rows, and returns the correct count.
- **AT-25 Badge:** the badge counts only direct active subordinates, closed self-assigned, pending, after the configured cutoff; it hides at zero.
- **AT-26 Hide old:** the checkbox defaults on, hides rows before the cutoff, and dims them at 40% when off; the label matches the configured date.

### Lists, search, and detail

- **AT-27 Open list:** category 23/24 excluded; ordering `id DESC`; row content and deep link work.
- **AT-28 Closed list:** all closed tickets, `resolved_time DESC`, capped at the configured limit; unknown users/locations still appear.
- **AT-29 Closed by you:** shows only tickets the current user resolved and closed; the legacy empty-list bug is gone.
- **AT-30 Search:** each filter field works alone and combined; the cap is enforced; SQL injection probes fail safely; print/reset behave.
- **AT-31 Detail:** timeline is oldest first; deleted authors show “Deleted user”; archived categories/locations show labels; issue/log HTML is sanitized.
- **AT-32 Deep link:** opening `#ticket=<id>` auto-opens the ticket; back restores the list; closing clears the fragment.

### Admin and permissions

- **AT-33 User CRUD:** add user writes `state = 1`, `fpc = 2`, a random temporary secret, and a valid notification; edit does not reset the password; delete deactivates and preserves tickets.
- **AT-34 Location dropdown:** the add-user location list is populated (D24).
- **AT-35 Category/group/location:** create/edit validation texts match; deleting a referenced row is blocked or soft-deletes; inactive categories disappear from ticket dropdowns but remain on historical tickets.
- **AT-36 Supervisor:** set/remove works; self and inactive supervisors are rejected; a cycle and excessive depth are rejected.
- **AT-37 Permissions:** only ACL 9 can access; posted ACLs must exist; parent cascades to children; the last admin cannot lock themselves out; changes are audited.

### Reports and email

- **AT-38 Counter:** ACL 3 can generate per-ticket XLSX for a date range and employee set; ACL 1/2 cannot; the per-category report works or is absent; the final day is included; JO/REG sets are configurable.
- **AT-39 Root report:** unauthenticated access is rejected; an authorized request returns the documented schema; `date` is validated; duration is labeled correctly.
- **AT-40 Email known sender:** a message from a known user creates exactly one category-1 open ticket, stores the body/preview correctly, attaches files with metadata, writes an ingest log, and sends the confirmation notification.
- **AT-41 Email unknown sender:** the message is quarantined or assigned to the configured unknown account; no orphan ticket is silently created.
- **AT-42 Email failure:** a simulated DB/attachment failure leaves the message unprocessed for retry and records the error; no partial ticket.
- **AT-43 Email idempotency:** running the job twice on the same `Message-ID` creates one ticket.

### Security regression

- **AT-44 CSRF:** a mutation without a token is rejected; with a valid token it succeeds; a token from another session fails.
- **AT-45 SQL injection:** payloads in every ID/date/search field do not alter queries or leak errors.
- **AT-46 XSS:** script/event-handler payloads in subject, issue, note, remarks, names, and category labels render inert in lists, detail, logs, exports, and JSON.
- **AT-47 IDOR:** an end user cannot read another user’s ticket by changing `id`; an ACL 2 user cannot mutate an unrelated ticket; data getters enforce the same scope.
- **AT-48 Files:** a disallowed type, oversized file, double extension, and traversal filename are rejected; an authorized download works; an unauthorized download returns 404/403.
- **AT-49 Headers:** CSP, nosniff, frame denial, and HSTS (production) are present.
- **AT-50 Secrets:** no default credentials, placeholders, or committed secrets are required for a production boot.

### Data preservation

- **AT-51 Row counts:** every legacy table row count matches after migration except documented redactions/normalizations.
- **AT-52 IDs:** max IDs and all foreign references are unchanged; `users.id = 1` and `categories.id = 1` still exist and are protected.
- **AT-53 Read-only legacy rows:** a sample of open, closed, approved, disapproved, orphaned, and backdated tickets renders with the same values as legacy.
- **AT-54 Dual write:** a ticket created by the target is readable by legacy code with the same meaning; a ticket created by legacy is readable by the target.

---

## 39. Migration and cutover plan

### Phase 0 — Freeze and inventory

1. Announce a change freeze for schema and code.
2. Export the production `mods` table and store it with the migration artifacts (§2.5).
3. Record the legacy application version, PHP version, MySQL version, and current cron schedule.
4. Take a full logical backup (`mysqldump --single-transaction --routines --triggers`) and a physical snapshot if available.
5. Verify the backup by restoring it to a staging database and running the preflight audit (§36).

### Phase 1 — Additive schema

1. Apply the additive migrations in §9.7 to staging, then production.
2. Add indexes listed in §9.7; do not drop legacy indexes.
3. Convert tables to InnoDB only after the backup and only if the target requires transactions. Verify row counts, auto-increment values, and indexes.
4. Create `auth_audit`, `admin_audit`, and `email_ingest_log`.

### Phase 2 — Data normalization

1. Normalize `users.state` `0`/`NULL` to `1`; keep `2` disabled (D71).
2. Resolve duplicate usernames (D72) with the data owner; do not auto-merge.
3. Mark accounts with missing/invalid ACL, location, or group for administrator review; do not guess.
4. Redact plaintext passwords from `notifications.data`.
5. Backfill `attachements.storage_path` by scanning the legacy directory; leave unmatched rows with `storage_path = NULL` and a report.
6. Do not rewrite ticket timestamps, approvals, or content.
7. Run the preflight audit again and store the before/after counts.

### Phase 3 — Module and configuration import

1. Import the production `mods` rows verbatim, then add any missing migration rows (`supervisors`, `tickets_by_subordinates`, `tickets_by_supervisors`, and their endpoints).
2. Map each `path` to a target route/controller. Unknown paths get a “not migrated” page, not a silent 404.
3. Set configuration values from §11, including the survey URL, cutoffs, JO/REG IDs, and units cutoff.
4. Configure the mail ingest mailbox, storage root, base URL, and secrets.

### Phase 4 — Authentication migration

1. Deploy the login path that accepts MD5 once and rehashes.
2. Do not bulk-reset passwords. Let users migrate on first login.
3. After the agreed window, optionally force a reset for accounts still on MD5.
4. Test with a sample of real accounts in staging; never log passwords.

### Phase 5 — Dual run

1. Run legacy and target against the same database for an agreed period (for example two weeks).
2. Route a small pilot group to the target; keep the legacy URL available. Ensure the pilot group can still authenticate through legacy (see the dual-run hazard in §7.2).
3. Compare daily counts: tickets created, closed, approved, logs written, notifications, email ingestion.
4. Reconcile any row written by one system that the other cannot read.
5. Keep the legacy cron disabled once the target cron is proven, to avoid double ingestion.

### Phase 6 — Cutover

1. Announce a maintenance window.
2. Stop the legacy cron and disable legacy writes (read-only mode if possible).
3. Take a final backup.
4. Deploy the target, run migrations, and smoke-test AT-01, AT-07, AT-17, AT-21, AT-38, AT-40, AT-44, AT-47, AT-51.
5. Point the canonical URL at the target; keep `?p=` aliases working.
6. Enable the target cron and monitoring.
7. Monitor error rates, login failures, email lag, and approval counts for 24–72 hours.

### Phase 7 — Rollback

- Keep the legacy code and database backup available for at least the agreed rollback window (for example 30 days).
- Rollback triggers: data corruption, inability to log in for a role, ticket creation failure, approval loss, or email loss.
- Rollback steps: stop the target, restore the pre-cutover database (or reverse additive changes), restart legacy, re-enable legacy cron.
- Because the schema is additive, the target’s new columns/tables can remain during rollback; legacy ignores them. Never roll back by dropping legacy columns.

### Phase 8 — Decommission

1. Archive the legacy code and database snapshot.
2. Remove the legacy cron and credentials.
3. Rotate database, IMAP, and any API secrets.
4. Keep a read-only archive of attachments and `mods` exports according to retention policy.
5. Publish the final deviation log and data-normalization report.

### Cutover checklist

- [ ] Production `mods` exported and imported.
- [ ] Backup restored and verified.
- [ ] Additive migrations applied; legacy columns untouched.
- [ ] `users.state` normalized; duplicate usernames resolved.
- [ ] MD5 login path tested; rehash verified.
- [ ] All magic constants configured.
- [ ] Survey URL reachable from the target network.
- [ ] Email mailbox credentials valid; test message ingested and deleted only after commit.
- [ ] Attachments readable through the authorized route.
- [ ] CSRF, CSP, cookies, and rate limits verified in production mode.
- [ ] Legacy `?p=` URLs and `#ticket=` deep links verified.
- [ ] Row counts and max IDs match.
- [ ] Rollback rehearsal completed.

---

## 40. Deliverables and definition of done

### Code and infrastructure

- Target application source with a documented local setup, environment template, and seed/bootstrap command.
- Database migrations: additive schema, normalization, indexes, audit tables, and a rollback script for each destructive step.
- CLI commands: `bootstrap-admin`, `migrate`, `ingest-email`, `rehash-passwords` (optional), `verify-data`, `export-mods`.
- Configuration module with every key in §11 and safe defaults for development only.
- Automated tests: unit tests for validation/authorization, integration tests for ticket lifecycle, and end-to-end tests for AT-01–AT-54.
- CI pipeline: lint, type check, tests, dependency audit, and a migration dry run against a disposable database.
- Deployment manifests and a runbook for cron, backups, log rotation, and rollback.

### Documentation

- `README.md` updated with the target stack, setup, roles, workflows, and schema summary; keep it high-level and stack-agnostic where it describes behavior.
- `RECREATE_PROMPT.md` (this document) checked in as the behavioral contract.
- Data dictionary covering every table and column, including legacy meanings and new columns.
- Route map from every legacy `?p=` module and file path to the target route.
- Deviation log (§37 template) with owner approval where required.
- Operations runbook: email ingestion, stuck queue, failed login, permission lockout, restore procedure.
- User-facing quick guides for end users, technicians, supervisors, and administrators.

### Quality gates

- All acceptance tests pass against a production-sized copy.
- No critical/high security findings remain open; medium findings have an owner and date.
- No unparameterized SQL, no raw request interpolation, no `eval`/dynamic include of request data.
- Every mutation has CSRF protection and an authorization test.
- Every list/detail survives orphaned references without hiding rows.
- Every legacy row is readable; row counts and IDs match; no legacy column was dropped or narrowed.
- Performance: ticket list first page under 2 seconds at production volume; search under 3 seconds with the configured cap; dashboard under 2 seconds; email batch processes the backlog within the cron interval.
- Accessibility: automated axe scan has no critical violations on login, list, detail, and admin forms.
- Backups: restore test completed within the agreed RTO.

### Handover

- A walkthrough with the data owner covering roles, approvals, reports, and the deviation log.
- Named owners for the application, database, email mailbox, and survey integration.
- Credentials rotated and stored in the secret manager.
- Legacy system archived read-only with a documented restore path.

---

## 41. Open questions and data-owner decisions

Answer these before implementation; record the answer next to the question.

1. **Target stack.** Fill in `[TARGET STACK]`: language, framework, database driver, UI library, hosting, and deployment model. The behavior contract does not depend on it, but the implementation plan does.
2. **`assigned_gid` meaning.** Is it a group assignment, a “No. of units/items/persons” count, or both by screen? Should the target split it into two new columns (`assigned_group_id`, `units_count`) while preserving the legacy column? D53.
3. **Staff create group field.** Should the target add a real group selector, or keep the legacy behavior of no group field and `assigned_gid = 0`? D27.
4. **Approval scope.** Should approval remain limited to closed, self-created, self-assigned tickets, or should supervisors also review tickets assigned to their team? D13/D42.
5. **Approval cutoffs.** Are the two 2026 cutoffs still meaningful, or should they become “last 30 days” / configurable per deployment? D44/D45.
6. **Indirect reports.** Should the pending badge include indirect subordinates, matching the optional list filter? D45.
7. **Survey.** Is the hardcoded `192.163.8.7` survey URL still correct? Who owns it, and should it be sent by email instead of copied? D38.
8. **Email sender policy.** For unknown senders, choose quarantine, a configured “Unknown sender” account, or reject with a bounce. D57.
9. **Email mailbox.** Which folder, TLS mode, polling interval, and retention? Should the job process all messages per run? D58.
10. **Notifications.** In-app center, email outbox, or stop writing rows? D74.
11. **Attachments.** Are uploads required in the target, or is read-only access to historical email attachments enough? D16/D73.
12. **Soft deletes.** Confirm that users/categories/groups/locations should be deactivated rather than deleted, and who can restore them. D20/D21.
13. **Duplicate usernames.** Which row is authoritative for each duplicate `mail`, and should they be merged or renamed? D72.
14. **Legacy `state = 0` users.** Confirm they should be enabled (recommended) rather than treated as disabled. D71.
15. **Closed rows with missing resolver/timestamps.** Leave as “Unknown,” or run a one-time repair? Who supplies the correct values?
16. **`resolution` field.** Should the target start capturing resolution text on close? If yes, that is a new workflow and needs a UI/API decision; do not repurpose the column silently.
17. **Reopen workflow.** Confirm that tickets remain one-way (open → closed) with no reopen.
18. **Ticket deletion.** Confirm tickets are never deleted; define retention and archival for old closed tickets.
19. **Reports.** Which external consumers use `reports.php` and the counter JSON? Can they move to an authenticated API or scheduled export? D12.
20. **Counter JO/REG lists.** Confirm the hardcoded IDs are the REG list, that JO should remain the complement of that list, and whether the list belongs in configuration or a payroll/employment table. D54.
21. **Password policy.** Confirm minimum length 12, lockout threshold, session timeouts, and whether to force a reset for remaining MD5 accounts. D95.
22. **PIS/payroll.** Is the payroll sync still required? What should happen when payroll is unavailable? D08.
23. **Module registry.** Provide the production `mods` export and identify any modules that should not be migrated.
24. **Branding.** Confirm the application title, logo, colors, and whether the old “Express Service Desk” name should remain in notification text.
25. **Data retention.** Define retention for tickets, logs, notifications, audit rows, attachments, and email ingest logs.
26. **Localization.** Confirm English-only and Asia/Manila; decide whether to store UTC and render Manila time in the target (recommended) while preserving legacy epoch meanings.

---

## Appendix A — Legacy DDL (authoritative contract)

This is the schema the target must remain compatible with. It is reproduced from `config/sts.sql`; do not run it over production as a migration. Apply additive changes from §9.7 instead.

```sql
CREATE TABLE IF NOT EXISTS `acls` (
  `id` int(11) NOT NULL,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acl` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `acls` (`id`, `name`) VALUES
(9, 'Administrator'),
(3, 'Help Desk'),
(2, 'Technician'),
(1, 'End User');

CREATE TABLE IF NOT EXISTS `application_settings` (
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `setting` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `application_settings` (`name`, `setting`) VALUES
('title', 'PHP Ticket System'),
('version', '1');

CREATE TABLE IF NOT EXISTS `attachements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_name_org` varchar(255) NOT NULL,
  `file_name` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `address` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `type` int(11) NOT NULL DEFAULT 1 COMMENT '1 = Global 2 = Mileage Only',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `locations2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `address` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `type` int(11) NOT NULL DEFAULT 1 COMMENT '1 = Global 2 = Mileage Only',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `mods` (
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `parent` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `display` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `path` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `has_icon` int(11) NOT NULL COMMENT '1 = TRUE 2 = FALSE',
  `icon` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `acl` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `enabled` int(11) NOT NULL COMMENT '1 = TRUE 2 = FALSE',
  `weight` int(11) NOT NULL,
  PRIMARY KEY (`name`),
  KEY `name` (`name`),
  KEY `parent` (`parent`),
  FULLTEXT KEY `parent_2` (`parent`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `data` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `dt` int(11) NOT NULL,
  `state` int(11) NOT NULL COMMENT '1 = New 2 = Sent',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `states` (
  `id` int(11) NOT NULL,
  `name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `states` (`id`, `name`) VALUES
(1, 'Open'),
(2, 'Closed');

CREATE TABLE IF NOT EXISTS `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` text NOT NULL,
  `issue` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `preview` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `resolution` text CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created_uid` int(11) NOT NULL,
  `created_date` int(11) NOT NULL,
  `created_time` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `place` varchar(1000) DEFAULT NULL,
  `contact` varchar(45) DEFAULT NULL,
  `assigned_gid` int(11) NOT NULL DEFAULT 0,
  `assigned_uid` int(11) NOT NULL DEFAULT 0,
  `assigned_date` int(11) NOT NULL DEFAULT 0,
  `assigned_time` int(11) NOT NULL DEFAULT 0,
  `state_id` int(11) NOT NULL,
  `resolved_uid` int(11) NOT NULL DEFAULT 0,
  `resolved_date` int(11) NOT NULL DEFAULT 0,
  `resolved_time` int(11) NOT NULL DEFAULT 0,
  `eu_uid` int(11) NOT NULL,
  `approved_by_supervisor` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tickets_id` (`id`),
  KEY `idx_tickets_assigned_uid_resolved_uid` (`assigned_uid`,`resolved_uid`),
  KEY `idx_tickets_resolved_date` (`resolved_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `tickets_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `data` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `date` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`),
  KEY `ticket_logindex` (`ticket_id`,`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fn` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `ln` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `mail` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `password` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `acl_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `fpc` int(11) NOT NULL COMMENT '1 = No 2 = Yes',
  `state` int(11) NOT NULL COMMENT '1 = Enabled 2 = Disabled',
  `login_attempts` int(11) NOT NULL,
  `last_login_ip` int(11) NOT NULL,
  `last_login_dt` int(11) NOT NULL,
  `profile_pic_path` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_users_id` (`id`),
  KEY `idx_users_group_id` (`group_id`),
  KEY `idx_supervisor_id` (`supervisor_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `users` (`id`, `fn`, `ln`, `mail`, `password`, `acl_id`, `location_id`, `group_id`, `fpc`, `state`, `login_attempts`, `last_login_ip`, `last_login_dt`, `profile_pic_path`) VALUES
(1, 'Administrator', 'System', 'admin', '5f4dcc3b5aa765d61d8327deb882cf99', 9, 1, 1, 1, 1, 0, 0, 0, '');
```

**Do not seed the `admin` row in production.** Use the bootstrap command (§2.4). The hash above is the MD5 of a known test password and must never be deployed.

### Migration history in the repository

| File | Effect |
|---|---|
| `20260721_001_add_supervisor_id.sql` | Adds `users.supervisor_id` and its index. |
| `20260721_002_add_approved_by_supervisor.sql` | Adds `tickets.approved_by_supervisor` with default 0. |
| `20260721_003_add_undo_approve_mod.sql` | Registers `process_undo_approve_ticket` in `mods`. |
| `20260721_004_auto_approve_assigned.sql` | Auto-approves existing tickets where `assigned_uid != 0 AND assigned_uid != eu_uid`. |
| `20260727_001_add_tickets_by_supervisors.sql` | Registers `tickets_by_supervisors` and its getter. |
| `20260727_002_rename_tickets_by_supervisors.sql` | Renames the display to “Tickets by Supervisor.” |

The production schema already reflects these changes; the files are retained for provenance.

---

## Appendix B — Module and endpoint inventory

The definitive list is the production `mods` export. This inventory maps every screen and data file found in the repository. Preserve the `?p=` names; target routes may be nested but must resolve these aliases.

Menu-only includes (`*_menu.php`) are not routed modules and are omitted from the tables. Three repository files are dead code and are not reachable from any module: `mods/tickets/search_tickets1.php` (an older search screen), `mods/tickets/ticket_filter.php` (an unused filter include), and `mods/tickets/tickets_menu.old.php` (the previous menu). Do not migrate them; if the production `mods` table points at one, treat that row as an open question.

### Screens and menus

| Legacy path | `?p=` name (expected) | Purpose | ACL |
|---|---|---|---|
| `mods/login/login.php` | `login` | Login form. | public |
| `mods/menu/top.php` | — | Top navigation include. | session |
| `mods/dashboard/dashboard.php` | `dashboard` | Metric cards; `v=tickets`. | 2+ |
| `mods/dashboard/ticket_monitoring.php` | `monitoring` | Live open-ticket grid. | 2+ |
| `mods/end_user/end_user.php` | `end_user` | End-user shell with `v=new_ticket` / `v=view_tickets`. | 1+ |
| `mods/end_user/new_ticket.php` | `end_user&v=new_ticket` | End-user ticket form. | 1+ |
| `mods/end_user/view_tickets.php` | `end_user&v=view_tickets` | End-user ticket list. | 1+ |
| `mods/tickets/all_open_tickets.php` | `all_open_tickets` | Open tickets excluding 23/24. | 2+ |
| `mods/tickets/all_closed_tickets.php` | `all_closed_tickets` | All closed tickets, capped. | 2+ |
| `mods/tickets/all_hardware_tickets.php` | `all_hardware_tickets` | Open category 23. | 2+ |
| `mods/tickets/all_announcements.php` | `all_announcements` | Open category 24. | 2+ |
| `mods/tickets/all_closed_request.php` | `all_closed_request` | Closed category 23. | 2+ |
| `mods/tickets/all_closed_tickets_by_you.php` | `all_closed_tickets_by_you` | Tickets resolved by the current user. | 2+ |
| `mods/tickets/created_by_user.php` | `created_by_user` | Tickets involving the current user. | 2+ |
| `mods/tickets/assigned_to_you.php` | `assigned_to_you` | Open tickets assigned to the current user. | 2+ |
| `mods/tickets/assigned_to_group.php` | `assigned_to_group` | Open tickets matching the user’s group value. | 2+ |
| `mods/tickets/unassigned_tickets.php` | `unassigned_tickets` | Open unassigned tickets. | 2+ |
| `mods/tickets/search_tickets.php` | `search_tickets` | Search form and results. | 2+ |
| `mods/tickets/create_ticket.php` | `create_ticket` | Staff create modal. | 2+ |
| `mods/tickets/edit_ticket.php` | `edit_ticket&id=<id>` | Detail/edit/close view. | 2+ / scoped |
| `mods/tickets/tickets_by_subordinates.php` | `tickets_by_subordinates` | Approval queue. | 3; menu hidden without direct reports |
| `mods/tickets/tickets_by_supervisors.php` | `tickets_by_supervisors` | Supervisor’s own closed tickets. | 3; menu hidden without a supervisor |
| `mods/tickets/tickets_menu.php` | — | Tickets submenu include. | session |
| `mods/administration/categories/categories.php` | `categories` | Category list. | 9 |
| `mods/administration/categories/add_category.php` | `add_category` | Add category modal. | 9 |
| `mods/administration/categories/edit_category.php` | `edit_category&id=<id>` | Edit category modal. | 9 |
| `mods/administration/groups/groups.php` | `groups` | Group list. | 9 |
| `mods/administration/groups/add_group.php` | `add_group` | Add group modal. | 9 |
| `mods/administration/groups/edit_group.php` | `edit_group&id=<id>` | Edit group modal. | 9 |
| `mods/administration/locations/locations.php` | `locations` | Location list. | 9 |
| `mods/administration/locations/add_location.php` | `add_location` | Add location modal. | 9 |
| `mods/administration/locations/edit_location.php` | `edit_location&id=<id>` | Edit location modal. | 9 |
| `mods/administration/users/users.php` | `users` | User list. | 9 |
| `mods/administration/users/add_user.php` | `add_user` | Add user modal. | 9 |
| `mods/administration/users/edit_user.php` | `edit_user&id=<id>` | Edit user modal. | 9 |
| `mods/administration/users/reset_user_password.php` | `reset_user_password&id=<id>` | Password reset tab. | 9 |
| `mods/administration/users/user_details.php` | — | User details tab include. | 9 |
| `mods/administration/permissions/permissions.php` | `permissions` | Module ACL matrix. | 9 |
| `mods/administration/supervisors/supervisors.php` | `supervisors` | Supervisor assignment grid. | 9 |
| `mods/administration/supervisors/edit_supervisor.php` | `edit_supervisor&id=<id>` | Supervisor modal. | 9 |
| `mods/administration/reports.php` | `reports` | Redirects to `counter/`. | 9 |
| `mods/administration/reports_by_category.php` | `reports_by_category` | Redirects to `counter/category.php`. | 9 |
| `mods/user_settings/user_options.php` | `user_options` | Change-password form. | 1+ |

### Data getters (JSON)

| Legacy path | `?p=` name (expected) | Purpose |
|---|---|---|
| `mods/tickets/data/get_all_open_tickets.php` | `get_all_open_tickets` | Open list excluding 23/24. |
| `mods/tickets/data/get_all_closed_tickets.php` | `get_all_closed_tickets` | Closed list, cap 4000. |
| `mods/tickets/data/get_all_open_hardware_tickets.php` | `get_all_open_hardware_tickets` | Open category 23. |
| `mods/tickets/data/get_all_open_announcements.php` | `get_all_open_announcements` | Open category 24. |
| `mods/tickets/data/get_all_closed_request.php` | `get_all_closed_request` | Closed category 23. |
| `mods/tickets/data/get_all_tickets_closed_by_user.php` | `get_all_tickets_closed_by_user` | Resolved-by-user list. |
| `mods/tickets/data/get_all_created_by_user.php` | `get_all_created_by_user` | Tickets involving a user. |
| `mods/tickets/data/get_all_tickets_created_by_user.php` | `get_all_tickets_created_by_user` | End-user list; `q=all`. |
| `mods/tickets/data/get_all_tickets_assigned_to_user.php` | `get_all_tickets_assigned_to_user` | Assigned-to-user list. |
| `mods/tickets/data/get_all_tickets_assigned_to_group.php` | `get_all_tickets_assigned_to_group` | Assigned-group/units list. |
| `mods/tickets/data/get_all_unassigned_tickets.php` | `get_all_unassigned_tickets` | Unassigned open list. |
| `mods/tickets/data/get_all_tickets.php` | `get_all_tickets` | Search/getter; `p=search_tickets`, cap 500. |
| `mods/tickets/data/get_all_tickets_by_subordinates.php` | `get_all_tickets_by_subordinates` | Approval queue. |
| `mods/tickets/data/get_all_tickets_by_supervisors.php` | `get_all_tickets_by_supervisors` | Supervisor’s own queue. |
| `mods/tickets/data/get_pending_review_count.php` | `get_pending_review_count` | Badge count JSON. |
| `mods/administration/categories/data/get_all_categories.php` | `get_all_categories` | Category grid. |
| `mods/administration/groups/data/get_all_groups.php` | `get_all_groups` | Group grid. |
| `mods/administration/locations/data/get_all_locations.php` | `get_all_locations` | Location grid. |
| `mods/administration/users/data/get_all_users.php` | `get_all_users` | User grid. |
| `mods/administration/supervisors/data/get_all_users.php` | `get_all_supervisor_users` | Supervisor grid (same file path as the user grid, different module name). |
| `mods/administration/supervisors/data/get_supervisor_options.php` | `get_supervisor_options` | Supervisor combo. |

### Process endpoints

| Legacy path | `?p=` name (expected) | Action |
|---|---|---|
| `mods/login/data/process_login.php` | `process_login` | Authenticate and redirect. |
| `mods/tickets/data/process_create_ticket.php` | `process_create_ticket` | Staff create. |
| `mods/tickets/data/process_eu_ticket.php` | `process_eu_ticket` | End-user create. |
| `mods/tickets/data/process_edit_ticket.php` | `process_edit_ticket&id=<id>` | Metadata/note update. |
| `mods/tickets/data/process_close_ticket.php` | `process_close_ticket&id=<id>` | Close and survey. |
| `mods/tickets/data/process_approve_ticket.php` | `process_approve_ticket&id=<id>` | Approve. |
| `mods/tickets/data/process_disapprove_ticket.php` | `process_disapprove_ticket&id=<id>` | Disapprove. |
| `mods/tickets/data/process_undo_approve_ticket.php` | `process_undo_approve_ticket&id=<id>` | Undo. |
| `mods/tickets/data/process_batch_approve_tickets.php` | `process_batch_approve_tickets` | Batch approve (JSON). |
| `mods/tickets/data/process_batch_disapprove_tickets.php` | `process_batch_disapprove_tickets` | Batch disapprove (JSON). |
| `mods/administration/categories/data/process_add_category.php` | `process_add_category` | Add category. |
| `mods/administration/categories/data/process_edit_category.php` | `process_edit_category&id=<id>` | Edit category. |
| `mods/administration/categories/data/process_delete_category.php` | `process_delete_category&id=<id>` | Delete category. |
| `mods/administration/groups/data/process_add_group.php` | `process_add_group` | Add group. |
| `mods/administration/groups/data/process_edit_group.php` | `process_edit_group&id=<id>` | Edit group. |
| `mods/administration/groups/data/process_delete_group.php` | `process_delete_group&id=<id>` | Delete group. |
| `mods/administration/locations/data/process_add_location.php` | `process_add_location` | Add location. |
| `mods/administration/locations/data/process_edit_location.php` | `process_edit_location&id=<id>` | Edit location. |
| `mods/administration/locations/data/process_delete_location.php` | `process_delete_location&id=<id>` | Delete location. |
| `mods/administration/users/data/process_add_user.php` | `process_add_user` | Add user. |
| `mods/administration/users/data/process_edit_user.php` | `process_edit_user&id=<id>` | Edit user. |
| `mods/administration/users/data/process_delete_user.php` | `process_delete_user&id=<id>` | Delete user. |
| `mods/administration/users/data/process_reset_user_password.php` | `process_reset_user_password&id=<id>` | Reset password. |
| `mods/administration/supervisors/data/process_set_supervisor.php` | `process_set_supervisor&id=<id>` | Set supervisor. |
| `mods/administration/supervisors/data/process_remove_supervisor.php` | `process_remove_supervisor&id=<id>` | Remove supervisor. |
| `mods/administration/permissions/data/process_update_permissions.php` | `process_update_permissions` | Update module ACLs. |
| `mods/user_settings/data/process_change_password.php` | `process_change_password&id=<id>` | Self password change. |

### Non-module routes

| Path | Purpose |
|---|---|
| `index.php?p=<name>` | Front controller. |
| `reports.php?date=YYYY-MM` | Unauthenticated JSON report (FIX D12). |
| `counter/index.php` | Per-ticket report UI. |
| `counter/category.php` | Per-category report UI. |
| `counter/request.php` | Report JSON API. |
| `config/cron/process_inbox.php` | IMAP ingestion entry point. |

---

## Appendix C — Configuration reference

All keys are mandatory configuration in the target. Suggested environment names are prefixed `ITS_`; the implementation may use a config file or secret manager, but the values must not be committed.

| Config key | Suggested env var | Legacy default | Notes |
|---|---|---|---|
| Database host | `ITS_DB_HOST` | `192.163.10.201` in `connect.php` fallback | Production value comes from `.env`; do not ship the fallback. |
| Database port | `ITS_DB_PORT` | `3306` | |
| Database name | `ITS_DB_NAME` | `its` | |
| Database user | `ITS_DB_USER` | `ihomstest` fallback | Use a least-privilege account. |
| Database password | `ITS_DB_PASSWORD` | `iHOMStestMySQL` fallback | Rotate at cutover. |
| Payroll source host | `ITS_PAYROLL_HOST` | `192.163.10.201` | PIS sync only. |
| Payroll source database | `ITS_PAYROLL_DB` | `jblmrhpayroll` | Read-only. |
| Session name | `ITS_SESSION_NAME` | `expressservicedesk` | Preserve for compatibility if needed. |
| Session idle timeout | `ITS_SESSION_IDLE_SECONDS` | none (PHP default) | Default 8 hours. |
| Session absolute timeout | `ITS_SESSION_ABSOLUTE_SECONDS` | none | Default 24 hours. |
| Application base URL | `ITS_BASE_URL` | derived `http://<host-ip>:90/its/index.php` | Must be explicit; used in links/notifications. |
| Application title | `ITS_APP_TITLE` | DB `application_settings.title` = `PHP Ticket System` | DB value wins or is the default. |
| Timezone | `ITS_TIMEZONE` | `Asia/Manila` | |
| Hardware category | `ITS_CATEGORY_HARDWARE` | `23` | Business data. |
| Announcements category | `ITS_CATEGORY_ANNOUNCEMENTS` | `24` | Business data. |
| Default category | `ITS_CATEGORY_DEFAULT` | `1` | Protected. |
| IHOMS location | `ITS_LOCATION_IHOMS` | `15` | Supervisor filter. |
| Default group | `ITS_GROUP_DEFAULT` | `1` | PIS sync and legacy create fallback. |
| Pending badge cutoff | `ITS_APPROVAL_PENDING_CUTOFF` | `1784553600` | 2026-07-20 21:20 Asia/Manila. |
| Hide-old cutoff | `ITS_APPROVAL_HIDE_OLD_CUTOFF` | `1784692800` | 2026-07-22 12:00 Asia/Manila. |
| Counter REG IDs | `ITS_COUNTER_EMPLOYEE_IDS` | `[334,566,335,1555,336,2243,2415]` | Comma-separated. REG checks these; JO checks everyone else. |
| Counter units cutoff | `ITS_COUNTER_UNITS_CUTOFF` | `2025-12-19 15:00:00` | After this, use `assigned_gid`. |
| Survey URL template | `ITS_SURVEY_URL` | `http://192.163.8.7/jblforms/form.php?use=V6U6202564133510372T1QA&ticketID={ticket_id}` | Validate scheme/host. |
| Attachment storage root | `ITS_ATTACHMENT_ROOT` | `mods/tickets/attachments/` | Target: outside web root. |
| Attachment max bytes | `ITS_ATTACHMENT_MAX_BYTES` | none | Default 25 MB. |
| Attachment allowed types | `ITS_ATTACHMENT_TYPES` | none | Default PDF, PNG, JPEG, GIF, WEBP, TXT, CSV, Office, ZIP. |
| Closed list cap | `ITS_CLOSED_LIST_LIMIT` | `4000` | |
| Search cap | `ITS_SEARCH_LIMIT` | `500` | |
| List page size | `ITS_LIST_PAGE_SIZE` | `40` | |
| Grid height | `ITS_GRID_HEIGHT` | `700` | |
| Subject truncate length | `ITS_SUBJECT_TRUNCATE` | `80` | |
| Dashboard refresh | `ITS_DASHBOARD_REFRESH_MS` | `5000` | Monitoring only. |
| Debounce seconds | `ITS_DEBOUNCE_SECONDS` | `1` | |
| Password minimum | `ITS_PASSWORD_MIN` | `4` self / `5` admin | Target 12. |
| Login failure limit | `ITS_LOGIN_MAX_FAILURES` | none | Default 5. |
| Login lockout seconds | `ITS_LOGIN_LOCKOUT_SECONDS` | none | Default 900. |
| IMAP host | `ITS_IMAP_HOST` | `SERVER` placeholder | Required. |
| IMAP port | `ITS_IMAP_PORT` | `:PORT` placeholder | Required. |
| IMAP user | `ITS_IMAP_USER` | `USER` placeholder | Required. |
| IMAP password | `ITS_IMAP_PASSWORD` | `password` placeholder | Secret manager. |
| IMAP folder | `ITS_IMAP_FOLDER` | `INBOX` | |
| IMAP TLS | `ITS_IMAP_TLS` | none | Required in production. |
| Email poll batch size | `ITS_EMAIL_BATCH_SIZE` | `1` (legacy) | Target: all available up to a cap. |
| Notification mode | `ITS_NOTIFICATION_MODE` | `outbox` (unused) | `in_app`, `email`, or `off`. |
| Log level | `ITS_LOG_LEVEL` | none | `info` in production. |
| Trusted proxies | `ITS_TRUSTED_PROXIES` | none | For correct client IPs. |

### Environment file rules

- `.env` is development-only; `.env.example` contains names and safe placeholders, never real secrets.
- Production secrets come from the platform’s secret manager or environment injection.
- Fail fast on missing required values: database, base URL, session secret, IMAP credentials (if email enabled), attachment root.
- Never derive the base URL from `gethostbyname`, `HTTP_HOST`, or the server IP.
- Never commit database dumps containing password hashes or personal data.

---

## Appendix D — Legacy query reference

Use these as behavioral references, not copy-paste code. Every value below is a bound parameter in the target.

### Open tickets (`get_all_open_tickets.php`)

```sql
SELECT tickets.id, UPPER(tickets.subject) AS subject, locations.name,
       CONCAT(users.fn,' ',users.ln) AS mail, categories.category,
       created_date, created_time,
       FROM_UNIXTIME(created_time, '%b %e, %l:%i %p') AS created_datetime,
       tickets.state_id, states.name AS state_name,
       CONCAT(u2.fn,' ',u2.ln) AS assigned
FROM tickets
INNER JOIN locations ON tickets.location_id = locations.id
LEFT JOIN users ON tickets.eu_uid = users.id
LEFT JOIN users u2 ON tickets.assigned_uid = u2.id AND u2.id <> 0
INNER JOIN categories ON tickets.category_id = categories.id
INNER JOIN states ON tickets.state_id = states.id
WHERE state_id = '1' AND categories.id NOT IN (23,24)
ORDER BY tickets.id DESC;
```

### Closed tickets (`get_all_closed_tickets.php`)

Same SELECT, but `WHERE tickets.state_id = '2' ORDER BY resolved_time DESC LIMIT 4000`. Uses `INNER JOIN users` on `eu_uid` in legacy; target uses `LEFT JOIN`.

### Hardware, announcements, closed requests

| View | Predicate | Order |
|---|---|---|
| Hardware | `state_id = 1 AND categories.id = 23` | `tickets.id DESC` |
| Announcements | `state_id = 1 AND categories.id = 24` | `tickets.id DESC` |
| Closed request | `state_id = 2 AND categories.id = 23` | `resolved_time DESC` |

### User-scoped lists

| View | Legacy predicate | Notes |
|---|---|---|
| Created by user | `(created_uid = :id OR resolved_uid = :id OR assigned_uid = :id)` AND `state_id != 0` | Includes assigned and resolved tickets despite the name (D62); no open-state filter. |
| Assigned to user | `assigned_uid = :id AND state_id = 1` | End-user variant uses the session ID when `p=end_user`. |
| Assigned to group | `assigned_gid = :session_group_id AND state_id = 1` | Conflates group and units (D53). |
| Unassigned | `assigned_uid = 0 AND state_id = 1` | |
| Closed by user | `resolved_uid = :id AND state_id = 2` | Target fix; legacy defaults to open and misses `id` (D52). |
| End-user list | `(created_uid = :id OR resolved_uid = :id)`; `q=all` removes the open filter | Uses session ID. |

### Search (`get_all_tickets.php`)

```sql
SELECT tickets.id, tickets.subject, locations.name, locations.id AS location_id,
       CONCAT(users.fn,' ',users.ln) AS mail, users.id AS eu_id,
       categories.category, created_date, created_time,
       tickets.state_id, states.name AS state_name,
       tickets.category_id AS category_id, tickets.assigned_gid AS group_id,
       tickets.assigned_uid AS assigned_uid
FROM tickets, locations, users, categories, states
WHERE tickets.location_id = locations.id AND tickets.eu_uid = users.id
  AND tickets.category_id = categories.id AND tickets.state_id = states.id
  /* filters appended as AND ... */
ORDER BY tickets.id DESC
/* LIMIT 500 only when p=search_tickets */
```

The getter reads `ticket_id`, `eu_id`, `location_id`, `category_id`, `subject` (LIKE), `issue` (LIKE), and `closed_by` (mapped to `tickets.resolved_uid =`). The form’s visible “ID” field is serialized as `ticket_id`, so all seven filters work. The target must keep all seven, parameterize them, and enforce the 500-row cap on search.

### Subordinates queue (`get_all_tickets_by_subordinates.php`)

```sql
SELECT tickets.id, tickets.subject, locations.name AS location_name,
       CONCAT(users.ln, ', ', users.fn) AS created_name, users.mail AS created_mail,
       CONCAT(attended_by.ln, ', ', attended_by.fn) AS attended_by_name,
       CONCAT(eu.ln, ', ', eu.fn) AS eu_name, categories.category,
       FROM_UNIXTIME(created_time, '%b %e, %l:%i %p') AS created_datetime,
       tickets.created_date, tickets.created_time, tickets.state_id,
       states.name AS state_name, tickets.approved_by_supervisor,
       CONCAT(supervisor.ln, ', ', supervisor.fn) AS supervisor_name
FROM tickets
INNER JOIN locations ON locations.id = tickets.location_id
INNER JOIN users ON users.id = tickets.created_uid
LEFT JOIN users AS supervisor ON supervisor.id = users.supervisor_id
INNER JOIN categories ON categories.id = tickets.category_id
INNER JOIN states ON states.id = tickets.state_id
INNER JOIN users AS attended_by ON attended_by.id = tickets.assigned_uid
LEFT JOIN users AS eu ON eu.id = tickets.eu_uid
WHERE tickets.created_uid IN (:subordinate_ids)
  AND tickets.created_uid = tickets.assigned_uid
  AND tickets.state_id = '2'
  /* AND tickets.created_time >= 1784692800 (hide_old=1) */
  /* AND (tickets.subject LIKE :q OR tickets.issue LIKE :q) */
  /* AND tickets.created_uid = :personnel_id (only if a direct subordinate) */
ORDER BY tickets.id DESC;
```

`hierarchy=1` walks the supervisor tree recursively (cycle-guarded by a `seen` list) and returns indirect reports; otherwise only direct reports are used. The target replaces `IN (:subordinate_ids)` with a bound array/CTE, keeps the explicit and indirect modes, and enforces authorization.

### Supervisor queue (`get_all_tickets_by_supervisors.php`)

Same shape, with `WHERE tickets.created_uid = :supervisor_id AND tickets.created_uid = tickets.assigned_uid AND tickets.state_id = '2'`, optional hide-old `created_time >= :cutoff`, and optional search.

### Pending-review badge

```sql
SELECT COUNT(*) AS cnt
FROM tickets
WHERE created_uid IN (:active_direct_subordinate_ids)
  AND created_uid = assigned_uid
  AND state_id = 2
  AND approved_by_supervisor = 0
  AND created_time >= 1784553600;
```

### Dashboard

```sql
-- Assigned to you
SELECT COUNT(*) FROM tickets WHERE assigned_uid = :user AND state_id = 1;

-- Assigned to group (legacy meaning)
SELECT COUNT(*) FROM tickets WHERE assigned_gid = :group AND state_id = 1;

-- Closed by you today
SELECT COUNT(*) FROM tickets
WHERE resolved_uid = :user AND state_id = 2 AND resolved_date = :midnight_today;

-- Closed by group today (legacy bug: last user wins)
SELECT COUNT(*) FROM tickets
WHERE resolved_uid = :group_member AND state_id = 2 AND resolved_date = :midnight_today;
-- Target: one query with resolved_uid IN (:group_members).

-- Average resolution time, you
SELECT AVG(resolved_time - created_time) FROM tickets
WHERE resolved_uid = :user AND state_id = 2 AND resolved_time > 0 AND created_time > 0;
-- Legacy has no > 0 guards; they are a target data-quality fix.

-- Average resolution time, system
SELECT AVG(resolved_time - created_time) FROM tickets
WHERE state_id = 2 AND resolved_time > 0 AND created_time > 0;
-- Same note: the legacy loop includes zero/backdated rows.
```

### Counter per-ticket (`counter/request.php`, `getByTickets`)

```sql
SELECT tickets.subject,
       CONCAT(us1.ln, ', ', us1.fn) AS created,
       CONCAT(us2.ln, ', ', us2.fn) AS assigned,
       CONCAT(us3.ln, ', ', us3.fn) AS resolved,
       CASE WHEN tickets.created_time > UNIX_TIMESTAMP('2025-12-19 15:00:00')
            THEN tickets.assigned_gid ELSE 1 END AS units_items_persons,
       REGEXP_REPLACE(tickets.issue, '<[^>]*>', '') AS issue,
       categories.category, loc.name AS department,
       FROM_UNIXTIME(tickets.created_time) AS created_datetime,
       FROM_UNIXTIME(tickets.resolved_time) AS resolved_datetime,
       FROM_UNIXTIME(tickets.created_time, '%m') AS created_month
FROM tickets
LEFT JOIN users AS us1 ON us1.id = created_uid AND us1.id IN (:employee_ids)
LEFT JOIN users AS us2 ON us2.id = tickets.assigned_uid AND us2.id IN (:employee_ids)
LEFT JOIN users AS us3 ON us3.id = tickets.resolved_uid AND us3.id IN (:employee_ids)
LEFT JOIN locations AS loc ON loc.id = tickets.location_id
INNER JOIN categories ON categories.id = tickets.category_id
WHERE (tickets.created_uid IN (:employee_ids)
    OR tickets.assigned_uid IN (:employee_ids)
    OR tickets.resolved_uid IN (:employee_ids))
  AND tickets.resolved_time BETWEEN UNIX_TIMESTAMP(:from)
                               AND UNIX_TIMESTAMP(CONCAT(:to, ' 11:59:59'))
  AND (tickets.category_id = :cat OR :cat = 0)
ORDER BY created_datetime ASC;
```

### Counter aggregates

```sql
-- getData: by resolver and category
SELECT resolved_uid, category_id, COUNT(*) AS count
FROM tickets
WHERE (resolved_uid IN (:employee_ids) OR :employee_empty = 1)
  AND resolved_time BETWEEN UNIX_TIMESTAMP(:from) AND UNIX_TIMESTAMP(:to)
  AND (category_id = :cat OR :cat = 0)
  AND approved_by_supervisor != 0
GROUP BY resolved_uid, category_id;

-- getDataTagged: by assignee and category
SELECT assigned_uid, category_id, COUNT(*) AS count
FROM tickets
WHERE (assigned_uid IN (:employee_ids) OR :employee_empty = 1)
  AND resolved_time BETWEEN UNIX_TIMESTAMP(:from) AND UNIX_TIMESTAMP(:to)
  AND (category_id = :cat OR :cat = 0)
  AND resolved_uid <> assigned_uid
  AND state_id = 2
  AND approved_by_supervisor != 0
GROUP BY assigned_uid, category_id;
```

### Root JSON report

```sql
SELECT tickets.id, categories.category, tickets.subject, tickets.issue,
       FROM_UNIXTIME(tickets.created_time, '%Y-%m-%d %H:%i:%s') AS created,
       FROM_UNIXTIME(tickets.resolved_time, '%Y-%m-%d %H:%i:%s') AS closed,
       SEC_TO_TIME(ABS(tickets.resolved_time - tickets.created_time)) AS `duration (minutes)`,
       CASE WHEN assigned_gid = 0 THEN 1 ELSE assigned_gid END AS no_of_units_items_persons
       /* , CONCAT(users.fn,' ',users.ln) AS end_user */
FROM tickets
INNER JOIN categories ON categories.id = tickets.category_id
/* LEFT JOIN users ON tickets.eu_uid = users.id */
WHERE FROM_UNIXTIME(tickets.created_time, '%Y-%m') = :month
  AND (tickets.state_id = 1 OR tickets.approved_by_supervisor != 0);
```

### Email insert

```sql
INSERT INTO tickets
  (subject, eu_uid, created_uid, created_date, created_time, location_id, state_id, category_id)
VALUES
  (:subject, :sender_id, :sender_id, :midnight_today, :now_clock, :sender_location, 1, 1);

INSERT INTO notifications (user_id, subject, data, dt, state)
VALUES (:sender_id, CONCAT('Ticket Created - ', :ticket_id),
        CONCAT('This is a confirmation email letting you know that your ticket was successfully created.<br /><br />Ticket ID: ', :ticket_id),
        :midnight_today, 1);

INSERT INTO attachements (ticket_id, file_name_org, file_name, storage_path, mime_type, size_bytes, sha256)
VALUES (:ticket_id, :original_name, 0, :storage_path, :mime, :size, :hash);
```

The target writes a meaningful `storage_path` and leaves the legacy `file_name` at `0` (or a documented numeric value) for new rows.

---

## Document control

- **Status:** behavioral contract and migration specification.
- **Authoritative sources, in order:** production `its` database and `mods` export; legacy PHP code in this repository; `config/sts.sql`; this document.
- **Conflict rule:** if this document disagrees with the production database or the live `mods` table, stop and report the conflict. Do not “fix” production data to match the document.
- **Change rule:** every behavioral change after approval requires a new defect ID or an update to an existing row in §37, plus an acceptance test and a deviation-log entry.
- **Non-goal:** pixel-perfect reproduction. The goal is identical workflow outcomes, data semantics, permissions, audit history, and user recognition.
- **Reminder:** keep the misspelled `attachements` table, the numeric `created_date`/`created_time` encoding, the tri-state approval flag, and the protected IDs `users.id = 1` and `categories.id = 1`. Those are data, not bugs.

If you are an agent starting the rebuild: read §1–§12 first, then the feature section for the workflow you are implementing, then §37 for the deviations that apply. Do not implement from a single section in isolation.

