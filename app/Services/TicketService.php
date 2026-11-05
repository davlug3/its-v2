<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketLog;
use Illuminate\Support\Facades\DB;

class TicketService
{
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
    public function listForUser(int $userId, int $page = 1, int $perPage = 20, ?int $stateId = null): array
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
        if ((int) $ticket->state_id === 3) {
            return ['ok' => true, 'message' => 'Ticket already closed.'];
        }

        $now = time();

        DB::transaction(function () use ($ticket, $actorId, $now) {
            $ticket->state_id = 3;
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
            $log->meta = ['state_id' => 3];
            $log->save();
        });

        return ['ok' => true, 'message' => 'Ticket closed.'];
    }
}
