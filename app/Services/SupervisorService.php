<?php

namespace App\Services;

use App\Models\UserProfile;

class SupervisorService
{
    /**
     * Direct reports of a user (users whose supervisor_id points at them).
     *
     * @return array<int>
     */
    public function directSubordinates(int $userId): array
    {
        return UserProfile::where('supervisor_id', $userId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Direct + indirect reports, cycle-safe.
     *
     * @return array<int>
     */
    public function allSubordinates(int $userId): array
    {
        $children = [];
        foreach (
            UserProfile::query()
                ->where('supervisor_id', '>', 0)
                ->get(['user_id', 'supervisor_id']) as $row
        ) {
            $children[(int) $row->supervisor_id][] = (int) $row->user_id;
        }

        $result = [];
        $visited = [$userId => true];
        $stack = [$userId];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach ($children[$current] ?? [] as $sub) {
                if (isset($visited[$sub])) {
                    continue;
                }
                $visited[$sub] = true;
                $result[] = $sub;
                $stack[] = $sub;
            }
        }

        return $result;
    }

    /**
     * Whether $actor is a supervisor (direct or indirect) of $target,
     * or an ACL-9 administrator.
     */
    public function isSupervisor(int $actorId, int $targetId, int $actorAcl): bool
    {
        if ($actorAcl >= 9) {
            return true;
        }

        if ($actorId === $targetId) {
            return false;
        }

        return in_array($targetId, $this->allSubordinates($actorId), true);
    }
}
