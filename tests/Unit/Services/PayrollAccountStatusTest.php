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
        $this->assertStatusIsActive('A', true);
        $this->assertStatusIsActive('I', false);
        $this->assertStatusIsActive(null, false);
    }

    private function assertStatusIsActive(?string $status, bool $expected): void
    {
        $query = Mockery::mock();
        $connection = Mockery::mock();

        DB::shouldReceive('connection')->once()->with('payroll')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('payroll')->andReturn($query);
        $query->shouldReceive('where')->once()->with('id', 123)->andReturnSelf();
        $query->shouldReceive('value')->once()->with('status')->andReturn($status);

        $this->assertSame($expected, app(PayrollAccountStatus::class)->isActive(123));
    }
}
