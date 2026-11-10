<?php

namespace Tests\Unit\Services;

use App\Services\PayrollAccountStatus;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PayrollAccountStatusTest extends TestCase
{
    public function test_only_status_a_is_active(): void
    {
        $query = Mockery::mock();
        $connection = Mockery::mock();

        DB::shouldReceive('connection')->times(3)->with('payroll')->andReturn($connection);
        $connection->shouldReceive('table')->times(3)->with('payroll')->andReturn($query);
        $query->shouldReceive('where')->times(3)->with('id', 123)->andReturnSelf();
        $query->shouldReceive('value')->times(3)->with('status')->andReturn('A', 'I', null);

        $accounts = app(PayrollAccountStatus::class);

        $this->assertTrue($accounts->isActive(123));
        $this->assertFalse($accounts->isActive(123));
        $this->assertFalse($accounts->isActive(123));
    }
}
