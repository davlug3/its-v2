<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const TZ = 'Asia/Manila';

    public function tickets(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $employeeIds = $request->query('employees', []);
        if (! is_array($employeeIds)) {
            $employeeIds = [$employeeIds];
        }
        $employeeIds = array_values(array_filter(array_map('intval', $employeeIds), fn (int $v): bool => $v > 0));

        $query = Ticket::query()
            ->whereBetween('created_date', [$from->timestamp, $to->timestamp]);

        if ($employeeIds !== []) {
            $query->whereIn('created_uid', $employeeIds);
        }

        $tickets = $query->orderBy('created_date')->get();

        // Resolve names in one batch from the PIS database.
        $ids = $tickets->pluck('created_uid')
            ->merge($tickets->pluck('assigned_uid'))
            ->merge($tickets->pluck('resolved_uid'))
            ->unique()
            ->filter()
            ->values()
            ->all();

        $names = [];
        if ($ids !== []) {
            $rows = DB::connection('payroll')
                ->table('payroll')
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'mname', 'lname']);

            foreach ($rows as $row) {
                $names[(int) $row->id] = trim($row->name.' '.$row->mname.' '.$row->lname);
            }
        }

        $categoryIds = $tickets->pluck('category_id')->unique()->filter()->values()->all();
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

        $filename = 'tickets_report_'.now()->format('Ymd_His').'.csv';

        $response = new StreamedResponse(function () use ($tickets, $names, $categories): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Ticket ID',
                'Subject',
                'State',
                'Created By',
                'Assigned To',
                'Resolved By',
                'Category',
                'Location ID',
                'Units',
                'Approval',
                'Created Date',
                'Resolved Date',
                'Resolution',
            ]);

            foreach ($tickets as $ticket) {
                fputcsv($handle, [
                    $ticket->id,
                    $ticket->subject,
                    $ticket->state_id === 1 ? 'Open' : ($ticket->state_id === 2 ? 'Closed' : 'State '.$ticket->state_id),
                    $names[(int) $ticket->created_uid] ?? $ticket->created_uid,
                    $ticket->assigned_uid ? ($names[(int) $ticket->assigned_uid] ?? $ticket->assigned_uid) : '',
                    $ticket->resolved_uid ? ($names[(int) $ticket->resolved_uid] ?? $ticket->resolved_uid) : '',
                    $categories[(int) $ticket->category_id] ?? $ticket->category_id,
                    $ticket->location_id,
                    $ticket->units_count,
                    match ((int) $ticket->approved_by_supervisor) {
                        1 => 'Approved',
                        2 => 'Disapproved',
                        default => 'Pending',
                    },
                    Carbon::createFromTimestamp((int) $ticket->created_date, self::TZ)->toDateTimeString(),
                    $ticket->resolved_date ? Carbon::createFromTimestamp((int) $ticket->resolved_date, self::TZ)->toDateTimeString() : '',
                    $ticket->resolution,
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    public function monthly(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->format('Y-m'));

        if (! preg_match('/^\d{4}-\d{2}$/', $date)) {
            return response()->json(['message' => 'Invalid date format. Use YYYY-MM.'], 422);
        }

        [$year, $month] = explode('-', $date);

        $from = Carbon::create((int) $year, (int) $month, 1, 0, 0, 0, self::TZ)->startOfDay();
        $to = $from->copy()->endOfMonth()->endOfDay();

        $rows = Ticket::query()
            ->whereBetween('created_date', [$from->timestamp, $to->timestamp])
            ->get(['state_id', 'category_id', 'approved_by_supervisor']);

        $total = $rows->count();
        $closed = $rows->where('state_id', 2)->count();
        $open = $rows->where('state_id', 1)->count();
        $approved = $rows->where('approved_by_supervisor', 1)->count();
        $disapproved = $rows->where('approved_by_supervisor', 2)->count();

        return response()->json([
            'date' => $date,
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'total' => $total,
            'open' => $open,
            'closed' => $closed,
            'approved' => $approved,
            'disapproved' => $disapproved,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $fromInput = (string) $request->query('from', '');
        $toInput = (string) $request->query('to', '');

        $from = $fromInput !== ''
            ? Carbon::parse($fromInput, self::TZ)->startOfDay()
            : Carbon::now(self::TZ)->startOfMonth()->startOfDay();

        $to = $toInput !== ''
            ? Carbon::parse($toInput, self::TZ)->endOfDay()
            : Carbon::now(self::TZ)->endOfMonth()->endOfDay();

        if ($from->greaterThan($to)) {
            $tmp = $from;
            $from = $to->copy()->startOfDay();
            $to = $tmp->copy()->endOfDay();
        }

        return [$from, $to];
    }
}
