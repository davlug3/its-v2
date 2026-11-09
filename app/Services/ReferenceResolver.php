<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class ReferenceResolver
{
    /**
     * Resolve payroll user display names for the given IDs.
     *
     * @param  array<int>  $ids
     * @return array<int, string>
     */
    public function userNames(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = DB::connection('payroll')
            ->table('payroll')
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'mname', 'lname']);

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row->id] = trim(
                preg_replace('/\s+/', ' ', (string) $row->name.' '.(string) ($row->mname ?? '').' '.(string) $row->lname) ?? '',
            );
        }

        return $names;
    }

    /**
     * Resolve category names.
     *
     * @param  array<int>  $ids
     * @return array<int, string>
     */
    public function categoryNames(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $ids)
            ->pluck('category', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
    }

    /**
     * Resolve location (department) names from jblmrhpayroll.
     *
     * @param  array<int>  $ids
     * @return array<int, string>
     */
    public function locationNames(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = DB::connection('payroll')
            ->table('department')
            ->whereIn('id', $ids)
            ->get(['id', 'department']);

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row->id] = (string) $row->department;
        }

        return $names;
    }

    /**
     * Enrich a single ticket array with resolved names.
     *
     * @param  array<string, mixed>  $ticket
     * @param  array<int, string>  $users
     * @param  array<int, string>  $categories
     * @param  array<int, string>  $locations
     * @return array<string, mixed>
     */
    public function enrichTicket(array $ticket, array $users, array $categories, array $locations): array
    {
        $ticket['created_name'] = $this->pick($users, (int) ($ticket['created_uid'] ?? 0));
        $ticket['assigned_name'] = $this->pick($users, (int) ($ticket['assigned_uid'] ?? 0));
        $ticket['resolved_name'] = $this->pick($users, (int) ($ticket['resolved_uid'] ?? 0));
        $ticket['eu_name'] = $this->pick($users, (int) ($ticket['eu_uid'] ?? 0));
        $ticket['category_name'] = $categories[(int) ($ticket['category_id'] ?? 0)] ?? null;
        $ticket['location_name'] = $locations[(int) ($ticket['location_id'] ?? 0)] ?? null;

        return $ticket;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tickets
     * @return array<int, array<string, mixed>>
     */
    public function enrichTickets(array $tickets): array
    {
        if ($tickets === []) {
            return [];
        }

        $userIds = [];
        $categoryIds = [];
        $locationIds = [];
        foreach ($tickets as $t) {
            foreach (['created_uid', 'assigned_uid', 'resolved_uid', 'eu_uid'] as $k) {
                $v = (int) ($t[$k] ?? 0);
                if ($v > 0) {
                    $userIds[] = $v;
                }
            }
            $c = (int) ($t['category_id'] ?? 0);
            if ($c > 0) {
                $categoryIds[] = $c;
            }
            $l = (int) ($t['location_id'] ?? 0);
            if ($l > 0) {
                $locationIds[] = $l;
            }
        }

        $users = $this->userNames($userIds);
        $categories = $this->categoryNames($categoryIds);
        $locations = $this->locationNames($locationIds);

        return array_map(fn (array $t) => $this->enrichTicket($t, $users, $categories, $locations), $tickets);
    }

    /**
     * @param  array<int, string>  $users
     */
    private function pick(array $users, int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        return $users[$id] ?? null;
    }

    /**
     * @param  array<int|string|null>  $ids
     * @return array<int>
     */
    private function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $v = (int) $id;
            if ($v > 0) {
                $out[$v] = $v;
            }
        }

        return array_values($out);
    }
}
