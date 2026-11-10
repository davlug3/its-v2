<?php

namespace App\Http\Controllers;

use App\Models\Attachement;
use App\Models\Ticket;
use App\Models\TicketLog;
use App\Models\UserProfile;
use App\Services\ReferenceResolver;
use App\Services\SupervisorService;
use App\Services\TicketMutationService;
use App\Services\TicketQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function __construct(
        private TicketQueryService $queries,
        private TicketMutationService $mutations,
        private SupervisorService $supervisors,
        private ReferenceResolver $resolver,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = UserProfile::where('user_id', $user->id)->first();
        $groupId = $profile !== null ? (int) ($profile->group_id ?? 0) : 0;
        $acl = $profile !== null ? (int) ($profile->acl_id ?? 1) : 1;

        // Regular users are scoped to their own department (payroll.department).
        $departmentId = 0;
        $payrollDepartment = DB::connection('payroll')
            ->table('payroll')
            ->where('id', $user->id)
            ->value('department');
        if ($payrollDepartment !== null) {
            $departmentId = (int) $payrollDepartment;
        }

        return response()->json(
            $this->queries->dashboardForUser((int) $user->id, $groupId, $acl, $departmentId),
        );
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $scope = (string) $request->query('scope', 'mine');

        if ($scope === 'closed_by_me') {
            $result = $this->queries->listClosedByUser(
                (int) $user->id,
                $page,
                $perPage,
                (string) $request->query('sort', ''),
                (string) $request->query('dir', 'desc'),
            );

            return response()->json($result);
        }

        if ($scope === 'assigned_to_me') {
            $result = $this->queries->listAssignedToUser(
                (int) $user->id,
                $page,
                $perPage,
                (string) $request->query('sort', ''),
                (string) $request->query('dir', 'desc'),
            );

            return response()->json($result);
        }

        if ($scope === 'unassigned' && $this->aclOf($request) >= 2) {
            $result = $this->queries->listUnassigned(
                $page,
                $perPage,
                (string) $request->query('sort', ''),
                (string) $request->query('dir', 'desc'),
            );

            return response()->json($result);
        }

        if ($scope === 'involving_me') {
            $result = $this->queries->listInvolvingUser(
                (int) $user->id,
                $page,
                $perPage,
                (string) $request->query('sort', ''),
                (string) $request->query('dir', 'desc'),
            );

            return response()->json($result);
        }

        if ($scope === 'end_user_tickets') {
            $stateId = $request->query('state_id');
            $stateId = ($stateId === null || $stateId === '') ? null : (int) $stateId;
            $result = $this->queries->listEndUserTickets(
                (int) $user->id,
                $stateId,
                $page,
                $perPage,
            );

            return response()->json($result);
        }

        if ($scope === 'tickets_by_supervisor' && $this->aclOf($request) >= 3) {
            $profile = UserProfile::where('user_id', $user->id)->first();
            $supervisorId = $profile !== null ? (int) ($profile->supervisor_id ?? 0) : 0;
            $result = $this->queries->listBySupervisor($supervisorId, $page, $perPage);

            return response()->json($result);
        }

        if ($scope === 'all' && $this->aclOf($request) >= 2) {
            $filters = [
                'state_id' => $request->query('state_id'),
                'category_id' => $request->query('category_id'),
                'assigned_uid' => $request->query('assigned_uid'),
                'created_uid' => $request->query('created_uid'),
                'search' => $request->query('search'),
                'subject' => $request->query('subject'),
                'issue' => $request->query('issue'),
                'ticket_id' => $request->query('ticket_id', $request->query('id')),
                'eu_uid' => $request->query('eu_uid'),
                'location_id' => $request->query('location_id'),
                'resolved_uid' => $request->query('resolved_uid', $request->query('closed_by')),
            ];

            $result = $this->queries->listAll(
                $page,
                $perPage,
                $filters,
                (string) $request->query('sort', ''),
                (string) $request->query('dir', 'desc'),
            );

            return response()->json($result);
        }

        $stateId = $request->query('state_id');
        $stateId = $stateId === null ? null : (int) $stateId;

        $result = $this->queries->listForUser(
            (int) $user->id,
            $page,
            $perPage,
            $stateId,
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'desc'),
        );

        return response()->json($result);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        // Only helpdesk/admin can assign; end users cannot.
        $canAssign = $this->aclOf($request) >= 3;

        $result = $this->mutations->assign($id, $request->all(), (int) $user->id, $canAssign);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $result = $this->mutations->create(
            $request->all(),
            (int) $user->id,
            $this->aclOf($request) >= 2,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'ticket_id' => $result['ticket_id'],
        ], 201);
    }

    public function endUserStore(Request $request): JsonResponse
    {
        $user = $request->user();

        $result = $this->mutations->create(
            $request->all(),
            (int) $user->id,
            false,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'ticket_id' => $result['ticket_id'],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        // Only the creator may edit, and only while the ticket is untouched.
        if ($ticket->created_uid !== (int) $user->id) {
            return response()->json(['message' => 'Only the ticket creator may edit it.'], 403);
        }

        $touched = (int) $ticket->assigned_uid !== 0
            || (int) $ticket->resolved_uid !== 0
            || (int) $ticket->state_id !== 1;

        if ($touched) {
            return response()->json(['message' => 'This ticket can no longer be edited.'], 422);
        }

        $result = $this->mutations->edit($id, $request->all(), (int) $user->id);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Permission check: only helpdesk and above (ACL >= 2) may resolve a ticket.
        // Regular users (ACL 1) cannot close tickets, even ones they created or are assigned to.
        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $canClose = $this->aclOf($request) >= 2;

        $result = $this->mutations->close($id, (int) $user->id, $canClose);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $userId = (int) $request->user()->id;

        $canView = $this->aclOf($request) >= 3
            || $ticket->created_uid === $userId
            || $ticket->assigned_uid === $userId
            || $ticket->eu_uid === $userId;

        if (! $canView) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $ticket->toArray();
        $enriched = $this->resolver->enrichTickets([$data]);

        return response()->json($enriched[0] ?? $data);
    }

    public function logs(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $userId = (int) $request->user()->id;
        $canView = $this->aclOf($request) >= 3
            || $ticket->created_uid === $userId
            || $ticket->assigned_uid === $userId
            || $ticket->eu_uid === $userId;

        if (! $canView) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($this->queries->logs($id));
    }

    public function comment(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $userId = (int) $request->user()->id;
        $canView = $this->aclOf($request) >= 3
            || $ticket->created_uid === $userId
            || $ticket->assigned_uid === $userId
            || $ticket->eu_uid === $userId;

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
        ]);

        $result = $this->mutations->comment($id, (string) $validated['text'], $userId, $canView);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $isSup = $this->supervisors->isSupervisor($userId, (int) $ticket->created_uid, $acl);

        $result = $this->mutations->approve(
            $id,
            (string) $request->input('remarks', ''),
            $userId,
            $acl,
            $isSup,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function disapprove(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $isSup = $this->supervisors->isSupervisor($userId, (int) $ticket->created_uid, $acl);

        $result = $this->mutations->disapprove(
            $id,
            (string) $request->input('remarks', ''),
            $userId,
            $acl,
            $isSup,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function undoApprove(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $isSup = $this->supervisors->isSupervisor($userId, (int) $ticket->created_uid, $acl);

        $result = $this->mutations->undoApprove($id, $userId, $acl, $isSup);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function batchApprove(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $allowed = $acl >= 9 ? [] : $this->supervisors->allSubordinates($userId);

        $result = $this->mutations->batchApprove(
            $ids,
            (string) $request->input('remarks', ''),
            $userId,
            $acl,
            $allowed,
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'errors' => $result['errors'],
        ]);
    }

    public function batchDisapprove(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $allowed = $acl >= 9 ? [] : $this->supervisors->allSubordinates($userId);

        $result = $this->mutations->batchDisapprove(
            $ids,
            (string) $request->input('remarks', ''),
            $userId,
            $acl,
            $allowed,
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'errors' => $result['errors'],
        ]);
    }

    public function bySubordinates(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        if ($acl < 3) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $indirect = filter_var($request->query('indirect', false), FILTER_VALIDATE_BOOLEAN);
        $hideOld = filter_var($request->query('hide_old', true), FILTER_VALIDATE_BOOLEAN);

        $direct = $this->supervisors->directSubordinates($userId);
        $subordinates = $indirect
            ? $this->supervisors->allSubordinates($userId)
            : $direct;

        $hideCutoff = $this->cutoff('approval.hide_old_cutoff', 1784692800);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 40)));

        $result = $this->queries->bySubordinates(
            $subordinates,
            $hideOld,
            $page,
            $perPage,
            $hideCutoff,
        );

        return response()->json($result);
    }

    public function pendingReviewCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        if ($acl < 3) {
            return response()->json(['count' => 0]);
        }

        $direct = $this->supervisors->directSubordinates($userId);
        $badgeCutoff = $this->cutoff('approval.badge_cutoff', 1784553600);

        $count = $this->queries->pendingReviewCount($direct, $badgeCutoff);

        return response()->json(['count' => $count]);
    }

    private function cutoff(string $name, int $default): int
    {
        $row = DB::connection('mysql')
            ->table('application_settings')
            ->where('name', $name)
            ->first();

        if ($row === null) {
            return $default;
        }

        $value = (int) $row->setting;

        return $value > 0 ? $value : $default;
    }

    public function attachments(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (! $this->canViewTicket($request, $ticket)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $items = Attachement::where('ticket_id', $id)->orderBy('created_at')->get();

        return response()->json($items);
    }

    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (! $this->canViewTicket($request, $ticket)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $file = $validated['file'];

        // Content-based MIME guard (finfo). Reject when detected type is not allowed.
        $allowedMimes = [
            'application/pdf', 'image/jpeg', 'image/png', 'image/gif',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv', 'application/csv',
            'application/zip', 'application/x-zip-compressed',
        ];
        $detected = (string) ($file->getMimeType() ?? '');
        if ($detected !== '' && ! in_array($detected, $allowedMimes, true)) {
            return response()->json(['message' => 'File content type not allowed.'], 422);
        }

        $directory = 'attachments/'.$id;
        $storedName = (string) Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $storedName, 'local');

        $attachment = new Attachement;
        $attachment->file_name_org = $file->getClientOriginalName();
        $attachment->file_name = 0;
        $attachment->ticket_id = $id;
        $attachment->storage_path = $path;
        $attachment->mime_type = $detected !== '' ? $detected : (string) $file->getClientMimeType();
        $attachment->size_bytes = $file->getSize();
        $attachment->sha256 = hash_file('sha256', $file->getRealPath());
        $attachment->uploaded_by = (int) $request->user()->id;
        $attachment->created_at = now();
        $attachment->save();

        $now = time();
        $log = new TicketLog;
        $log->ticket_id = $id;
        $log->user_id = (int) $request->user()->id;
        $log->data = 'Attachment uploaded: '.$file->getClientOriginalName();
        $log->date = $now;
        $log->time = $now;
        $log->action = 'attachment';
        $log->meta = ['attachment_id' => $attachment->id];
        $log->save();

        return response()->json($attachment, 201);
    }

    public function downloadAttachment(Request $request, int $id, int $attachmentId): StreamedResponse|JsonResponse
    {
        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (! $this->canViewTicket($request, $ticket)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $attachment = Attachement::where('id', $attachmentId)->where('ticket_id', $id)->first();

        if ($attachment === null) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        if (! Storage::disk('local')->exists($attachment->storage_path)) {
            return response()->json(['message' => 'File missing on disk.'], 404);
        }

        return Storage::disk('local')->download($attachment->storage_path, $attachment->file_name_org, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteAttachment(Request $request, int $id, int $attachmentId): JsonResponse
    {
        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        if (! $this->canViewTicket($request, $ticket)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $attachment = Attachement::where('id', $attachmentId)->where('ticket_id', $id)->first();

        if ($attachment === null) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $userId = (int) $request->user()->id;
        $acl = $this->aclOf($request);

        if ($attachment->uploaded_by !== $userId && $acl < 3) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        Storage::disk('local')->delete($attachment->storage_path);
        $attachment->delete();

        $now = time();
        $log = new TicketLog;
        $log->ticket_id = $id;
        $log->user_id = $userId;
        $log->data = 'Attachment removed: '.$attachment->file_name_org;
        $log->date = $now;
        $log->time = $now;
        $log->action = 'attachment';
        $log->meta = ['attachment_id' => $attachmentId];
        $log->save();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    private function canViewTicket(Request $request, Ticket $ticket): bool
    {
        $userId = (int) $request->user()->id;

        return $this->aclOf($request) >= 3
            || $ticket->created_uid === $userId
            || $ticket->assigned_uid === $userId
            || $ticket->eu_uid === $userId;
    }

    public function locationOptions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(50, max(1, (int) $request->query('limit', 30)));

        $query = DB::connection('payroll')
            ->table('department')
            ->select(['id', 'department']);

        if ($q !== '') {
            $query->where('department', 'like', '%'.$q.'%');
        }

        $rows = $query->orderBy('department')->limit($limit)->get();

        return response()->json($rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'label' => (string) $r->department,
        ])->values());
    }

    public function monitoring(Request $request): JsonResponse
    {
        if ($this->aclOf($request) < 2) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tickets = Ticket::query()
            ->where('state_id', 1)
            ->whereNotIn('category_id', [23, 24])
            ->orderByDesc('created_date')
            ->limit(200)
            ->get();

        $userIds = $tickets->pluck('created_uid')
            ->merge($tickets->pluck('assigned_uid'))
            ->unique()
            ->filter()
            ->values()
            ->all();

        $locationIds = $tickets->pluck('location_id')->unique()->filter()->values()->all();
        $categoryIds = $tickets->pluck('category_id')->unique()->filter()->values()->all();

        $names = [];
        if ($userIds !== []) {
            $rows = DB::connection('payroll')
                ->table('payroll')
                ->whereIn('id', $userIds)
                ->get(['id', 'name', 'mname', 'lname']);
            foreach ($rows as $row) {
                $names[(int) $row->id] = trim($row->name.' '.$row->mname.' '.$row->lname);
            }
        }

        $locations = [];
        if ($locationIds !== []) {
            $rows = DB::connection('payroll')
                ->table('department')
                ->whereIn('id', $locationIds)
                ->get(['id', 'department']);
            foreach ($rows as $row) {
                $locations[(int) $row->id] = $row->department;
            }
        }

        $categories = [];
        if ($categoryIds !== []) {
            $rows = DB::connection('mysql')
                ->table('categories')
                ->whereIn('id', $categoryIds)
                ->get(['id', 'category']);
            foreach ($rows as $row) {
                $categories[(int) $row->id] = $row->category;
            }
        }

        $result = $tickets->map(function (Ticket $ticket) use ($names, $locations, $categories): array {
            $locId = (int) $ticket->location_id;
            $creatorId = (int) $ticket->created_uid;
            $catId = (int) $ticket->category_id;
            $assignedId = (int) $ticket->assigned_uid;

            return [
                'id' => $ticket->id,
                'created_date' => $ticket->created_date,
                'subject' => $ticket->subject,
                'location' => $locations[$locId] ?? ($locId > 0 ? 'Unknown location' : '—'),
                'mail' => $names[$creatorId] ?? ($creatorId > 0 ? 'Deleted user' : '—'),
                'category' => $categories[$catId] ?? ($catId > 0 ? 'Unknown category' : '—'),
                'assigned' => $assignedId > 0 ? ($names[$assignedId] ?? 'Deleted user') : '—',
            ];
        });

        return response()->json($result);
    }

    private function aclOf(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            return 1;
        }

        $profile = UserProfile::where('user_id', $user->id)->first();

        return $profile !== null ? (int) $profile->acl_id : 1;
    }
}
