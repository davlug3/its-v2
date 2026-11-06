<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketLog;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Create a ticket.
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, ticket_id: int|null, message: string}
     */
    public function create(array $data, int $actorId, bool $isStaff): array
    {
        $subject = trim((string) ($data['subject'] ?? ''));
        $issue = trim((string) ($data['issue'] ?? ''));

        if ($subject === '' || $issue === '') {
            return ['ok' => false, 'ticket_id' => null, 'message' => 'Subject and issue are required.'];
        }

        if (mb_strlen($subject) > 255) {
            $subject = mb_substr($subject, 0, 255);
        }

        if (mb_strlen($issue) > 4000) {
            $issue = mb_substr($issue, 0, 4000);
        }

        $categoryId = (int) ($data['category_id'] ?? 1);
        $locationId = (int) ($data['location_id'] ?? 0);

        $now = time();

        $ticket = DB::transaction(function () use ($subject, $issue, $categoryId, $locationId, $actorId, $isStaff, $now, $data) {
            $ticket = new Ticket;
            $ticket->subject = $subject;
            $ticket->issue = $issue;
            $ticket->preview = mb_substr($issue, 0, 200);
            $ticket->created_uid = $actorId;
            $ticket->created_date = $now;
            $ticket->created_time = $now;
            $ticket->category_id = $categoryId;
            $ticket->location_id = $locationId;
            $ticket->place = (string) ($data['place'] ?? '');
            $ticket->contact = (string) ($data['contact'] ?? '');
            $ticket->assigned_gid = 0;
            $ticket->assigned_uid = 0;
            $ticket->state_id = 1;
            $ticket->eu_uid = $actorId;
            $ticket->approved_by_supervisor = 0;
            $ticket->source = $isStaff ? 'staff' : 'enduser';
            $ticket->units_count = (int) ($data['units_count'] ?? 0);
            $ticket->save();

            $log = new TicketLog;
            $log->ticket_id = $ticket->id;
            $log->user_id = $actorId;
            $log->data = 'Ticket created.';
            $log->date = $now;
            $log->time = $now;
            $log->action = 'create';
            $log->meta = ['source' => $ticket->source];
            $log->save();

            return $ticket;
        });

        $this->notifications->notify(
            (int) $ticket->eu_uid,
            'Ticket Created - '.$ticket->id,
            'This is a confirmation email letting you know that your ticket was successfully created.<br /><br />Ticket ID: '.$ticket->id,
        );

        return ['ok' => true, 'ticket_id' => $ticket->id, 'message' => 'Ticket created.'];
    }

    /**
     * Edit a ticket.
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string}
     */
    public function edit(int $ticketId, array $data, int $actorId): array
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket === null) {
            return ['ok' => false, 'message' => 'Ticket not found.'];
        }

        // Only the creator or an admin can edit.
        // Authorization is enforced by the controller/policy.

        $subject = isset($data['subject']) ? trim((string) $data['subject']) : $ticket->subject;
        $issue = isset($data['issue']) ? trim((string) $data['issue']) : $ticket->issue;

        if ($subject === '' || $issue === '') {
            return ['ok' => false, 'message' => 'Subject and issue are required.'];
        }

        if (mb_strlen($subject) > 255) {
            $subject = mb_substr($subject, 0, 255);
        }

        if (mb_strlen($issue) > 4000) {
            $issue = mb_substr($issue, 0, 4000);
        }

        $changed = [];
        $now = time();

        DB::transaction(function () use ($ticket, $subject, $issue, $data, $actorId, $now, &$changed) {
            if ($ticket->subject !== $subject) {
                $changed[] = 'subject';
                $ticket->subject = $subject;
            }

            if ($ticket->issue !== $issue) {
                $changed[] = 'issue';
                $ticket->issue = $issue;
                $ticket->preview = mb_substr($issue, 0, 200);
            }

            if (isset($data['category_id']) && (int) $data['category_id'] !== (int) $ticket->category_id) {
                $changed[] = 'category';
                $ticket->category_id = (int) $data['category_id'];
            }

            if (isset($data['location_id']) && (int) $data['location_id'] !== (int) $ticket->location_id) {
                $changed[] = 'location';
                $ticket->location_id = (int) $data['location_id'];
            }

            if (isset($data['place'])) {
                $place = (string) $data['place'];
                if ($ticket->place !== $place) {
                    $changed[] = 'place';
                    $ticket->place = $place;
                }
            }

            if (isset($data['contact'])) {
                $contact = (string) $data['contact'];
                if ($ticket->contact !== $contact) {
                    $changed[] = 'contact';
                    $ticket->contact = $contact;
                }
            }

            if (isset($data['units_count'])) {
                $units = (int) $data['units_count'];
                if ($ticket->units_count !== $units) {
                    $changed[] = 'units';
                    $ticket->units_count = $units;
                }
            }

            if (count($changed) > 0) {
                $ticket->updated_at = now();
                $ticket->save();

                $log = new TicketLog;
                $log->ticket_id = $ticket->id;
                $log->user_id = $actorId;
                $log->data = 'Ticket edited: '.implode(', ', $changed).'.';
                $log->date = $now;
                $log->time = $now;
                $log->action = 'edit';
                $log->meta = ['changed' => $changed];
                $log->save();
            }
        });

        if (count($changed) === 0) {
            return ['ok' => true, 'message' => 'No changes.'];
        }

        return ['ok' => true, 'message' => 'Ticket updated.'];
    }

    /**
     * List tickets involving the given user.
     *
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listForUser(int $userId, int $page = 1, int $perPage = 20, ?int $stateId = null, ?string $sort = null, string $dir = 'desc'): array
    {
        $query = Ticket::query()->where(function ($q) use ($userId): void {
            $q->where('created_uid', $userId)
                ->orWhere('assigned_uid', $userId)
                ->orWhere('eu_uid', $userId);
        });

        if ($stateId !== null) {
            $query->where('state_id', $stateId);
        }

        $total = (int) $query->count();

        $sortMap = [
            'id' => 'id',
            'subject' => 'subject',
            'state' => 'state_id',
            'category' => 'category_id',
            'created' => 'created_date',
            'assigned' => 'assigned_uid',
            'creator' => 'created_uid',
        ];

        $sortColumn = $sortMap[$sort ?? ''] ?? 'created_date';
        $direction = $dir === 'asc' ? 'asc' : 'desc';

        $tickets = $query
            ->orderBy($sortColumn, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'tickets' => $tickets->toArray(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * List all tickets (staff view) with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listAll(int $page = 1, int $perPage = 20, array $filters = [], ?string $sort = null, string $dir = 'desc'): array
    {
        $query = Ticket::query();

        if (isset($filters['state_id']) && $filters['state_id'] !== '') {
            $query->where('state_id', (int) $filters['state_id']);
        }

        if (isset($filters['category_id']) && $filters['category_id'] !== '') {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (isset($filters['assigned_uid']) && $filters['assigned_uid'] !== '') {
            $query->where('assigned_uid', (int) $filters['assigned_uid']);
        }

        if (isset($filters['created_uid']) && $filters['created_uid'] !== '') {
            $query->where('created_uid', (int) $filters['created_uid']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where('subject', 'like', $term);
        }

        $total = (int) $query->count();

        $sortMap = [
            'id' => 'id',
            'subject' => 'subject',
            'state' => 'state_id',
            'category' => 'category_id',
            'created' => 'created_date',
            'assigned' => 'assigned_uid',
            'creator' => 'created_uid',
        ];

        $sortColumn = $sortMap[$sort ?? ''] ?? 'created_date';
        $direction = $dir === 'asc' ? 'asc' : 'desc';

        $tickets = $query
            ->orderBy($sortColumn, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'tickets' => $tickets->toArray(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Assign a ticket to a user/group.
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string}
     */
    public function assign(int $ticketId, array $data, int $actorId, bool $canAssign): array
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket === null) {
            return ['ok' => false, 'message' => 'Ticket not found.'];
        }

        if (! $canAssign) {
            return ['ok' => false, 'message' => 'You do not have permission to assign this ticket.'];
        }

        $now = time();

        DB::transaction(function () use ($ticket, $data, $actorId, $now): void {
            if (array_key_exists('assigned_uid', $data)) {
                $ticket->assigned_uid = (int) $data['assigned_uid'];
                $ticket->assigned_date = $now;
                $ticket->assigned_time = $now;
            }

            if (array_key_exists('assigned_gid', $data)) {
                $ticket->assigned_gid = (int) $data['assigned_gid'];
            }

            $ticket->updated_at = now();
            $ticket->save();

            $log = new TicketLog;
            $log->ticket_id = $ticket->id;
            $log->user_id = $actorId;
            $log->data = 'Ticket assigned.';
            $log->date = $now;
            $log->time = $now;
            $log->action = 'assign';
            $log->meta = [
                'assigned_uid' => $ticket->assigned_uid,
                'assigned_gid' => $ticket->assigned_gid,
            ];
            $log->save();
        });

        if ((int) $ticket->assigned_uid > 0) {
            $this->notifications->notify(
                (int) $ticket->assigned_uid,
                'Ticket Assigned - '.$ticket->id,
                'You have been assigned ticket #'.$ticket->id.'.',
            );
        }

        return ['ok' => true, 'message' => 'Ticket assigned.'];
    }

    /**
     * List the audit/comment log for a ticket.
     *
     * @return array<int, array<string, mixed>>
     */
    public function logs(int $ticketId): array
    {
        return TicketLog::where('ticket_id', $ticketId)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    /**
     * Add a comment to a ticket.
     *
     * @return array{ok: bool, message: string}
     */
    public function comment(int $ticketId, string $text, int $actorId, bool $canView): array
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket === null) {
            return ['ok' => false, 'message' => 'Ticket not found.'];
        }

        if (! $canView) {
            return ['ok' => false, 'message' => 'You do not have permission to comment on this ticket.'];
        }

        $text = trim($text);

        if ($text === '') {
            return ['ok' => false, 'message' => 'Comment cannot be empty.'];
        }

        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 4000);
        }

        $now = time();

        $log = new TicketLog;
        $log->ticket_id = $ticketId;
        $log->user_id = $actorId;
        $log->data = $text;
        $log->date = $now;
        $log->time = $now;
        $log->action = 'comment';
        $log->meta = null;
        $log->save();

        return ['ok' => true, 'message' => 'Comment added.'];
    }

    /**
     * Approve a ticket.
     *
     * @return array{ok: bool, message: string}
     */
    public function approve(int $ticketId, string $remarks, int $actorId, int $actorAcl, bool $isSupervisor): array
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket === null) {
            return ['ok' => false, 'message' => 'Ticket not found.'];
        }

        if (! $isSupervisor) {
            return ['ok' => false, 'message' => 'You are not authorized to approve this ticket.'];
        }

        if ((int) $ticket->state_id !== 2) {
            return ['ok' => false, 'message' => 'Ticket must be closed before approval.'];
        }

        if ((int) $ticket->created_uid !== (int) $ticket->assigned_uid) {
            return ['ok' => false, 'message' => 'Only self-assigned tickets can be approved.'];
        }

        if ($actorAcl < 9 && (int) $ticket->created_uid === $actorId) {
            return ['ok' => false, 'message' => 'You cannot approve your own ticket.'];
        }

        if ((int) $ticket->approved_by_supervisor === 1) {
            return ['ok' => true, 'message' => 'Ticket already approved.'];
        }

        $remarks = trim($remarks);
        if (mb_strlen($remarks) > 2000) {
            $remarks = mb_substr($remarks, 0, 2000);
        }

        $now = time();

        DB::transaction(function () use ($ticket, $remarks, $actorId, $now): void {
            $ticket->approved_by_supervisor = 1;
            $ticket->updated_at = now();
            $ticket->save();

            $log = new TicketLog;
            $log->ticket_id = $ticket->id;
            $log->user_id = $actorId;
            $log->data = 'Supervisor approved ticket.'.($remarks !== '' ? ' Remarks: '.$remarks : '');
            $log->date = $now;
            $log->time = $now;
            $log->action = 'approve';
            $log->meta = ['approved_by_supervisor' => 1];
            $log->save();
        });

        $this->notifications->notify(
            (int) $ticket->created_uid,
            'Ticket Approved - '.$ticket->id,
            'Ticket #'.$ticket->id.' has been approved.',
        );

        return ['ok' => true, 'message' => 'Ticket approved.'];
    }

    /**
     * Disapprove a ticket.
     *
     * @return array{ok: bool, message: string}
     */
    public function disapprove(int $ticketId, string $remarks, int $actorId, int $actorAcl, bool $isSupervisor): array
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket === null) {
            return ['ok' => false, 'message' => 'Ticket not found.'];
        }

        if (! $isSupervisor) {
            return ['ok' => false, 'message' => 'You are not authorized to disapprove this ticket.'];
        }

        if ((int) $ticket->state_id !== 2) {
            return ['ok' => false, 'message' => 'Ticket must be closed before disapproval.'];
        }

        if ((int) $ticket->created_uid !== (int) $ticket->assigned_uid) {
            return ['ok' => false, 'message' => 'Only self-assigned tickets can be disapproved.'];
        }

        if ($actorAcl < 9 && (int) $ticket->created_uid === $actorId) {
            return ['ok' => false, 'message' => 'You cannot disapprove your own ticket.'];
        }

        $remarks = trim($remarks);

        if ($remarks === '') {
            return ['ok' => false, 'message' => 'Remarks are required for disapproval.'];
        }

        if (mb_strlen($remarks) > 2000) {
            $remarks = mb_substr($remarks, 0, 2000);
        }

        if ((int) $ticket->approved_by_supervisor === 2) {
            return ['ok' => true, 'message' => 'Ticket already disapproved.'];
        }

        $now = time();

        DB::transaction(function () use ($ticket, $remarks, $actorId, $now): void {
            $ticket->approved_by_supervisor = 2;
            $ticket->updated_at = now();
            $ticket->save();

            $log = new TicketLog;
            $log->ticket_id = $ticket->id;
            $log->user_id = $actorId;
            $log->data = 'Supervisor disapproved ticket. Remarks: '.$remarks;
            $log->date = $now;
            $log->time = $now;
            $log->action = 'disapprove';
            $log->meta = ['approved_by_supervisor' => 2];
            $log->save();
        });

        $this->notifications->notify(
            (int) $ticket->created_uid,
            'Ticket Disapproved - '.$ticket->id,
            'Ticket #'.$ticket->id.' has been disapproved.',
        );

        return ['ok' => true, 'message' => 'Ticket disapproved.'];
    }

    /**
     * Undo a prior approval.
     *
     * @return array{ok: bool, message: string}
     */
    public function undoApprove(int $ticketId, int $actorId, int $actorAcl, bool $isSupervisor): array
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket === null) {
            return ['ok' => false, 'message' => 'Ticket not found.'];
        }

        if (! $isSupervisor) {
            return ['ok' => false, 'message' => 'You are not authorized to undo this approval.'];
        }

        if ((int) $ticket->state_id !== 2) {
            return ['ok' => false, 'message' => 'Ticket must be closed.'];
        }

        if ((int) $ticket->created_uid !== (int) $ticket->assigned_uid) {
            return ['ok' => false, 'message' => 'Only self-assigned tickets can be undone.'];
        }

        if ($actorAcl < 9 && (int) $ticket->created_uid === $actorId) {
            return ['ok' => false, 'message' => 'You cannot undo your own ticket.'];
        }

        if ((int) $ticket->approved_by_supervisor !== 1) {
            return ['ok' => false, 'message' => 'Ticket is not currently approved.'];
        }

        $now = time();

        DB::transaction(function () use ($ticket, $actorId, $now): void {
            $ticket->approved_by_supervisor = 0;
            $ticket->updated_at = now();
            $ticket->save();

            $log = new TicketLog;
            $log->ticket_id = $ticket->id;
            $log->user_id = $actorId;
            $log->data = 'Supervisor undid approval.';
            $log->date = $now;
            $log->time = $now;
            $log->action = 'undo_approve';
            $log->meta = ['approved_by_supervisor' => 0];
            $log->save();
        });

        return ['ok' => true, 'message' => 'Approval undone.'];
    }

    /**
     * Batch approve.
     *
     * @param  array<int>  $ids
     * @param  array<int>  $allowedUserIds
     * @return array{success: int, message: string, errors: array<int|string, string>}
     */
    public function batchApprove(array $ids, string $remarks, int $actorId, int $actorAcl, array $allowedUserIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $v): bool => $v > 0)));

        if ($ids === []) {
            return ['success' => 0, 'message' => 'No tickets selected.', 'errors' => []];
        }

        if (count($ids) > 500) {
            return ['success' => 0, 'message' => 'Too many tickets selected.', 'errors' => []];
        }

        $remarks = trim($remarks);
        if (mb_strlen($remarks) > 2000) {
            $remarks = mb_substr($remarks, 0, 2000);
        }

        $tickets = Ticket::whereIn('id', $ids)->get()->keyBy('id');

        $errors = [];

        foreach ($ids as $id) {
            $ticket = $tickets->get($id);

            if ($ticket === null) {
                $errors[$id] = 'Ticket not found.';

                continue;
            }

            if ((int) $ticket->state_id !== 2) {
                $errors[$id] = 'Ticket must be closed.';

                continue;
            }

            if ((int) $ticket->created_uid !== (int) $ticket->assigned_uid) {
                $errors[$id] = 'Only self-assigned tickets can be approved.';

                continue;
            }

            if ($actorAcl < 9 && ! in_array((int) $ticket->created_uid, $allowedUserIds, true)) {
                $errors[$id] = 'Not your subordinate.';

                continue;
            }

            if ($actorAcl < 9 && (int) $ticket->created_uid === $actorId) {
                $errors[$id] = 'You cannot approve your own ticket.';

                continue;
            }
        }

        if ($errors !== []) {
            return ['success' => 0, 'message' => 'Some tickets could not be approved.', 'errors' => $errors];
        }

        $changed = 0;
        $now = time();

        DB::transaction(function () use ($ids, $tickets, $remarks, $actorId, $now, &$changed): void {
            foreach ($ids as $id) {
                $ticket = $tickets->get($id);

                if ((int) $ticket->approved_by_supervisor === 1) {
                    continue;
                }

                $ticket->approved_by_supervisor = 1;
                $ticket->updated_at = now();
                $ticket->save();
                $changed++;

                $log = new TicketLog;
                $log->ticket_id = $id;
                $log->user_id = $actorId;
                $log->data = 'Supervisor approved ticket.'.($remarks !== '' ? ' Remarks: '.$remarks : '');
                $log->date = $now;
                $log->time = $now;
                $log->action = 'approve';
                $log->meta = ['approved_by_supervisor' => 1];
                $log->save();
            }
        });

        return ['success' => $changed, 'message' => $changed.' ticket(s) approved.', 'errors' => []];
    }

    /**
     * Batch disapprove.
     *
     * @param  array<int>  $ids
     * @param  array<int>  $allowedUserIds
     * @return array{success: int, message: string, errors: array<int|string, string>}
     */
    public function batchDisapprove(array $ids, string $remarks, int $actorId, int $actorAcl, array $allowedUserIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $v): bool => $v > 0)));

        if ($ids === []) {
            return ['success' => 0, 'message' => 'No tickets selected.', 'errors' => []];
        }

        if (count($ids) > 500) {
            return ['success' => 0, 'message' => 'Too many tickets selected.', 'errors' => []];
        }

        $remarks = trim($remarks);

        if ($remarks === '') {
            return ['success' => 0, 'message' => 'Remarks are required for disapproval.', 'errors' => []];
        }

        if (mb_strlen($remarks) > 2000) {
            $remarks = mb_substr($remarks, 0, 2000);
        }

        $tickets = Ticket::whereIn('id', $ids)->get()->keyBy('id');

        $errors = [];

        foreach ($ids as $id) {
            $ticket = $tickets->get($id);

            if ($ticket === null) {
                $errors[$id] = 'Ticket not found.';

                continue;
            }

            if ((int) $ticket->state_id !== 2) {
                $errors[$id] = 'Ticket must be closed.';

                continue;
            }

            if ((int) $ticket->created_uid !== (int) $ticket->assigned_uid) {
                $errors[$id] = 'Only self-assigned tickets can be disapproved.';

                continue;
            }

            if ($actorAcl < 9 && ! in_array((int) $ticket->created_uid, $allowedUserIds, true)) {
                $errors[$id] = 'Not your subordinate.';

                continue;
            }

            if ($actorAcl < 9 && (int) $ticket->created_uid === $actorId) {
                $errors[$id] = 'You cannot disapprove your own ticket.';

                continue;
            }
        }

        if ($errors !== []) {
            return ['success' => 0, 'message' => 'Some tickets could not be disapproved.', 'errors' => $errors];
        }

        $changed = 0;
        $now = time();

        DB::transaction(function () use ($ids, $tickets, $remarks, $actorId, $now, &$changed): void {
            foreach ($ids as $id) {
                $ticket = $tickets->get($id);

                if ((int) $ticket->approved_by_supervisor === 2) {
                    continue;
                }

                $ticket->approved_by_supervisor = 2;
                $ticket->updated_at = now();
                $ticket->save();
                $changed++;

                $log = new TicketLog;
                $log->ticket_id = $id;
                $log->user_id = $actorId;
                $log->data = 'Supervisor disapproved ticket. Remarks: '.$remarks;
                $log->date = $now;
                $log->time = $now;
                $log->action = 'disapprove';
                $log->meta = ['approved_by_supervisor' => 2];
                $log->save();
            }
        });

        return ['success' => $changed, 'message' => $changed.' ticket(s) disapproved.', 'errors' => []];
    }

    /**
     * List tickets awaiting review by subordinates.
     *
     * @param  array<int>  $subordinateIds
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function bySubordinates(array $subordinateIds, bool $hideOld, int $page, int $perPage, int $hideCutoff): array
    {
        if ($subordinateIds === []) {
            return ['tickets' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $query = Ticket::query()
            ->whereIn('created_uid', $subordinateIds)
            ->whereColumn('created_uid', 'assigned_uid')
            ->where('state_id', 2)
            ->where('approved_by_supervisor', 0);

        if ($hideOld) {
            $query->where('created_date', '>=', $hideCutoff);
        }

        $total = (int) $query->count();

        $tickets = $query
            ->orderByDesc('created_date')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'tickets' => $tickets->toArray(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Count pending-review tickets for direct subordinates.
     *
     * @param  array<int>  $directSubordinateIds
     */
    public function pendingReviewCount(array $directSubordinateIds, int $badgeCutoff): int
    {
        if ($directSubordinateIds === []) {
            return 0;
        }

        return (int) Ticket::query()
            ->whereIn('created_uid', $directSubordinateIds)
            ->whereColumn('created_uid', 'assigned_uid')
            ->where('state_id', 2)
            ->where('approved_by_supervisor', 0)
            ->where('created_time', '>=', $badgeCutoff)
            ->count();
    }

    /**
     * Close a ticket.
     *
     * @return array{ok: bool, message: string}
     */
    public function close(int $ticketId, int $actorId, bool $canClose): array
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket === null) {
            return ['ok' => false, 'message' => 'Ticket not found.'];
        }

        if (! $canClose) {
            return ['ok' => false, 'message' => 'You do not have permission to close this ticket.'];
        }

        // Idempotent close.
        if ((int) $ticket->state_id === 2) {
            return ['ok' => true, 'message' => 'Ticket already closed.'];
        }

        $now = time();

        DB::transaction(function () use ($ticket, $actorId, $now) {
            $ticket->state_id = 2;
            $ticket->resolved_uid = $actorId;
            $ticket->resolved_date = $now;
            $ticket->resolved_time = $now;
            $ticket->updated_at = now();
            $ticket->save();

            $log = new TicketLog;
            $log->ticket_id = $ticket->id;
            $log->user_id = $actorId;
            $log->data = 'Ticket closed.';
            $log->date = $now;
            $log->time = $now;
            $log->action = 'close';
            $log->meta = ['state_id' => 2];
            $log->save();
        });

        $this->notifications->notify(
            (int) $ticket->created_uid,
            'Ticket Closed - '.$ticket->id,
            'Ticket #'.$ticket->id.' has been closed.',
        );

        return ['ok' => true, 'message' => 'Ticket closed.'];
    }
}
