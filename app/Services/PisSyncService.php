<?php

namespace App\Services;

use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

class PisSyncService
{
    /**
     * Sync one user from the PIS database.
     *
     * @return array{ok: bool, message: string}
     */
    public function sync(int $userId): array
    {
        $user = DB::connection('payroll')
            ->table('user')
            ->where('id', $userId)
            ->first();

        if ($user === null) {
            return ['ok' => false, 'message' => 'User not found in PIS.'];
        }

        $payroll = DB::connection('payroll')
            ->table('payroll')
            ->where('id', $userId)
            ->first();

        if ($payroll === null) {
            return ['ok' => false, 'message' => 'Payroll record not found in PIS.'];
        }

        $departmentId = (int) $payroll->department;

        if ($departmentId === 0) {
            return ['ok' => false, 'message' => 'PIS department is 0. Configure the location manually.'];
        }

        $department = DB::connection('payroll')
            ->table('department')
            ->where('id', $departmentId)
            ->first();

        if ($department === null) {
            return ['ok' => false, 'message' => "PIS department {$departmentId} does not exist."];
        }

        // Verify the location exists in the helpdesk locations view.
        // its.locations is retired; department.id is the location id.
        // We trust the department table as the source of truth.

        $aclId = $this->resolveAcl($userId, $departmentId);

        $state = $payroll->status === 'A' ? 1 : 2;

        $profile = UserProfile::firstOrNew(['user_id' => $userId]);

        if (! $profile->exists) {
            $profile->acl_id = $aclId;
            $profile->group_id = 1;
            $profile->login_attempts = (int) $user->failed_attempts;
            $profile->save();
        } else {
            // Update ACL in case the department changed.
            $profile->acl_id = $aclId;
            $profile->save();
        }

        return ['ok' => true, 'message' => 'Synced. Location: '.$departmentId];
    }

    private function resolveAcl(int $userId, int $departmentId): int
    {
        // ACL is authoritative in the legacy its.users table (read-only).
        $legacy = DB::connection('mysql')
            ->table('users')
            ->where('id', $userId)
            ->value('acl_id');

        if ($legacy !== null) {
            $aclId = (int) $legacy;

            // Legacy uses 0 for unset accounts; treat as End User.
            if ($aclId === 0) {
                $aclId = 1;
            }

            if (in_array($aclId, [1, 3, 9], true)) {
                return $aclId;
            }
        }

        // Fallback when its.users has no row for this user.
        $protectedAdmins = [334, 2194, 2415, 3040, 4735, 4862, 4991, 4992];
        if (in_array($userId, $protectedAdmins, true)) {
            return 9;
        }

        if ($departmentId === 15) {
            return 3;
        }

        return 1;
    }
}
