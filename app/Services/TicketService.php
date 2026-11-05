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
}
