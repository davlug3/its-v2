# Ticket Lifecycle Specification

**Status:** Approved
**Approved:** 2026-11-10
**Scope:** Canonical ticket creation, assignment, editing, closure, supervisor review, reopening, audit, notification, and concurrency rules.

This document is authoritative for target ticket workflow behavior. Historical database values retain their legacy meaning.

Related: [PROMPT.md](./PROMPT.md), [DECISIONS.md](./DECISIONS.md), [PLAN.md](./PLAN.md).

## 1. Core data semantics

| Field | Meaning |
|---|---|
| `created_uid` | User who created the ticket |
| `eu_uid` | End user the ticket is for |
| `assigned_uid` | Responsible staff member; `0` means unassigned |
| `assigned_gid` | Legacy group reference; preserve its meaning and historical values |
| `units_count` | Number of units/items/persons; independent of `assigned_gid` |
| `state_id` | `1` Open, `2` Closed |
| `resolved_uid` | Staff member who performed the current closure |
| `resolved_date`, `resolved_time` | Date/time of the current closure |
| `approved_by_supervisor` | `0` pending, `1` approved, `2` disapproved |

`assigned_gid` and `units_count` are used simultaneously for distinct purposes. Never store units in `assigned_gid`. New staff-created tickets use `assigned_gid = 0` because teams are retired. Units are read and written through `units_count`. The legacy `resolution` column remains unused; no additional resolution summary is required.

## 2. Ticket creation

### End-user creation
- End users create tickets only for themselves.
- New ticket is Open, category 1, unassigned (`assigned_uid = 0`), and approval pending.
- Creator and end user are the authenticated end user; location comes from their profile.
- Subject, issue, place, and contact are required.
- End users cannot assign or close tickets.
- No initial ticket-created log is required.

### Staff creation
- Staff may create a ticket for another end user.
- ACL 3 and ACL 9 creators are forced to self-assign.
- ACL 2 staff may assign another active staff member or leave the ticket unassigned.
- New ticket is Open and approval pending.
- `assigned_gid = 0`; `units_count` is independently populated when supplied.
- When an assignee is selected, append the established assignment log entry. Do not write a group/teams log.

## 3. Authorization
- End users (ACL 1) may view tickets only when they are the creator or end user.
- Staff access is based on creator, assignee, or elevated ACL permissions.
- ACL 3+ users have elevated ticket access as defined by the route policy.
- Group membership, `users.group_id`, and `assigned_gid` do not grant access.
- End users cannot edit ticket metadata, add staff notes, close, approve, disapprove, or reopen.
- Backend policies apply to every HTML, API, AJAX, and direct endpoint request. Hiding a UI control is not authorization.

## 4. Assignment and notifications
Every assignment change must be transactional with its audit event. Record ticket ID, previous assignee ID, new assignee ID, actor ID, and timestamp.
- Notify the new assignee when assigned.
- Notify the previous assignee when removed or replaced.
- Notifications are in-app and recipient-scoped. Never include credentials or secrets.

## 5. Editing
- Authorized staff may edit Open tickets.
- Authorized staff may edit Closed tickets while supervisor approval is pending (`approved_by_supervisor = 0`).
- Closed tickets that are Approved or Disapproved are locked.
- Subject and original issue are immutable after creation.
- Discussion notes are append-only.
- Units are stored in `units_count`, never `assigned_gid`.
- Every metadata change, assignment change, and note is recorded with actor and timestamp and shown in ticket history.
- Editing a closed pending-review ticket does not automatically change its approval state.

## 6. Closing
- Closure is staff-controlled. End users cannot close tickets, including tickets they created.
- Only an authorized staff member may close an Open ticket.
- Closing is idempotent. A repeated close does not overwrite the original resolver/timestamps or create duplicate closure events.
- Set `state_id = 2`, `resolved_uid`, `resolved_date`, and `resolved_time`.
- Append the established `Ticket Closed.` log and survey link.
- Preserve the existing auto-approval condition: if `assigned_uid != 0`, `assigned_uid != created_uid`, and approval is pending, set approval to Approved.
- Otherwise, self-created/self-assigned tickets remain pending supervisor review.
- Do not introduce a new requirement for the legacy `resolution` column.

## 7. Supervisor review
Review applies to Closed tickets where `created_uid = assigned_uid`.
- ACL 9 administrators and the creator's authorized direct/indirect supervisors may review.
- Pending may transition to Approved or Disapproved.
- Approved may transition to Pending (undo) or Disapproved.
- Disapproved may transition to Approved.
- Disapproval requires non-empty remarks; approval remarks are optional.
- Every successful transition is audited and appended to ticket history.
- Batch operations validate every selected ticket before mutation; one invalid item rejects the whole batch.

## 8. Reopening

### Permission and validation
- Only authorized staff may reopen a Closed ticket. End users cannot reopen.
- Reopening an already Open ticket is rejected.
- A reason is mandatory: trim whitespace, reject empty input, and cap at 2,000 characters.
- The reason is visible in ticket history and preserved in the structured audit record.

### Atomic transition
In one database transaction, lock the ticket row and:
1. Verify the ticket is Closed and the actor is authorized.
2. Set `state_id = 1`.
3. Set `approved_by_supervisor = 0`.
4. Clear current closure fields: `resolved_uid = 0`, `resolved_date = 0`, `resolved_time = 0`.
5. Append `Ticket Reopened. Reason: <reason>` to `tickets_log`.
6. Write an audit event containing ticket ID, actor ID, timestamp, and reason.
7. Create recipient-scoped notifications for the assigned staff member and relevant ticket participants.

Preserve every prior close, approval, disapproval, undo, and reopen event. Never delete or rewrite historical log rows. A subsequent closure creates a new current resolution and follows ordinary close/auto-approval rules.

## 9. Concurrency and transactions
- Close, reopen, approve, disapprove, undo, assignment changes, and their audit/log writes are transactional.
- Use `SELECT ... FOR UPDATE` or conditional updates that include the expected current state and approval value.
- A stale request fails cleanly; it cannot overwrite a newer transition.
- The ticket mutation and its audit/log event commit or roll back together.
- Batch approval/disapproval validates the complete selection before writing and commits atomically.

## 10. Required acceptance coverage
See [PLAN.md](./PLAN.md), AT-55 through AT-65. These tests are required before the ticket workflow phase is considered complete.
