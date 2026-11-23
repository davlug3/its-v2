<?php

namespace Tests\Unit;

use App\Support\CsvSafe;
use PHPUnit\Framework\TestCase;

class CsvSafeTest extends TestCase
{
    public function test_prefixes_formula_triggers(): void
    {
        foreach (['=1+1', '+1', '-1', '@SUM(A1)', "\tcmd", "\rDDE"] as $value) {
            $this->assertSame("'$value", CsvSafe::cell($value), "Value should be escaped: {$value}");
        }
    }

    public function test_leaves_safe_values_unchanged(): void
    {
        foreach (['Normal subject', '123', 'a=b', '', '0'] as $value) {
            $this->assertSame($value, CsvSafe::cell($value), "Value should be unchanged: {$value}");
        }
    }

    public function test_casts_non_string_values(): void
    {
        $this->assertSame('42', CsvSafe::cell(42));
        $this->assertSame('', CsvSafe::cell(null));
    }
}
