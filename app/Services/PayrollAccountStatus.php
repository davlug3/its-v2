<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PayrollAccountStatus
{
    /**
     * The external payroll application owns account activation.
     * Missing payroll records and every status other than "A" are inactive.
     */
    public function isActive(int $userId): bool
    {
        $status = DB::connection('payroll')
            ->table('payroll')
            ->where('id', $userId)
            ->value('status');

        return $status === 'A';
    }
}
