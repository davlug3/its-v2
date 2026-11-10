<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketLog;
use Illuminate\Support\Facades\DB;

/**
 * Read-side ticket queries.
 *
 * Owns all ticket listing, aggregation, and log retrieval. Mutations live in
 * {@see TicketMutationService}; this service is read-only by construction.
 */
class TicketQueryService
{
    public function __construct(
        private ReferenceResolver $resolver,
    ) {}

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
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
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

        $isSet = static fn (array $f, string $k): bool => isset($f[$k]) && $f[$k] !== '' && strcasecmp((string) $f[$k], 'null') !== 0 && (string) $f[$k] !== '0';

        if ($isSet($filters, 'eu_uid')) {
            $query->where('eu_uid', (int) $filters['eu_uid']);
        }

        if ($isSet($filters, 'resolved_uid')) {
            $query->where('resolved_uid', (int) $filters['resolved_uid']);
        }

        if ($isSet($filters, 'location_id')) {
            $query->where('location_id', (int) $filters['location_id']);
        }

        $searchMode = false;

        if (isset($filters['ticket_id']) && $filters['ticket_id'] !== '' && strcasecmp((string) $filters['ticket_id'], 'null') !== 0) {
            $query->where('id', (int) $filters['ticket_id']);
            $searchMode = true;
        }

        $subject = trim((string) ($filters['subject'] ?? $filters['search'] ?? ''));
        if ($subject !== '' && strcasecmp($subject, 'null') !== 0) {
            $query->where('subject', 'like', '%'.$subject.'%');
            $searchMode = true;
        }

        $issue = trim((string) ($filters['issue'] ?? ''));
        if ($issue !== '' && strcasecmp($issue, 'null') !== 0) {
            $query->where('issue', 'like', '%'.$issue.'%');
            $searchMode = true;
        }

        if ($isSet($filters, 'eu_uid') || $isSet($filters, 'resolved_uid')) {
            $searchMode = true;
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

        if ($searchMode) {
            $perPage = min($perPage, 500);
            $page = 1;
            $total = min($total, 500);
        }

        $tickets = $query
            ->orderBy($sortColumn, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * List the audit/comment log for a ticket.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Aggregate summary for tickets involving the given user.
     *
     * @return array{open: int, closed: int, total: int, recent: array<int, array<string, mixed>>}
     */
    /**
     * Tickets closed/resolved by the given user (D52).
     *
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listClosedByUser(int $userId, int $page = 1, int $perPage = 20, ?string $sort = null, string $dir = 'desc'): array
    {
        $query = Ticket::query()
            ->where('state_id', 2)
            ->where('resolved_uid', $userId);

        $total = (int) $query->count();

        $sortMap = [
            'id' => 'id',
            'subject' => 'subject',
            'resolved' => 'resolved_date',
            'created' => 'created_date',
        ];
        $sortColumn = $sortMap[$sort ?? ''] ?? 'resolved_date';
        $direction = $dir === 'asc' ? 'asc' : 'desc';

        $tickets = $query
            ->orderBy($sortColumn, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Open tickets assigned to the given user.
     *
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listAssignedToUser(int $userId, int $page = 1, int $perPage = 20, ?string $sort = null, string $dir = 'desc'): array
    {
        $query = Ticket::query()
            ->where('state_id', 1)
            ->where('assigned_uid', $userId);

        $total = (int) $query->count();

        $sortMap = [
            'id' => 'id',
            'subject' => 'subject',
            'created' => 'created_date',
            'category' => 'category_id',
        ];
        $sortColumn = $sortMap[$sort ?? ''] ?? 'id';
        $direction = $dir === 'asc' ? 'asc' : 'desc';
        if ($sort === null || $sort === '') {
            $direction = 'desc';
        }

        $tickets = $query
            ->orderBy($sortColumn, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Open unassigned tickets (staff view).
     *
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listUnassigned(int $page = 1, int $perPage = 20, ?string $sort = null, string $dir = 'desc'): array
    {
        $query = Ticket::query()
            ->where('state_id', 1)
            ->where('assigned_uid', 0);

        $total = (int) $query->count();

        $sortMap = [
            'id' => 'id',
            'subject' => 'subject',
            'created' => 'created_date',
            'category' => 'category_id',
        ];
        $sortColumn = $sortMap[$sort ?? ''] ?? 'id';
        $direction = $dir === 'asc' ? 'asc' : 'desc';
        if ($sort === null || $sort === '') {
            $direction = 'desc';
        }

        $tickets = $query
            ->orderBy($sortColumn, $direction)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Tickets involving the user (legacy "created by user" getter semantics; D62 open).
     * created_uid OR resolved_uid OR assigned_uid = user, all states.
     *
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listInvolvingUser(int $userId, int $page = 1, int $perPage = 20, ?string $sort = null, string $dir = 'desc'): array
    {
        $query = Ticket::query()->where(function ($q) use ($userId): void {
            $q->where('created_uid', $userId)
                ->orWhere('resolved_uid', $userId)
                ->orWhere('assigned_uid', $userId);
        });

        $total = (int) $query->count();
        $sortMap = ['id' => 'id', 'subject' => 'subject', 'created' => 'created_date', 'state' => 'state_id'];
        $sortColumn = $sortMap[$sort ?? ''] ?? 'id';
        $direction = $dir === 'asc' ? 'asc' : 'desc';
        if ($sort === null || $sort === '') {
            $direction = 'desc';
        }

        $tickets = $query->orderBy($sortColumn, $direction)->skip(($page - 1) * $perPage)->take($perPage)->get();

        return [
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * End-user ticket view: created by or resolved by current user.
     *
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listEndUserTickets(int $userId, ?int $stateId, int $page = 1, int $perPage = 20): array
    {
        $query = Ticket::query()->where(function ($q) use ($userId): void {
            $q->where('created_uid', $userId)->orWhere('resolved_uid', $userId);
        });
        if ($stateId !== null) {
            $query->where('state_id', $stateId);
        }
        $total = (int) $query->count();
        $tickets = $query->orderByDesc('id')->skip(($page - 1) * $perPage)->take($perPage)->get();

        return [
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Tickets by supervisor: self-assigned closed tickets created by the actor's supervisor.
     *
     * @return array{tickets: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listBySupervisor(int $supervisorId, int $page = 1, int $perPage = 20): array
    {
        if ($supervisorId <= 0) {
            return ['tickets' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $query = Ticket::query()
            ->where('created_uid', $supervisorId)
            ->whereColumn('created_uid', 'assigned_uid')
            ->where('state_id', 2);

        $total = (int) $query->count();
        $tickets = $query->orderByDesc('id')->skip(($page - 1) * $perPage)->take($perPage)->get();

        return [
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Dashboard card metrics (D46–D49). Uses aggregate SQL only.
     *
     * @return array<string, int|float|null|array<int, array<string, mixed>>>
     */
    public function dashboardForUser(int $userId, int $groupId = 0, int $acl = 1, int $departmentId = 0): array
    {
        // Regular users only see tickets by their own department and by them.
        if ($acl === 1) {
            return $this->dashboardForRegularUser($userId, $departmentId);
        }

        return $this->dashboardForStaff($userId, $groupId);
    }

    /**
     * Staff dashboard metrics (existing behavior).
     *
     * @return array<string, int|float|null|array<int, array<string, mixed>>>
     */
    private function dashboardForStaff(int $userId, int $groupId = 0): array
    {
        $tz = config('app.timezone', 'UTC');
        $startOfToday = (int) now($tz)->startOfDay()->timestamp;
        $endOfToday = (int) now($tz)->endOfDay()->timestamp;
        $startOfYear = (int) now($tz)->startOfYear()->timestamp;

        $assignedToYou = (int) Ticket::query()
            ->where('assigned_uid', $userId)
            ->where('state_id', 1)
            ->count();

        $assignedToGroup = 0;
        if ($groupId > 0) {
            $assignedToGroup = (int) Ticket::query()
                ->where('assigned_gid', $groupId)
                ->where('state_id', 1)
                ->count();
        }

        $closedByYouToday = (int) Ticket::query()
            ->where('resolved_uid', $userId)
            ->where('state_id', 2)
            ->whereBetween('resolved_date', [$startOfToday, $endOfToday])
            ->count();

        $closedByYouYtd = (int) Ticket::query()
            ->where('resolved_uid', $userId)
            ->where('state_id', 2)
            ->whereBetween('resolved_date', [$startOfYear, $endOfToday])
            ->count();

        $closedByGroupToday = 0;
        if ($groupId > 0) {
            $memberIds = DB::table('user_profile')
                ->where('group_id', $groupId)
                ->pluck('user_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            if ($memberIds !== []) {
                $closedByGroupToday = (int) Ticket::query()
                    ->whereIn('resolved_uid', $memberIds)
                    ->where('state_id', 2)
                    ->whereBetween('resolved_date', [$startOfToday, $endOfToday])
                    ->count();
            }
        }

        $yourAvg = Ticket::query()
            ->where('resolved_uid', $userId)
            ->where('state_id', 2)
            ->where('resolved_time', '>', 0)
            ->where('created_time', '>', 0)
            ->selectRaw('AVG(resolved_time - created_time) as avg_secs')
            ->value('avg_secs');

        $systemAvg = Ticket::query()
            ->where('state_id', 2)
            ->where('resolved_time', '>', 0)
            ->where('created_time', '>', 0)
            ->selectRaw('AVG(resolved_time - created_time) as avg_secs')
            ->value('avg_secs');

        $allOpenSystem = (int) Ticket::query()->where('state_id', 1)->count();

        $recent = Ticket::query()
            ->where(function ($q) use ($userId): void {
                $q->where('created_uid', $userId)
                    ->orWhere('assigned_uid', $userId)
                    ->orWhere('eu_uid', $userId);
            })
            ->orderByDesc('created_date')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'assigned_to_you' => $assignedToYou,
            'assigned_to_group' => $assignedToGroup,
            'closed_by_you_today' => $closedByYouToday,
            'closed_by_you_ytd' => $closedByYouYtd,
            'closed_by_group_today' => $closedByGroupToday,
            'your_avg_resolution_seconds' => $yourAvg !== null ? (float) $yourAvg : null,
            'system_avg_resolution_seconds' => $systemAvg !== null ? (float) $systemAvg : null,
            'all_open_system' => $allOpenSystem,
            'recent' => $recent,
            'group_id' => $groupId,
        ];
    }

    /**
     * Regular-user dashboard: tickets by the user's department and by the user.
     *
     * @return array<string, int|string|null|array{total: int, open: int, closed: int}|array<int, array<string, mixed>>>
     */
    private function dashboardForRegularUser(int $userId, int $departmentId): array
    {
        $departmentName = null;
        if ($departmentId > 0) {
            $row = DB::connection('payroll')
                ->table('department')
                ->where('id', $departmentId)
                ->value('department');
            $departmentName = $row !== null ? (string) $row : null;
        }

        $byDepartment = $this->ticketCountsByLocation($departmentId);
        $byMe = $this->ticketCountsByUserInvolvement($userId);

        $recent = Ticket::query()
            ->where(function ($q) use ($userId): void {
                $q->where('created_uid', $userId)
                    ->orWhere('assigned_uid', $userId)
                    ->orWhere('resolved_uid', $userId)
                    ->orWhere('eu_uid', $userId);
            })
            ->orderByDesc('created_date')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'by_department' => $byDepartment,
            'by_me' => $byMe,
            'recent' => $recent,
            'group_id' => 0,
        ];
    }

    /**
     * @return array{total: int, open: int, closed: int}
     */
    private function ticketCountsByLocation(int $locationId): array
    {
        if ($locationId <= 0) {
            return ['total' => 0, 'open' => 0, 'closed' => 0];
        }

        return [
            'total' => (int) Ticket::query()->where('location_id', $locationId)->count(),
            'open' => (int) Ticket::query()->where('location_id', $locationId)->where('state_id', 1)->count(),
            'closed' => (int) Ticket::query()->where('location_id', $locationId)->where('state_id', 2)->count(),
        ];
    }

    /**
     * @return array{total: int, open: int, closed: int}
     */
    private function ticketCountsByUserInvolvement(int $userId): array
    {
        $base = Ticket::query()->where(function ($q) use ($userId): void {
            $q->where('created_uid', $userId)
                ->orWhere('assigned_uid', $userId)
                ->orWhere('resolved_uid', $userId)
                ->orWhere('eu_uid', $userId);
        });

        return [
            'total' => (int) $base->count(),
            'open' => (int) clone $base->where('state_id', 1)->count(),
            'closed' => (int) clone $base->where('state_id', 2)->count(),
        ];
    }

    /**
     * @deprecated Use dashboardForUser
     *
     * @return array{open: int, closed: int, total: int, recent: array<int, array<string, mixed>>}
     */
    public function summaryForUser(int $userId): array
    {
        $d = $this->dashboardForUser($userId, 0);
        $recent = $d['recent'] ?? [];

        return [
            'open' => (int) ($d['assigned_to_you'] ?? 0),
            'closed' => (int) ($d['closed_by_you_ytd'] ?? 0),
            'total' => 0,
            'recent' => is_array($recent) ? $recent : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function logs(int $ticketId): array
    {
        $logs = TicketLog::where('ticket_id', $ticketId)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->toArray();

        $userIds = array_values(array_unique(array_filter(array_map(
            fn (array $l) => (int) ($l['user_id'] ?? 0),
            $logs,
        ))));
        $names = $this->resolver->userNames($userIds);

        return array_map(function (array $l) use ($names): array {
            $uid = (int) ($l['user_id'] ?? 0);
            $l['user_name'] = $uid > 0 ? ($names[$uid] ?? null) : null;

            return $l;
        }, $logs);
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
            'tickets' => $this->resolver->enrichTickets($tickets->toArray()),
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
}
